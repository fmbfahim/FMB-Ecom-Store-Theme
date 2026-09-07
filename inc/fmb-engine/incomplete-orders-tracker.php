<?php
namespace fmb_engine;

class Incomplete_Orders_Tracker {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
    }

    public function get_analytics($start_date, $end_date) {
        global $wpdb;

        $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $join = $hpos_enabled 
            ? "INNER JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id" 
            : "INNER JOIN {$wpdb->posts} p ON t.order_id = p.ID AND p.post_type = 'shop_order'";

        // Get incomplete orders created in date range
        $created_query = $wpdb->prepare("
            SELECT COUNT(id) 
            FROM $this->table_name
            WHERE created_at BETWEEN %s AND %s
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
        $created = $wpdb->get_var($created_query);

        // Get recovered orders that were recovered in date range
        $recovered = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(t.id) 
            FROM $this->table_name t
            $join
            WHERE t.recovered_at BETWEEN %s AND %s
            AND t.status_to = 'ads-recovered' 
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'));

        // Get recovery amount for orders recovered in date range
        $recovery_amount = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(t.order_total) 
            FROM $this->table_name t
            $join
            WHERE t.recovered_at BETWEEN %s AND %s
            AND t.status_to = 'ads-recovered' 
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'));

        // Get top recovery days for orders recovered in date range
        $top_days = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(t.recovered_at) as recovery_date, 
                   COUNT(t.id) as recovered_carts,
                   SUM(t.order_total) as recovered_revenue
            FROM $this->table_name t
            $join
            WHERE t.recovered_at BETWEEN %s AND %s
            AND t.status_to = 'ads-recovered'
            AND t.recovered_at IS NOT NULL
            GROUP BY DATE(t.recovered_at)
            ORDER BY recovered_carts DESC, recovered_revenue DESC
            LIMIT 10
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'));

        return [
            'created' => $created,
            'recovered' => $recovered,
            'recovery_rate' => $created > 0 ? round(($recovered / $created) * 100, 2) : 0,
            'recovery_amount' => $recovery_amount ?: 0,
            'top_days' => $top_days
        ];
    }
}