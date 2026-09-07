<?php
/**
 * FMB Courier Rate & Partial Payment Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Partial_Payment {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $enabled = get_option('fmb_enable_partial_payment', false);
        if ($enabled) {
            add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
            add_action('woocommerce_review_order_after_order_total', array($this, 'display_partial_breakdown'));
            add_action('woocommerce_checkout_order_processed', array($this, 'save_partial_payment_meta'), 10, 1);
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_partial_info'));
        }
    }

    /**
     * Filter Payment Gateways if Partial Payment is required for low success rate users
     */
    public function filter_payment_gateways($gateways) {
        if (is_admin() || !is_checkout()) return $gateways;

        $phone = isset($_POST['post_data']) ? $this->extract_phone_from_post_data($_POST['post_data']) : (isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '');
        if (empty($phone)) return $gateways;

        $history = FMB_BD_Courier_Engine::get_customer_history($phone);
        $threshold = (int) get_option('fmb_courier_rate_threshold', 70);

        if ($history && isset($history['success_percent']) && $history['success_percent'] < $threshold) {
            // Require advance payment - remove Cash on Delivery
            if (isset($gateways['cod'])) {
                unset($gateways['cod']);
            }
        }

        return $gateways;
    }

    private function extract_phone_from_post_data($post_data) {
        parse_str($post_data, $output);
        return isset($output['billing_phone']) ? sanitize_text_field($output['billing_phone']) : '';
    }

    public function display_partial_breakdown() {
        $amount = (float) get_option('fmb_partial_payment_amount', 100);
        $cart_total = (float) WC()->cart->get_total('edit');
        $due = max(0, $cart_total - $amount);
        ?>
        <tr class="fmb-partial-payment-breakdown" style="background:#fef3c7; font-weight:600;">
            <th><?php _e('অগ্রিম প্রদেয় (পারশিয়াল পেমেন্ট):', 'fmb-store'); ?></th>
            <td><?php echo wc_price($amount); ?></td>
        </tr>
        <tr class="fmb-due-payment-breakdown" style="background:#ecfdf5; font-weight:600;">
            <th><?php _e('বাকি ক্যাশ অন ডেলিভারি:', 'fmb-store'); ?></th>
            <td><?php echo wc_price($due); ?></td>
        </tr>
        <?php
    }

    public function save_partial_payment_meta($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $history = FMB_BD_Courier_Engine::get_customer_history($phone);
        $threshold = (int) get_option('fmb_courier_rate_threshold', 70);

        if ($history && isset($history['success_percent']) && $history['success_percent'] < $threshold) {
            $partial_amount = (float) get_option('fmb_partial_payment_amount', 100);
            $total = (float) $order->get_total();
            $due = max(0, $total - $partial_amount);

            update_post_meta($order_id, '_fmb_is_partial_paid', '1');
            update_post_meta($order_id, '_fmb_partial_amount', $partial_amount);
            update_post_meta($order_id, '_fmb_due_amount', $due);
            $order->update_status('wc-partial-paid', 'Partial payment required due to low courier success rate.');
        }
    }

    public function display_admin_partial_info($order) {
        $is_partial = get_post_meta($order->get_id(), '_fmb_is_partial_paid', true);
        if ($is_partial) {
            $advance = get_post_meta($order->get_id(), '_fmb_partial_amount', true);
            $due = get_post_meta($order->get_id(), '_fmb_due_amount', true);
            echo '<p style="color:#d97706; font-weight:bold; margin-top:10px;">⚠️ Partial Payment Order:<br>Advance Required: ' . wc_price($advance) . '<br>Due on COD: ' . wc_price($due) . '</p>';
        }
    }
}
