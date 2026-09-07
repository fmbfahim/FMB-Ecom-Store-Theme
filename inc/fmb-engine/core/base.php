<?php
namespace fmb_engine\Core;

class Base {
    /**
     * Log a recent activity
     */
    public static function log_activity($message, $icon = '') {
        $activities = get_option('ofls_recent_activities', []);
        if (!is_array($activities)) {
            $activities = [];
        }
        
        $entry = [
            'message' => $message,
            'icon' => $icon,
            'time' => time()
        ];
        
        array_unshift($activities, $entry);
        
        // Keep only the most recent 15 events
        if (count($activities) > 15) {
            $activities = array_slice($activities, 0, 15);
        }
        
        update_option('ofls_recent_activities', $activities);
    }

    /**
     * Checks if current request is a system context (CRON, AJAX, REST)
     *
     * @return bool
     */
    public static function is_system_request_context() {
        return defined('DOING_CRON') || wp_doing_ajax() || defined('DOING_AJAX') || defined('DOING_REST');
    }

    /**
     * Checks if current request is in preview mode
     *
     * @return bool
     */
    public static function is_preview_mode() {
        return is_preview() || isset($_GET['elementor-preview']) || isset($_GET['wp_scrape_key']);
    }

    /**
     * Generates hashed customer profile data
     *
     * @param int|null $user_id User ID (null for current user)
     * @param array $user_data Existing user data to append
     * @return array
     */
    public static function generate_hashed_customer_profile($user_id = null, $user_data = []) {
        $current_user_id = $user_id ?? get_current_user_id();
        $customer = new \WC_Customer($current_user_id);

        $fields = [
            'fn' => $customer->get_billing_first_name(),
            'ln' => $customer->get_billing_last_name(),
            'ph' => $customer->get_billing_phone(),
            'em' => $customer->get_billing_email(),
            'ct' => $customer->get_billing_city(),
            'zp' => $customer->get_billing_postcode(),
            'st' => $customer->get_billing_state(),
            'country' => $customer->get_billing_country(),
        ];

        foreach ($fields as $key => $value) {
            if (!array_key_exists($key, $user_data) && !empty($value)) {
                $user_data[$key] = hash('sha256', $value);
            }
        }

        return $user_data;
    }

    /**
     * Generates hashed order billing data
     *
     * @param int|null $order_id Order ID
     * @param array $user_data Existing user data to append
     * @return array
     */
    public static function generate_hashed_order_billing_data($order_id = null, $user_data = []) {
        if (!$order_id || !($order = wc_get_order($order_id))) {
            return $user_data;
        }

        $fields = [
            'fn'      => 'get_billing_first_name',
            'ln'      => 'get_billing_last_name',
            'ph'      => 'get_billing_phone',
            'em'      => 'get_billing_email',
            'ct'      => 'get_billing_city',
            'zp'      => 'get_billing_postcode',
            'st'      => 'get_billing_state',
            'country' => 'get_billing_country'
        ];

        foreach ($fields as $key => $method) {
            $value = $order->$method();
            if (!empty($value)) {
                $user_data[$key] = hash('sha256', $value);
            }
        }

        return $user_data;
    }

    /**
     * Collects client request data (IP and user agent)
     *
     * @param array $user_data Existing user data to append
     * @return array
     */
    public static function collect_client_request_data($user_data = []) {
        $user_data['client_ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_data['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return $user_data;
    }

    /**
     * Terminates execution if Facebook payload option is enabled
     */
    public static function terminate_after_facebook_payload() {
        if (get_option('ads_terminate_after_facebook_payload', false)) {
            exit;
        }
    }

    /**
     * Fetches customer record from database
     *
     * @param string $data_type Data type (phone_number, email_address, etc.)
     * @param string $data_value Data value
     * @return object|false
     */
    public static function fetch_customer_record($data_type = null, $data_value = null) {
        if (!$data_type || !$data_value) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_customers_data';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE data_type = %s AND data_value = %s",
            $data_type,
            $data_value
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Determines customer access class based on record
     *
     * @param object $row Database record
     * @return string CSS class name
     */
    public static function determine_customer_access_class($row) {
        return ($row && is_object($row) && $row->data_access === 'blocked')
            ? 'ads-customer-access-blocked'
            : 'ads-customer-access-allowed';
    }

    /**
     * Normalizes phone number by removing prefixes and non-numeric characters
     *
     * @param string $phone Phone number
     * @return string Normalized phone number
     */
    public static function normalize_phone_number($phone) {
        $bn_digits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($bn_digits, $en_digits, (string)$phone);

        // Strip everything except digits
        $digits = preg_replace('/[^\d]/', '', $phone);

        // Strip leading 0088
        if (strpos($digits, '0088') === 0) {
            $digits = substr($digits, 4);
        }
        // Strip leading 88
        elseif (strpos($digits, '88') === 0) {
            $digits = substr($digits, 2);
        }

        // If 10 digits starting with 1 (e.g. 1712345678), add leading 0
        if (strlen($digits) === 10 && strpos($digits, '1') === 0) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}