<?php
/**
 * FMB Anti-Fraud & Checkout Restrictions System
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Checkout_Restrictions {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_checkout_posted_data', array($this, 'clean_phone_number_format'), 10, 1);
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_phone_number'), 10, 2);
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_blacklisted_data'), 10, 2);
        add_action('woocommerce_after_checkout_validation', array($this, 'enforce_order_limit'), 10, 2);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_device_hashes'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_fingerprint_script'));
        add_action('wp_footer', array($this, 'inject_vpn_modal'));

        add_action('wp_ajax_fmb_check_vpn_status', array($this, 'ajax_check_vpn_status'));
        add_action('wp_ajax_nopriv_fmb_check_vpn_status', array($this, 'ajax_check_vpn_status'));
    }

    /**
     * Normalize Bangladeshi Phone Number helper
     */
    public static function normalize_phone($phone) {
        if (class_exists('\fmb_engine\Core\Base') && method_exists('\fmb_engine\Core\Base', 'normalize_phone_number')) {
            return \fmb_engine\Core\Base::normalize_phone_number($phone);
        }
        $bn_digits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($bn_digits, $en_digits, (string)$phone);
        $digits = preg_replace('/[^\d]/', '', $phone);
        if (strpos($digits, '0088') === 0) $digits = substr($digits, 4);
        elseif (strpos($digits, '88') === 0) $digits = substr($digits, 2);
        if (strlen($digits) === 10 && strpos($digits, '1') === 0) $digits = '0' . $digits;
        return $digits;
    }

    /**
     * Clean phone format before checkout processing
     */
    public function clean_phone_number_format($data) {
        if (!empty($data['billing_phone'])) {
            $data['billing_phone'] = self::normalize_phone($data['billing_phone']);
        }
        if (!empty($data['shipping_phone'])) {
            $data['shipping_phone'] = self::normalize_phone($data['shipping_phone']);
        }
        return $data;
    }

    /**
     * Validate 11-digit Bangladeshi Phone Format
     */
    public function validate_phone_number($data, $errors) {
        $validate_enabled = get_option('ads_validate_phone_number_length_number', get_option('fmb_enable_phone_length_validation', true));
        if ($validate_enabled) {
            $phone = isset($data['billing_phone']) ? sanitize_text_field($data['billing_phone']) : '';
            $digits_only = self::normalize_phone($phone);

            if (strlen($digits_only) !== 11 || !preg_match('/^01[3-9]\d{8}$/', $digits_only)) {
                $errors->add('validation', __('দয়া করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর প্রদান করুন (যেমন: 01712345678)।', 'fmb-store'));
            }
        }
    }

    /**
     * Validate Blacklisted Phone, Email, IP Address or Device
     */
    public function validate_blacklisted_data($data, $errors) {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'ads_customers_data',
            $wpdb->prefix . 'fmb_customers_data'
        ];

        $phone = isset($data['billing_phone']) ? self::normalize_phone(sanitize_text_field($data['billing_phone'])) : '';
        $email = isset($data['billing_email']) ? sanitize_email($data['billing_email']) : '';
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $device_hash = isset($_COOKIE['_fmb_did']) ? sanitize_text_field($_COOKIE['_fmb_did']) : (isset($_COOKIE['_ads_device_id']) ? sanitize_text_field($_COOKIE['_ads_device_id']) : '');

        $block_phone_enabled = get_option('ads_block_phone_numbers', true);
        $block_device_enabled = get_option('ads_block_device_ids', true);
        $vpn_shield_enabled = get_option('ads_enable_vpn_shield', false);

        $custom_blocked_msg = get_option('ads_checkout_process_error_blocked_customer_message', '');
        $blocked_msg = !empty($custom_blocked_msg) ? $custom_blocked_msg : __('আপনার অ্যাকাউন্ট অথবা নেটওয়ার্কটি এই মুহূর্তে নতুন অর্ডার প্রদানে সীমাবদ্ধ। বিস্তারিত জানতে কাস্টমার কেয়ারে যোগাযোগ করুন।', 'fmb-store');

        // 1. Check Blocked Phone
        if ($block_phone_enabled && !empty($phone)) {
            $last_10 = substr($phone, -10);
            foreach ($tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                    $blocked = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$table} WHERE data_type = 'phone_number' AND (data_value = %s OR data_value LIKE %s) AND data_access = 'blocked'",
                        $phone,
                        '%' . $wpdb->esc_like($last_10)
                    ));
                    if (!$blocked) {
                        $blocked = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$table} WHERE data_type = 'phone' AND (data_value = %s OR data_value LIKE %s) AND data_access = 'blocked'",
                            $phone,
                            '%' . $wpdb->esc_like($last_10)
                        ));
                    }
                    if ($blocked) {
                        $errors->add('validation', $blocked_msg);
                        return;
                    }
                }
            }
        }

        // 2. Check Blocked Device / IP
        if ($block_device_enabled) {
            foreach ($tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                    if (!empty($device_hash)) {
                        $blocked_device = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$table} WHERE data_type = 'device_hash' AND data_value = %s AND data_access = 'blocked'",
                            $device_hash
                        ));
                        if ($blocked_device) {
                            $errors->add('validation', $blocked_msg);
                            return;
                        }
                    }

                    if (!empty($ip) && $ip !== '127.0.0.1' && $ip !== '::1') {
                        $blocked_ip = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$table} WHERE data_type = 'ip' AND data_value = %s AND data_access = 'blocked'",
                            $ip
                        ));
                        if ($blocked_ip) {
                            $errors->add('validation', $blocked_msg);
                            return;
                        }
                    }
                }
            }
        }

        // 3. Check VPN Shield
        if ($vpn_shield_enabled) {
            $is_vpn = false;
            $headers = ['HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'HTTP_FORWARDED_FOR_IP', 'VIA', 'X_FORWARDED_FOR', 'FORWARDED_FOR', 'X_FORWARDED', 'FORWARDED', 'CLIENT_IP', 'FORWARDED_FOR_IP', 'HTTP_PROXY_CONNECTION'];
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $is_vpn = true;
                    break;
                }
            }
            if ($is_vpn) {
                $errors->add('validation', __('VPN অথবা Proxy সংযোগ সনাক্ত হয়েছে। অনুগ্রহ করে VPN বন্ধ করে অর্ডার করুন।', 'fmb-store'));
                return;
            }
        }
    }

    /**
     * Enforce Multiple Order Limit per Phone and Device
     */
    public function enforce_order_limit($data, $errors) {
        $phone = isset($data['billing_phone']) ? self::normalize_phone(sanitize_text_field($data['billing_phone'])) : '';
        $device_hash = isset($_COOKIE['_fmb_did']) ? sanitize_text_field($_COOKIE['_fmb_did']) : '';
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        // 1. Restrict multiple orders by Phone
        $phone_limit_enabled = get_option('ads_restrict_multiple_orders_phone', get_option('fmb_enable_order_limit', false));
        if ($phone_limit_enabled && !empty($phone)) {
            $max_orders = (int) get_option('ads_restrict_multiple_orders_phone_limit', get_option('fmb_max_orders_per_customer', 1));
            $time_hours = (int) get_option('ads_restrict_multiple_orders_phone_time', 24);

            $last_10 = substr($phone, -10);
            $recent_orders = wc_get_orders(array(
                'limit'        => $max_orders + 1,
                'date_after'   => date('Y-m-d H:i:s', strtotime("-{$time_hours} hours")),
                'billing_phone'=> $phone,
                'return'       => 'ids',
            ));

            if (count($recent_orders) >= $max_orders) {
                $errors->add('validation', sprintf(__('আপনি বিগত %d ঘণ্টায় সর্বোচ্চ %dটি অর্ডার সম্পন্ন করতে পারবেন। অনুগ্রহ করে কিছু সময় পর চেষ্টা করুন।', 'fmb-store'), $time_hours, $max_orders));
                return;
            }
        }

        // 2. Restrict multiple orders by Device ID
        $device_limit_enabled = get_option('ads_restrict_multiple_orders_device', false);
        if ($device_limit_enabled) {
            $max_orders_device = (int) get_option('ads_restrict_multiple_orders_device_limit', 1);
            $time_hours_device = (int) get_option('ads_restrict_multiple_orders_device_time', 24);

            if (!empty($device_hash) || (!empty($ip) && $ip !== '127.0.0.1' && $ip !== '::1')) {
                global $wpdb;
                $hpos_table = $wpdb->prefix . 'wc_orders';
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$time_hours_device} hours"));
                
                $dev_order_count = 0;
                if ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table) {
                    if (!empty($ip)) {
                        $dev_order_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(id) FROM {$hpos_table} WHERE ip_address = %s AND date_created_gmt >= %s",
                            $ip,
                            $cutoff
                        ));
                    }
                }

                if ($dev_order_count >= $max_orders_device) {
                    $errors->add('validation', sprintf(__('এই ডিভাইস থেকে বিগত %d ঘণ্টায় সর্বোচ্চ অনুমোদিত অর্ডার সীমা (%dটি) পূর্ণ হয়েছে।', 'fmb-store'), $time_hours_device, $max_orders_device));
                    return;
                }
            }
        }
    }

    /**
     * Save Device Fingerprints to Order Meta
     */
    public function save_device_hashes($order_id, $data) {
        $device_hash = !empty($_POST['fmb_device_hash']) ? sanitize_text_field($_POST['fmb_device_hash']) : (isset($_COOKIE['_fmb_did']) ? sanitize_text_field($_COOKIE['_fmb_did']) : '');
        $hw_hash = !empty($_POST['fmb_hardware_hash']) ? sanitize_text_field($_POST['fmb_hardware_hash']) : (isset($_COOKIE['_fmb_hw_did']) ? sanitize_text_field($_COOKIE['_fmb_hw_did']) : '');

        if (!empty($device_hash)) {
            update_post_meta($order_id, '_fmb_device_hash', $device_hash);
        }
        if (!empty($hw_hash)) {
            update_post_meta($order_id, '_fmb_hardware_hash', $hw_hash);
        }
    }

    /**
     * Enqueue Device Fingerprinting Script
     */
    public function enqueue_fingerprint_script() {
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
            wp_enqueue_script('fmb-device-fingerprint', FMB_ENGINE_URL . 'assets/js/device-fingerprint.js', array('jquery'), '1.0.0', true);
            wp_localize_script('fmb-device-fingerprint', 'fmbEngineParams', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }

    public function ajax_check_vpn_status() {
        wp_send_json(array('enabled' => (bool) get_option('ads_enable_vpn_shield', get_option('fmb_enable_vpn_shield', false))));
    }

    public function inject_vpn_modal() {
        if (!is_checkout()) return;
        ?>
        <div id="fmb-vpn-warning-modal" style="display:none; position:fixed; z-index:999999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:12px; padding:24px; max-width:400px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                <div style="font-size:40px; color:#ef4444; margin-bottom:10px;">⚠️</div>
                <h3 style="font-size:18px; font-weight:700; color:#1f2937; margin-bottom:8px;">VPN / Proxy সনাক্ত করা হয়েছে</h3>
                <p style="font-size:14px; color:#4b5563; margin-bottom:16px;">অর্ডার নিশ্চিত করতে দয়া করে আপনার ডিভাইসের VPN বন্ধ করুন এবং পৃষ্ঠাটি রিফ্রেশ করুন।</p>
            </div>
        </div>
        <?php
    }
}
