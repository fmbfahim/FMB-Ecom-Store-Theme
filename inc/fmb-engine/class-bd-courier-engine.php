<?php
/**
 * FMB BD Courier Engine
 * Integrates BD Courier Check API (bdcourier.com) & Steadfast, Pathao, RedX, Paperfly.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_BD_Courier_Engine {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_fmb_check_courier_history', array($this, 'ajax_check_courier_history'));
        add_action('wp_ajax_nopriv_fmb_check_courier_history', array($this, 'ajax_check_courier_history'));

        // Register Webhook Endpoint for Couriers
        add_action('init', array($this, 'register_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));

        // Admin Metabox for Order Management
        add_action('add_meta_boxes', array($this, 'add_courier_metabox'));
        add_action('wp_ajax_fmb_send_to_courier', array($this, 'ajax_send_to_courier'));
    }

    /**
     * Check customer parcel success rate via BD Courier Check API (bdcourier.com)
     */
    public static function get_customer_history($phone_number, $only_cache = false) {
        $phone = preg_replace('/[^\d]/', '', $phone_number);
        if (strlen($phone) < 11) {
            return null;
        }

        $transient_key = 'fmb_bdc_cache_' . md5($phone);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return is_array($cached) ? $cached : json_decode($cached, true);
        }

        if ($only_cache) return null;

        $api_key = get_option('fmb_bd_courier_api_key', '');
        if (empty($api_key)) {
            return array(
                'total_order'     => 0,
                'total_success'   => 0,
                'total_cancel'    => 0,
                'success_percent' => 100,
                'note'            => 'API key missing'
            );
        }

        $response = wp_remote_post('https://api.bdcourier.com/courier-check', array(
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ),
            'body'      => wp_json_encode(array('phone' => $phone))
        ));

        if (is_wp_error($response)) {
            return array('error' => true, 'message' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $payload = isset($data['data']) ? $data['data'] : (isset($data['response']) ? $data['response'] : $data);
        $summary = isset($payload['summary']) ? $payload['summary'] : $payload;

        $total   = (int) ($summary['total_orders'] ?? ($summary['total_parcel'] ?? ($summary['total'] ?? 0)));
        $success = (int) ($summary['total_success'] ?? ($summary['success_parcel'] ?? ($summary['success'] ?? 0)));
        $cancel  = (int) ($summary['total_returns'] ?? ($summary['cancelled_parcel'] ?? ($summary['cancel'] ?? 0)));

        $ratio = ($total > 0) ? round(($success / $total) * 100, 2) : 100;

        $result = array(
            'total_order'     => $total,
            'total_success'   => $success,
            'total_cancel'    => $cancel,
            'success_percent' => $ratio,
        );

        set_transient($transient_key, $result, 24 * HOUR_IN_SECONDS);
        return $result;
    }

    public function ajax_check_courier_history() {
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $data = self::get_customer_history($phone);
        wp_send_json_success($data);
    }

    /**
     * Send Order to Steadfast Courier
     */
    public static function send_to_steadfast($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return array('error' => true, 'message' => 'Invalid order');

        $existing_cid = $order->get_meta('_steadfast_consignment_id') 
                     ?: ($order->get_meta('_courier_consignment_id') 
                     ?: ($order->get_meta('_fmb_consignment_id') 
                     ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
        if (!empty($existing_cid)) {
            return array('error' => true, 'message' => sprintf('Order #%s is already booked (Consignment ID: %s). An order cannot be booked more than once.', $order->get_order_number(), $existing_cid));
        }

        $api_key = get_option('fmb_steadfast_api_key', '');
        $secret_key = get_option('fmb_steadfast_secret_key', '');
        
        if (empty($api_key) || empty($secret_key)) {
            $api_key = get_option('steadfast_api_key', '');
            $secret_key = get_option('steadfast_secret_key', '');
        }

        if (empty($api_key) || empty($secret_key)) {
            return array('error' => true, 'message' => 'Steadfast API Key / Secret Missing');
        }

        $body = array(
            'invoice'           => (string) $order->get_order_number(),
            'recipient_name'    => $order->get_formatted_billing_full_name(),
            'recipient_phone'   => preg_replace('/[^\d]/', '', $order->get_billing_phone()),
            'recipient_address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'cod_amount'        => (float) $order->get_total(),
            'note'              => $order->get_customer_note()
        );

        $response = wp_remote_post('https://portal.steadfast.com.bd/api/v1/create_order', array(
            'timeout'   => 20,
            'headers'   => array(
                'Api-Key'      => $api_key,
                'Secret-Key'   => $secret_key,
                'Content-Type' => 'application/json'
            ),
            'body'      => wp_json_encode($body)
        ));

        if (is_wp_error($response)) {
            return array('error' => true, 'message' => $response->get_error_message());
        }

        $res_data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($res_data['status']) && $res_data['status'] === 200 && isset($res_data['consignment'])) {
            $tracking_code = $res_data['consignment']['tracking_code'];
            update_post_meta($order_id, '_fmb_courier_provider', 'steadfast');
            update_post_meta($order_id, '_fmb_tracking_code', $tracking_code);
            $order->add_order_note('Sent to Steadfast Courier. Tracking: ' . $tracking_code);
            return array('success' => true, 'tracking_code' => $tracking_code);
        }

        return array('error' => true, 'message' => isset($res_data['message']) ? $res_data['message'] : 'Steadfast booking failed');
    }

    /**
     * Webhook handling for Couriers
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule('^fmb-courier-webhook/?', 'index.php?fmb_courier_webhook=1', 'top');
        add_rewrite_tag('%fmb_courier_webhook%', '([^&]+)');
    }

    public function handle_webhook_request() {
        global $wp_query;
        if (isset($wp_query->query_vars['fmb_courier_webhook']) || isset($_GET['fmb_courier_webhook'])) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (isset($data['invoice']) && isset($data['status'])) {
                $order_id = wc_get_order_id_by_order_number($data['invoice']);
                if ($order_id) {
                    $order = wc_get_order($order_id);
                    $status = strtolower($data['status']);
                    if (in_array($status, array('delivered', 'completed'))) {
                        $order->update_status('completed', 'Courier Webhook: Order Delivered');
                    } elseif (in_array($status, array('cancelled', 'returned'))) {
                        $order->update_status('failed', 'Courier Webhook: Order Returned/Cancelled');
                    }
                }
            }
            wp_send_json_success(array('status' => 'received'));
            exit;
        }
    }

    /**
     * Admin Order Metabox
     */
    public function add_courier_metabox() {
        add_meta_box(
            'fmb_courier_details',
            'FMB Engine - BD Courier & Success Rate',
            array($this, 'render_courier_metabox'),
            'shop_order',
            'side',
            'high'
        );
    }

    public function render_courier_metabox($post) {
        $order_id = $post->ID;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $history = self::get_customer_history($phone);
        $tracking = get_post_meta($order_id, '_fmb_tracking_code', true);
        $provider = get_post_meta($order_id, '_fmb_courier_provider', true);

        ?>
        <div style="font-size:13px; line-height:1.6;">
            <div style="background:#f3f4f6; padding:10px; border-radius:6px; margin-bottom:12px;">
                <strong>📊 BDCourier.com Delivery History</strong><br>
                <?php if ($history && !isset($history['error'])): ?>
                    <span style="color:#10b981; font-weight:bold;">Success Rate: <?php echo esc_html($history['success_percent']); ?>%</span><br>
                    <span>Total Orders: <?php echo esc_html($history['total_order']); ?></span> | 
                    <span>Delivered: <?php echo esc_html($history['total_success']); ?></span> | 
                    <span style="color:#ef4444;">Returned: <?php echo esc_html($history['total_cancel']); ?></span>
                <?php else: ?>
                    <span style="color:#6b7280;">API key not set or history unavailable.</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($tracking)): ?>
                <p><strong>Courier:</strong> <?php echo strtoupper(esc_html($provider)); ?></p>
                <p><strong>Tracking Code:</strong> <code><?php echo esc_html($tracking); ?></code></p>
            <?php else: ?>
                <button type="button" class="button button-primary fmb-send-steadfast" data-orderid="<?php echo esc_attr($order_id); ?>">Send to Steadfast</button>
            <?php endif; ?>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.fmb-send-steadfast').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Sending...');
                $.post(ajaxurl, { action: 'fmb_send_to_courier', order_id: btn.data('orderid') }, function(res) {
                    if (res.success) {
                        alert('Order sent to Steadfast! Tracking: ' + res.data.tracking_code);
                        location.reload();
                    } else {
                        alert('Failed: ' + (res.data ? res.data.message : 'Error'));
                        btn.prop('disabled', false).text('Send to Steadfast');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_send_to_courier() {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $result = self::send_to_steadfast($order_id);
        if (isset($result['success'])) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Change status to Shipping since it was successfully booked to courier
                $order->update_status('ads-shipping', 'Order successfully booked to Steadfast Courier.');
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
