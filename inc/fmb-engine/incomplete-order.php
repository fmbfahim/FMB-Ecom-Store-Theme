<?php
namespace fmb_engine;
use fmb_engine\Core\Base;
use fmb_engine\Incomplete_Orders_Tracker;
use fmb_engine\Traits\Order_Check_Trait;
class Incomplete_Order extends Base {
    use Order_Check_Trait;
    private $tracker;
    private $tracker_table;

    public function __construct( ?Incomplete_Orders_Tracker $tracker = null ) {
        global $wpdb;
        $this->tracker_table = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $this->tracker       = $tracker ? $tracker : new Incomplete_Orders_Tracker();

        if (get_option('ads_enable_order_autosave')) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_autosave_scripts']);
            add_action('wp_ajax_autosave_order', [$this, 'process_autosave_order']);
            add_action('wp_ajax_nopriv_autosave_order', [$this, 'process_autosave_order']);
        }

        add_action('woocommerce_checkout_order_processed', [$this, 'delete_autosave_order_on_checkout'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'track_status_change'], 10, 3);
    }

    /**
     * Save incomplete order data to custom tracker table
     */
    private function save_to_tracker_table($order_id) {
        global $wpdb;
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Prepare data for tracker table
        $data = [
            'order_id'          => $order_id,
            'created_at'        => current_time('mysql'),
            'recovered_at'      => current_time('mysql'),
            'status_from'       => 'ads-incomplete',
            'status_to'         => 'ads-incomplete',
            'order_total'       => $order->get_total(),
            'user_id'           => $order->get_user_id(),
            'email'             => $order->get_billing_email(),
            'billing_first_name'=> $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_phone'     => $order->get_billing_phone(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_state'     => $order->get_billing_state(),
            'note'              => $order->get_customer_note()
        ];

        // Check if record already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->tracker_table} WHERE order_id = %d",
            $order_id
        ));

        if ($existing) {
            // Update existing record
            $wpdb->update(
                $this->tracker_table,
                $data,
                ['id' => $existing->id]
            );
        } else {
            // Insert new record
            $wpdb->insert($this->tracker_table, $data);
        }
        if ( function_exists( 'orderflow_bust_incomplete_order_menu_count_cache' ) ) {
            orderflow_bust_incomplete_order_menu_count_cache();
        }
    }

    /**
     * Delete record from tracker table
     */
    private function delete_from_tracker_table($order_id) {
        global $wpdb;
        $wpdb->delete(
            $this->tracker_table,
            ['order_id' => $order_id],
            ['%d']
        );
        if ( function_exists( 'orderflow_bust_incomplete_order_menu_count_cache' ) ) {
            orderflow_bust_incomplete_order_menu_count_cache();
        }
    }

    public function track_status_change($order_id, $old_status, $new_status) {
        global $wpdb;
        // Remove 'wc-' prefix from status values
        $old_status = str_replace('wc-', '', $old_status);
        $new_status = str_replace('wc-', '', $new_status);

        // Only process if previous status was 'ads-incomplete'
        if ($old_status !== 'ads-incomplete') return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $wpdb->update(
            $table_name,
            [
                'recovered_at' => current_time('mysql'),
                'status_from' => $old_status,
                'status_to' => $new_status,
                'order_total' => $order->get_total()
            ],
            ['order_id' => $order_id]
        );
        if ( function_exists( 'orderflow_bust_incomplete_order_menu_count_cache' ) ) {
            orderflow_bust_incomplete_order_menu_count_cache();
        }
    }

    public function enqueue_autosave_scripts() {
        wp_enqueue_script(
            'ads-incomplete-order',
            FMB_ENGINE_URL . 'assets/js/incomplete-order.js',
            ['jquery'],
            FMB_THEME_VERSION,
            true
        );
        wp_localize_script('ads-incomplete-order', 'ads_ajax_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ads_autosave_secure_nonce')
        ]);
    }

    public function process_autosave_order() {
        if (!get_option('ads_enable_order_autosave', 0)) {
            wp_die();
        }

        if (empty($_POST['billing_phone']) || strlen($_POST['billing_phone']) < 11) {
            wp_send_json_error('Missing required field(s)');
            wp_die();
        }

        $billing_phone = sanitize_text_field($_POST['billing_phone'] ?? '');
        $billing_email = sanitize_text_field($_POST['billing_email'] ?? '');
        $ip_address = \WC_Geolocation::get_ip_address();
        $session_time_limit = 60 * (get_option('ads_autosave_session_duration') ? (int)get_option('ads_autosave_session_duration') : 5);
        $is_new_order = false;

        // Block checks
        if ($billing_phone && get_option('ads_block_phone_numbers')) {
            $phone = self::normalize_phone_number($billing_phone);
            $phoneData = self::fetch_customer_record('phone_number', $phone);
            if ($phoneData && $phoneData->data_access === 'blocked') {
                wp_send_json_error('Phone is blocked');
                wp_die();
            }
        }

        if ($billing_email && get_option('ads_block_email_addresses')) {
            $emailData = self::fetch_customer_record('email_address', $billing_email);
            if ($emailData && $emailData->data_access === 'blocked') {
                wp_send_json_error('Email is blocked');
                wp_die();
            }
        }

        if ($ip_address && get_option('ads_block_ip_addresses')) {
            $ipData = self::fetch_customer_record('ip_address', $ip_address);
            if ($ipData && $ipData->data_access === 'blocked') {
                wp_send_json_error('IP address is blocked');
                wp_die();
            }
        }

        $excluded_statuses = ['wc-ads-incomplete'];
        $allowed_statuses = array_diff(array_keys(wc_get_order_statuses()), $excluded_statuses);

        // Existing customer-created order check
        if (!empty($_COOKIE['ads_autosave_order_id'])) {
            $args = self::prepare_order_query_args(null, null, $allowed_statuses, (int)$_COOKIE['ads_autosave_order_id']);
            $orders = wc_get_orders($args);
            if ($orders && is_array($orders) && count($orders) > 0) {
                wp_send_json_error('Order already made by user and autosave is temporarily disabled.');
                wp_die();
            } else {
                self::validate_existing_orders_by_identifiers();
            }
        } else {
            self::validate_existing_orders_by_identifiers();
        }

        $order = null;

        // Existing autosave order check
        if (!empty($_COOKIE['ads_autosave_order_id'])) {
            $autosave_order_id = (int)$_COOKIE['ads_autosave_order_id'];
            $args = self::prepare_order_query_args(null, null, 'wc-ads-incomplete', $autosave_order_id);
            $orders = wc_get_orders($args);
            if ($orders && is_array($orders) && count($orders) > 0) {
                $order = $orders[0];
            } else {
                $order = self::find_autosaved_order_by_identifiers();
            }
        } else {
            $order = self::find_autosaved_order_by_identifiers();
        }

        if (!$order) {
            $order = wc_create_order();
            $is_new_order = true;
            $this->save_to_tracker_table($order->get_id());
        }

        setcookie('ads_autosave_order_id', $order->get_id(), time() + $session_time_limit, COOKIEPATH, COOKIE_DOMAIN);

        // Billing fields
        $fields = [
            'billing_first_name',
            'billing_last_name',
            'billing_address_1',
            'billing_phone',
            'billing_city',
            'billing_state',
            'billing_country',
            'billing_email'
        ];

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = sanitize_text_field($_POST[$field] ?? '');
        }

        // Merge with user meta if logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            foreach ($fields as $field) {
                $user_value = get_user_meta($user_id, $field, true);
                if (!empty($user_value) && empty($data[$field])) {
                    $data[$field] = $user_value;
                }
            }
        }

        // Update order billing details
        $order->set_billing_first_name($data['billing_first_name']);
        $order->set_billing_last_name($data['billing_last_name']);
        $order->set_billing_address_1($data['billing_address_1']);
        $order->set_billing_phone($data['billing_phone']);
        $order->set_billing_city($data['billing_city']);
        $order->set_billing_state($data['billing_state']);
        $order->set_billing_country($data['billing_country']);
        $order->set_billing_email($data['billing_email']);

        // Replace order items
        if (!$is_new_order) {
            $order->remove_order_items();
            $order->save(); // Force save to flush deleted items
        }
        
        if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $args = [
                'variation' => isset($cart_item['variation']) ? $cart_item['variation'] : [],
                'totals'    => [
                    'subtotal'     => isset($cart_item['line_subtotal']) ? $cart_item['line_subtotal'] : 0,
                    'subtotal_tax' => isset($cart_item['line_subtotal_tax']) ? $cart_item['line_subtotal_tax'] : 0,
                    'total'        => isset($cart_item['line_total']) ? $cart_item['line_total'] : 0,
                    'tax'          => isset($cart_item['line_tax']) ? $cart_item['line_tax'] : 0,
                    'tax_data'     => isset($cart_item['line_tax_data']) ? $cart_item['line_tax_data'] : []
                ]
            ];
            
            $item_id = $order->add_product($cart_item['data'], $cart_item['quantity'], $args);
            
            if (!$item_id) {
                // Fallback if add_product fails for some reason
                $order_item = new \WC_Order_Item_Product();
                $order_item->set_product($cart_item['data']);
                $order_item->set_quantity($cart_item['quantity']);
                $order_item->set_order_id($order->get_id());
                $order_item->set_subtotal($args['totals']['subtotal']);
                $order_item->set_total($args['totals']['total']);
                $item_id = $order_item->save();
                $order->add_item($order_item);
            }
            
            if ( $item_id ) {
                $item = $order->get_item( $item_id );
                do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $cart_item, $order );
            }
        }

        // Add shipping
        $shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (!empty($shipping_methods) && is_array($shipping_methods)) {
            $shipping_method_id = $shipping_methods[0];
            $shipping_total = WC()->cart->get_shipping_total();
            if ($shipping_total) {
                $rate = new \WC_Shipping_Rate($shipping_method_id, __('Shipping', 'woocommerce'), $shipping_total, [], $shipping_method_id);
                $order->add_shipping($rate);
            }
        }

        $order->set_total(WC()->cart->get_total('edit'));
        $order->set_status('ads-incomplete');
        $order->save();

        // Save to tracker table
        $this->save_to_tracker_table($order->get_id());

        // Save FB cookies
        $fb_cookie = [
            'fbc' => sanitize_text_field($_COOKIE['_fbc'] ?? ''),
            'fbp' => sanitize_text_field($_COOKIE['_fbp'] ?? ''),
            'event_source_url' => $_SERVER['HTTP_REFERER'] ?? '',
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        update_post_meta($order->get_id(), 'ads_fb_cookie', $fb_cookie);

        if ($is_new_order) {
            if (class_exists('\fmb_engine\Core\Base') && method_exists('\fmb_engine\Core\Base', 'log_activity')) {
                $customer_name = trim(($data['billing_first_name'] ?? '') . ' ' . ($data['billing_last_name'] ?? ''));
                if (empty($customer_name)) $customer_name = 'একজন ক্রেতা';
                \fmb_engine\Core\Base::log_activity("{$customer_name} একটি অসম্পূর্ণ অর্ডার (Incomplete Order) তৈরি করেছেন।", '🛒');
            }
        }

        wp_send_json_success([
            'message' => $is_new_order ? 'Incomplete order created' : 'Incomplete order updated',
            'order_id' => $order->get_id()
        ]);
        wp_die();
    }

    /**
     * Delete incomplete order when checkout is completed
     */
    public function delete_autosave_order_on_checkout($order_id, $manually = false ) {
        if ( $manually == true ) {
            $autosave_id = $order_id;
        } else {
            $autosave_id = !empty($_COOKIE['ads_autosave_order_id']) ? (int)$_COOKIE['ads_autosave_order_id'] : null;
        }

        // Clear autosave cookie
        setcookie('ads_autosave_order_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);

        $success = false;

        // If we have an autosave ID, delete that specific incomplete order
        if ($autosave_id) {
            $incomplete_order = wc_get_order($autosave_id);

            if ( $incomplete_order ) {
                // Force delete WooCommerce order
                $result = $incomplete_order->delete(true);

                if (is_wp_error($result)) {
                    error_log('Error deleting incomplete order: ' . $result->get_error_message());
                } else {
                    // Delete the corresponding tracker record
                    $this->delete_from_tracker_table($autosave_id);
                    $success = true;
                }
            } else {
                // Order not found in WC, but delete it from tracker table anyway
                $this->delete_from_tracker_table($autosave_id);
                $success = true;
            }
        }

        return $success;
    }
}