<?php
namespace fmb_engine\Traits;
use WC_Geolocation;

trait Order_Check_Trait {
    public static function prepare_order_query_args($customer_data_type = null, $customer_data_value = null, $order_status = null, $order_id = null) {
        $session_time = (int) get_option('ads_order_autosave_session_time', 5);
        $args = [
            'date_created' => '>=' . (time() - (60 * $session_time)),
            'limit'        => -1,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'paginate'     => true,
        ];

        if ($customer_data_type && $customer_data_value) {
            $args[$customer_data_type] = $customer_data_value;
        }
        if ($order_status) {
            $args['status'] = $order_status;
        }
        if ($order_id) {
            $args['id'] = $order_id;
        }

        return $args;
    }

    public static function validate_existing_orders_by_identifiers() {
        $identifiers = self::get_sanitized_identifiers();
        $excluded_statuses = ['wc-ads-incomplete', 'wc-checkout-draft'];
        $allowed_statuses = array_diff(array_keys(wc_get_order_statuses()), $excluded_statuses);

        if (self::has_existing_orders($identifiers, $allowed_statuses)) {
            wp_send_json_error('Order already made and autosave is disabled temporarily.');
            wp_die();
        }
    }

    public static function find_autosaved_order_by_identifiers() {
        $identifiers = self::get_sanitized_identifiers();
        return self::find_autosaved_order($identifiers);
    }

    private static function get_sanitized_identifiers() {
        return [
            'billing_phone' => isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '',
            'billing_email' => isset($_POST['billing_email']) ? sanitize_text_field($_POST['billing_email']) : '',
            'ip_address'    => WC_Geolocation::get_ip_address(),
        ];
    }

    private static function has_existing_orders(array $identifiers, array $statuses) {
        foreach ($identifiers as $type => $value) {
            if (empty($value)) continue;

            $args = self::prepare_order_query_args($type, $value, $statuses);
            $orders = wc_get_orders($args);

            if ($orders && $orders->total > 0) {
                return true;
            }
        }
        return false;
    }

    private static function find_autosaved_order(array $identifiers) {
        foreach ($identifiers as $type => $value) {
            if (empty($value)) continue;

            $args = self::prepare_order_query_args($type, $value, 'wc-ads-incomplete');
            $orders = wc_get_orders($args);

            if ($orders && !empty($orders->orders)) {
                return $orders->orders[0];
            }
        }
        return null;
    }
}