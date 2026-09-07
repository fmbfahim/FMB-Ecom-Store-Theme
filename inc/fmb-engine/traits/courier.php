<?php
namespace fmb_engine\Traits;

trait  Courier {

    public function get_courier_rate_by_phone($phone) {
        $stats = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone);

        if (is_string($stats)) {
            $stats = json_decode($stats, true);
        }

        if (!$stats || !is_array($stats)) {
            return null; // API failed or completely invalid phone
        }

        $total_order = intval($stats['total_order'] ?? ($stats['total_orders'] ?? 0));
        $success_percent = floatval($stats['success_percent'] ?? ($stats['courier_ratio'] ?? 0));

        // Distinct case: New customer with absolutely no history
        if ($total_order === 0) {
            return -1; // -1 represents "New Customer/Blank History"
        }

        return $success_percent; // 0-100 real success rate
    }
}