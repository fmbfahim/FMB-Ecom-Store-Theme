<?php
/**
 * FMB Incomplete Orders & Abandoned Checkout Tracker
 * Merges abandoned checkout autosave and live lead tracker into one system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Incomplete_Orders {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_autosave_scripts'));

        // Dual AJAX hooks for backwards compatibility
        add_action('wp_ajax_fmb_save_lead', array($this, 'process_autosave'));
        add_action('wp_ajax_nopriv_fmb_save_lead', array($this, 'process_autosave'));

        // AJAX handlers for Live Leads Admin Actions
        add_action('wp_ajax_fmb_create_order_from_lead', array($this, 'ajax_create_order_from_lead'));
        add_action('wp_ajax_fmb_delete_lead', array($this, 'ajax_delete_lead'));
        add_action('wp_ajax_fmb_update_lead_note', array($this, 'ajax_update_lead_note'));

        add_action('woocommerce_checkout_order_processed', array($this, 'cleanup_incomplete_order'), 10, 1);
        add_action('admin_menu', array($this, 'register_leads_menu'));
    }

    public function enqueue_autosave_scripts() {
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
            wp_enqueue_script('fmb-incomplete-autosave', FMB_ENGINE_URL . 'assets/js/incomplete-autosave.js', array('jquery'), '1.0.0', true);
            wp_localize_script('fmb-incomplete-autosave', 'fmbAutosaveParams', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }

    /**
     * Unified Handler on Checkout Field Change / Lead Capture
     */
    public function process_autosave() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        $raw_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : (isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '');
        $name      = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : (isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '');
        $address   = isset($_POST['billing_address_1']) ? sanitize_text_field($_POST['billing_address_1']) : (isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '');
        $email     = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';

        // Universal phone normalization
        if (function_exists('fmb_clean_and_normalize_phone')) {
            $phone_clean = fmb_clean_and_normalize_phone($raw_phone);
        } else {
            $digits = preg_replace('/[^\d]/', '', (string)$raw_phone);
            if (strpos($digits, '0088') === 0) $digits = substr($digits, 4);
            elseif (strpos($digits, '88') === 0) $digits = substr($digits, 2);
            if (strlen($digits) === 10 && strpos($digits, '1') === 0) $digits = '0' . $digits;
            $phone_clean = $digits;
        }

        if (strlen($phone_clean) < 11 && empty($email)) {
            wp_send_json_error(array('message' => 'Insufficient data'));
        }

        // Parse cart items and compute total
        $cart_items = array();
        $calculated_total = 0;

        $raw_cart = isset($_POST['cart']) ? stripslashes($_POST['cart']) : (isset($_POST['order_items']) ? stripslashes($_POST['order_items']) : '');
        $decoded_cart = json_decode($raw_cart, true);

        if (!empty($decoded_cart) && is_array($decoded_cart)) {
            foreach ($decoded_cart as $c_item) {
                $p_id = absint($c_item['id'] ?? $c_item['product_id'] ?? 0);
                $qty  = max(1, absint($c_item['qty'] ?? $c_item['quantity'] ?? 1));
                $price = isset($c_item['price']) ? floatval($c_item['price']) : 0;

                if ($p_id > 0) {
                    $wc_prod = wc_get_product($p_id);
                    $p_name  = $wc_prod ? $wc_prod->get_name() : ($c_item['name'] ?? 'Product #' . $p_id);
                    if ($price <= 0 && $wc_prod) {
                        $price = floatval($wc_prod->get_price());
                    }
                    $line_total = $price * $qty;
                    $calculated_total += $line_total;

                    $cart_items[] = array(
                        'product_id' => $p_id,
                        'name'       => $p_name,
                        'quantity'   => $qty,
                        'price'      => $price,
                        'total'      => $line_total,
                        'img'        => $c_item['img'] ?? ''
                    );
                }
            }
        } elseif (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $line_total = floatval($cart_item['line_total']);
                $calculated_total += $line_total;
                $cart_items[] = array(
                    'product_id' => $cart_item['product_id'],
                    'name'       => $product ? $product->get_name() : '',
                    'quantity'   => $cart_item['quantity'],
                    'price'      => $cart_item['quantity'] > 0 ? ($line_total / $cart_item['quantity']) : $line_total,
                    'total'      => $line_total
                );
            }
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE billing_phone = %s AND status_to IN ('ads-incomplete', 'fmb-incomplete')",
            $phone_clean
        ));

        $data = array(
            'created_at'         => current_time('mysql'),
            'status_from'        => 'ads-incomplete',
            'status_to'          => 'ads-incomplete',
            'order_total'        => $calculated_total,
            'user_id'            => get_current_user_id(),
            'email'              => $email,
            'billing_first_name' => $name,
            'billing_phone'      => $phone_clean,
            'billing_address_1'  => $address,
            'cart_data'          => wp_json_encode($cart_items)
        );

        if ($existing) {
            $wpdb->update($table_name, $data, array('id' => $existing->id));
        } else {
            $wpdb->insert($table_name, $data);
        }

        wp_send_json_success(array('status' => 'autosaved'));
    }

    /**
     * Delete or mark recovered when order is successfully completed
     */
    public function cleanup_incomplete_order($order_id) {
        global $wpdb;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = preg_replace('/[^\d]/', '', $order->get_billing_phone());
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        if (!empty($phone)) {
            $wpdb->delete($table_name, array('billing_phone' => $phone));
        }
    }

    /**
     * Register Abandoned Live Leads Submenu under FMB Store Main Menu
     */
    public function register_leads_menu() {
        add_submenu_page(
            'fmb-store',
            'Live Leads (Abandoned)',
            'Live Leads',
            'manage_woocommerce',
            'fmb-live-leads',
            array($this, 'render_leads_page')
        );
    }

    /**
     * AJAX: Create WooCommerce Order from Lead
     */
    public function ajax_create_order_from_lead() {
        check_ajax_referer('fmb_live_leads_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $lead_id));
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found'));
        }

        $order = wc_create_order();
        $cart_items = json_decode($lead->cart_data, true);

        if (!empty($cart_items) && is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $p_id = absint($item['product_id'] ?? $item['id'] ?? 0);
                $qty = max(1, absint($item['quantity'] ?? $item['qty'] ?? 1));
                if ($p_id > 0) {
                    $prod = wc_get_product($p_id);
                    if ($prod) {
                        $order->add_product($prod, $qty);
                    }
                }
            }
        }

        $address = array(
            'first_name' => $lead->billing_first_name,
            'phone'      => $lead->billing_phone,
            'address_1'  => $lead->billing_address_1,
            'country'    => 'BD',
        );

        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        $order->set_payment_method('cod');

        $order->calculate_totals();
        $order->update_status('processing', 'Converted from Live Lead #' . $lead_id);
        $order_id = $order->get_id();

        // Update lead table
        $wpdb->update(
            $table_name,
            array(
                'status_to' => 'ads-recovered',
                'order_id'  => $order_id
            ),
            array('id' => $lead_id)
        );

        wp_send_json_success(array(
            'message' => 'Order created successfully! Order #' . $order_id,
            'order_id' => $order_id,
            'edit_url' => admin_url('post.php?post=' . $order_id . '&action=edit')
        ));
    }

    /**
     * AJAX: Delete Lead
     */
    public function ajax_delete_lead() {
        check_ajax_referer('fmb_live_leads_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $wpdb->delete($table_name, array('id' => $lead_id));

        wp_send_json_success(array('message' => 'Lead deleted successfully'));
    }

    /**
     * AJAX: Update Lead Note
     */
    public function ajax_update_lead_note() {
        check_ajax_referer('fmb_live_leads_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $wpdb->update($table_name, array('note' => $note), array('id' => $lead_id));

        wp_send_json_success(array('message' => 'Note saved'));
    }

    /**
     * Render Live Leads & Abandoned Checkouts Page
     */
    public function render_leads_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $leads = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 100");
        $nonce = wp_create_nonce('fmb_live_leads_nonce');
        ?>
        <style>
            .fmb-wrap { font-family: 'Inter', -apple-system, sans-serif; max-width: 1400px; margin: 20px 20px 20px 0; animation: fadeUp 0.5s ease-out backwards; animation-delay: 0.1s; }
            .fmb-card { background: #ffffff; border-radius: 24px; padding: 36px; box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.05); margin-bottom: 36px; border: 1px solid #f1f5f9; }
            .fmb-table { width: 100%; border-collapse: separate; border-spacing: 0; }
            .fmb-table th { color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; padding: 18px; text-align: left; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
            .fmb-table th:first-child { border-top-left-radius: 16px; border-bottom-left-radius: 16px; }
            .fmb-table th:last-child { border-top-right-radius: 16px; border-bottom-right-radius: 16px; }
            .fmb-table td { padding: 18px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: middle; transition: all 0.2s ease; }
            .fmb-table tr:last-child td { border-bottom: none; }
            
            .fmb-table tbody tr { transition: transform 0.2s ease, background 0.2s ease; border-radius: 16px; }
            .fmb-table tbody tr:hover { transform: translateX(4px); background: #f8fafc; }
            .fmb-table tbody tr:hover td { border-bottom-color: transparent; }
            
            .fmb-badge-total { background: #dcfce7; color: #166534; padding: 6px 14px; border-radius: 30px; font-size: 13px; font-weight: 800; }
            
            .fmb-btn { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
            .fmb-btn-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
            .fmb-btn-blue:hover { background: #3b82f6; color: white; border-color: #2563eb; transform: translateY(-1px); }
            .fmb-btn-green { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
            .fmb-btn-green:hover { background: #22c55e; color: white; border-color: #16a34a; transform: translateY(-1px); }
            .fmb-btn-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
            .fmb-btn-red:hover { background: #ef4444; color: white; border-color: #dc2626; transform: translateY(-1px); }
        </style>
        
        <div class="fmb-wrap">
            <div class="fmb-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
                    <h3 style="margin:0; font-size:20px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:24px;">⚡</span> Abandoned Checkouts
                    </h3>
                    <div style="font-size:13px; color:#475569; background:#f8fafc; padding:8px 16px; border-radius:12px; border:1px solid #e2e8f0; font-weight:600;">
                        Total Records: <strong style="color:#2563eb;"><?php echo count($leads); ?></strong>
                    </div>
                </div>
                
                <table class="fmb-table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Date & Time</th>
                            <th style="width:130px;">Customer</th>
                            <th style="width:130px;">Phone</th>
                            <th>Address</th>
                            <th>Products</th>
                            <th style="width:110px;">Cart Total</th>
                            <th style="width:160px;">Note</th>
                            <th style="width:230px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leads)): foreach ($leads as $lead): ?>
                            <tr id="lead-row-<?php echo esc_attr($lead->id); ?>">
                                <td>
                                    <span style="font-weight:600; color:#0f172a;"><?php echo esc_html(wp_date('M j, Y', strtotime($lead->created_at))); ?></span>
                                    <br><span style="font-size:12px; color:#64748b; font-family:monospace;"><?php echo esc_html(wp_date('h:i A', strtotime($lead->created_at))); ?></span>
                                </td>
                                <td>
                                    <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html($lead->billing_first_name ?: 'No Name'); ?></strong>
                                </td>
                                <td>
                                    <a href="tel:<?php echo esc_attr($lead->billing_phone); ?>" style="color:#2563eb; font-weight:700; text-decoration:none; font-family:monospace;">
                                        <?php echo esc_html($lead->billing_phone); ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="color:#475569; font-size:13px; display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($lead->billing_address_1); ?>">
                                        <?php echo esc_html($lead->billing_address_1 ?: '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $cart_data = json_decode($lead->cart_data, true);
                                    if (!empty($cart_data) && is_array($cart_data)) {
                                        $items = array();
                                        foreach ($cart_data as $item) {
                                            $p_id = $item['product_id'] ?? $item['id'] ?? 0;
                                            $p_name = $item['name'] ?? '';
                                            $p_qty = $item['quantity'] ?? $item['qty'] ?? 1;
                                            if (!$p_name && $p_id > 0) {
                                                $prod = wc_get_product($p_id);
                                                if ($prod) $p_name = $prod->get_name();
                                            }
                                            if (!$p_name) $p_name = 'Product #' . $p_id;
                                            $items[] = '<div style="font-size:13px; font-weight:600; color:#1e293b; margin-bottom:4px; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">• ' . esc_html($p_name) . ' <span style="color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:10px; font-size:11px;">x' . esc_html($p_qty) . '</span></div>';
                                        }
                                        echo implode('', $items);
                                    } else {
                                        echo '<span style="color:#94a3b8; font-size:13px;">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="fmb-badge-total">
                                        <?php echo wc_price(floatval($lead->order_total)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <input type="text" id="lead-note-<?php echo esc_attr($lead->id); ?>" value="<?php echo esc_attr($lead->note ?? ''); ?>" placeholder="Add note..." style="font-size:12px; padding:6px 10px; width:100%; border:1px solid #cbd5e1; border-radius:8px; outline:none; transition:border 0.2s;">
                                        <button type="button" class="fmb-btn fmb-btn-blue fmb-save-note-btn" data-id="<?php echo esc_attr($lead->id); ?>" title="Save Note">💾</button>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                                        <?php if (!empty($lead->order_id)): ?>
                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $lead->order_id . '&action=edit')); ?>" class="fmb-btn fmb-btn-green" title="View Order">
                                                ✓ Order #<?php echo esc_html($lead->order_id); ?>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="fmb-btn fmb-btn-blue fmb-create-order-btn" data-id="<?php echo esc_attr($lead->id); ?>" title="Create WooCommerce Order">
                                                🛒 Create Order
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="https://api.whatsapp.com/send?phone=88<?php echo esc_attr($lead->billing_phone); ?>&text=Hi%20<?php echo urlencode($lead->billing_first_name); ?>" target="_blank" class="fmb-btn" style="background:#25d366; color:#fff; border:none;" title="Chat on WhatsApp">
                                            WA
                                        </a>

                                        <button type="button" class="fmb-btn fmb-btn-red fmb-delete-lead-btn" data-id="<?php echo esc_attr($lead->id); ?>" title="Delete Lead">
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" style="text-align:center; color:#64748b; padding:40px; font-weight:500;">No abandoned checkouts recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js($nonce); ?>';
            
            // Create Order
            $('.fmb-create-order-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var leadId = btn.data('id');
                if (!confirm('আপনি কি এই কাস্টমারের তথ্যের উপর ভিত্তি করে একটি নতুন অর্ডার তৈরি করতে চান?')) return;

                btn.prop('disabled', true).text('Creating...');
                $.post(ajaxurl, {
                    action: 'fmb_create_order_from_lead',
                    lead_id: leadId,
                    nonce: nonce
                }, function(res) {
                    if (res.success) {
                        alert(res.data.message);
                        btn.replaceWith('<a href="' + res.data.edit_url + '" class="fmb-btn fmb-btn-green" title="View Order">✓ Order #' + res.data.order_id + '</a>');
                    } else {
                        alert('Error: ' + (res.data.message || 'Failed'));
                        btn.prop('disabled', false).text('🛒 Create Order');
                    }
                });
            });

            // Save Note
            $('.fmb-save-note-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var leadId = btn.data('id');
                var noteVal = $('#lead-note-' + leadId).val();

                btn.prop('disabled', true).text('...');
                $.post(ajaxurl, {
                    action: 'fmb_update_lead_note',
                    lead_id: leadId,
                    note: noteVal,
                    nonce: nonce
                }, function(res) {
                    btn.prop('disabled', false).text('✓');
                    setTimeout(function(){ btn.text('💾'); }, 1500);
                });
            });

            // Delete Lead
            $('.fmb-delete-lead-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var leadId = btn.data('id');
                if (!confirm('Are you sure you want to delete this lead?')) return;

                $.post(ajaxurl, {
                    action: 'fmb_delete_lead',
                    lead_id: leadId,
                    nonce: nonce
                }, function(res) {
                    if (res.success) {
                        $('#lead-row-' + leadId).fadeOut();
                    } else {
                        alert('Failed to delete');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
