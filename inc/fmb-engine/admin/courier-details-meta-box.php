<?php
namespace fmb_engine\Admin;
use fmb_engine\Core\Base;
use fmb_engine\Courier\Courier;
use fmb_engine\Traits\Core;

class Courier_Details_Meta_Box extends Base {
    use Core;

    public function __construct() {
        if ( ! self::is_license_active()  ) {
            return;
        }
        // Add meta boxes for both HPOS and classic order edit screens
        add_action('add_meta_boxes', [$this, 'add_courier_details_meta_box'],99999999999999999);
        // Handle AJAX request for refreshing courier data
        add_action('wp_ajax_ads_refresh_courier_details', [$this, 'refresh_courier_details']);
        // Output inline CSS and JS on admin pages
        add_action('admin_head', [$this, 'output_inline_css']);
        add_action('admin_footer', [$this, 'output_inline_js']);
    }

    /**
     * Add meta boxes for both HPOS and classic order edit screens
     */
    public function add_courier_details_meta_box() {
        $fraud_checker_enabled = get_option('ads_enable_fraud_checker', false);
        if ( ! $fraud_checker_enabled ) {
            return;
        }
        
        // For HPOS order edit screen
        add_meta_box(
            'ads_courier_details_meta_box',
            __('Courier Details', 'FMB Engine'),
            [$this, 'render_courier_details_meta_box'],
            'woocommerce_page_wc-orders', // HPOS orders page hook
            'side',
            'high'
        );

        // For classic order edit screen
        add_meta_box(
            'ads_courier_details_meta_box',
            __('Courier Details', 'FMB Engine'),
            [$this, 'render_courier_details_meta_box'],
            'shop_order', // Classic order post type
            'side',
            'high'
        );
    }

    /**
     * Render the courier details meta box content
     */
    public function render_courier_details_meta_box($post_or_order_object) {
        // Get order object depending on context
        if (is_a($post_or_order_object, 'WP_Post')) {
            $order = wc_get_order($post_or_order_object->ID);
        } else {
            $order = $post_or_order_object;
        }

        if (!$order) {
            return;
        }

        $order_id = $order->get_id();
        $phone_number = $this->normalize_phone_number($order->get_billing_phone());

        // Get courier history strictly from cache (no API calls during page load!)
        $courier_history = Courier::get_courier_history_from_cache($phone_number);
        if (is_string($courier_history)) {
            $courier_history = json_decode($courier_history, true);
        }
        if (!is_array($courier_history)) {
            $courier_history = [];
        }

        ?>
        <div id="ads-courier-details-wrapper" data-order-id="<?php echo esc_attr($order_id); ?>">
            <div class="ads-meta-box-inside">
                <?php 
                if ($courier_history) {
                    $totals = $this->calculate_courier_totals($courier_history);
                    echo $this->render_courier_ui_html($phone_number, $totals, $courier_history);
                }
                ?>
            </div>
            
            <button class="ads-refresh-courier-data">
                <?php if ($courier_history) : ?>
                    <span class="dashicons dashicons-update"></span>
                    <span class="ads-button-text">Recheck</span>
                <?php else : ?>
                    <span class="dashicons dashicons-search"></span>
                    <span class="ads-button-text">Check Courier Data</span>
                <?php endif; ?>
            </button>
        </div>
        <?php
    }

    /**
     * Helper to render the Courier Breakdown UI content cleanly
     */
    private function render_courier_ui_html($phone_number, $totals, $courier_history) {
        $couriers = $this->get_supported_couriers();
        $score = round($totals['success_percent']);
        
        $status_label = 'Danger';
        $status_color = '#e53e3e';
        $status_bg = '#fbe8e8'; // light red
        if ($score >= 70) {
            $status_label = 'Safe';
            $status_color = '#00a669'; // Green from image
            $status_bg = '#e6f6ef'; // light green
        } else if ($score >= 40) {
            $status_label = 'Warning';
            $status_color = '#dd6b20'; // Orange
            $status_bg = '#fcebd9';
        }

        // 194.7 is roughly the circumference of drawing a circle with r=31
        $dashoffset = 194.7 - (194.7 * $score) / 100;
        
        ob_start();
        ?>
        <div class="ads-courier-top-status">
            <div class="ads-status-circle">
                <svg>
                    <circle cx="35" cy="35" r="31" stroke="#f0f0f0"></circle>
                    <circle cx="35" cy="35" r="31" stroke="<?php echo esc_attr($status_color); ?>" style="stroke-dasharray: 194.7; stroke-dashoffset: <?php echo esc_attr($dashoffset); ?>;"></circle>
                </svg>
                <div class="ads-circle-score" style="color: <?php echo esc_attr($status_color); ?>;">
                    <?php echo esc_html($score); ?>
                </div>
            </div>
            <div class="ads-status-info">
                <div class="ads-status-badge" style="color: <?php echo esc_attr($status_color); ?>; background-color: <?php echo esc_attr($status_bg); ?>;">
                    <?php echo esc_html($status_label); ?>
                </div>
                <div class="ads-status-phone">
                    <span class="dashicons dashicons-phone"></span> <?php echo esc_html($phone_number); ?>
                </div>
            </div>
        </div>

        <div class="ads-courier-stats-row">
            <div class="ads-stat-card">
                <span class="ads-card-value"><?php echo esc_html($totals['total']); ?></span>
                <span class="ads-card-label">Total</span>
            </div>
            <div class="ads-stat-card">
                <span class="ads-card-value ads-text-success"><?php echo esc_html($totals['success']); ?></span>
                <span class="ads-card-label">Success</span>
            </div>
            <div class="ads-stat-card line-left">
                <span class="ads-card-value ads-text-danger"><?php echo esc_html($totals['cancel']); ?></span>
                <span class="ads-card-label">Cancel</span>
            </div>
        </div>
        <div class="ads-courier-table-wrap">
            <table class="ads-breakdown-table">
                <thead>
                <tr>
                    <th style="text-align: left;">Couriers</th>
                    <th>Total</th>
                    <th style="color: #00a669;">✓</th>
                    <th style="color: #e53e3e;">✕</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($couriers as $courier_key => $courier_name) : 
                    $stats = $this->extract_courier_data_robust($courier_history, $courier_key);
                ?>
                    <tr>
                        <td style="text-align: left; font-weight: 600; color: #2d3748; font-size: 14px;"><?php echo esc_html($courier_name); ?></td>
                        <td style="font-size: 14px;"><?php echo esc_html($stats['total']); ?></td>
                        <td style="font-weight: 700; color: #00a669; font-size: 14px;"><?php echo esc_html($stats['success']); ?></td>
                        <td style="font-weight: 700; color: #e53e3e; font-size: 14px;"><?php echo esc_html($stats['cancel']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate totals from courier history
     */
    private function calculate_courier_totals($courier_history) {
        if (is_string($courier_history)) {
            $courier_history = json_decode($courier_history, true);
        }
        if (!is_array($courier_history)) {
            return [
                'total' => 0,
                'success' => 0,
                'cancel' => 0,
                'success_percent' => 0
            ];
        }

        $couriers = $this->get_supported_couriers();
        $total_orders = 0;
        $total_success = 0;
        $total_cancel = 0;

        foreach ($couriers as $courier_key => $courier_name) {
            $stats = $this->extract_courier_data_robust($courier_history, $courier_key);
            $total_orders += $stats['total'];
            $total_success += $stats['success'];
            $total_cancel += $stats['cancel'];
        }

        // Fall back to root-level totals if individual courier breakdown is empty
        if ($total_orders === 0) {
            $root_total = intval($courier_history['total_order'] ?? ($courier_history['total_orders'] ?? 0));
            $root_success = intval($courier_history['total_success'] ?? 0);
            $root_cancel = intval($courier_history['total_cancel'] ?? ($courier_history['total_returns'] ?? 0));
            
            if ($root_total > 0) {
                $total_orders = $root_total;
                $total_success = $root_success;
                $total_cancel = $root_cancel;
            }
        }

        $success_percent = ($total_orders > 0) ? round(($total_success / $total_orders) * 100, 1) : 0;

        // Fall back to root-level success_percent/courier_ratio if it exists
        if ($success_percent == 0 && isset($courier_history['success_percent']) && floatval($courier_history['success_percent']) > 0) {
            $success_percent = floatval($courier_history['success_percent']);
        }

        return [
            'total' => $total_orders,
            'success' => $total_success,
            'cancel' => $total_cancel,
            'success_percent' => $success_percent
        ];
    }

    /**
     * Get list of required couriers to display
     */
    private function get_supported_couriers() {
        return [
            'pathao'    => 'Pathao',
            'steadfast' => 'Steadfast',
            'redx'      => 'Redx',
            'carrybee'  => 'Carrybee',
            'paperfly'  => 'Paperfly',
            'parceldex' => 'Parceldex'
        ];
    }

    /**
     * Robustly extract courier data avoiding case-sensitivity or structure mismatches
     */
    private function extract_courier_data_robust($courier_history, $courier_key) {
        $c_data = null;
        
        $base_data_arrays = [];
        if (isset($courier_history['response']) && is_array($courier_history['response'])) {
            $base_data_arrays[] = $courier_history['response'];
        }
        if (isset($courier_history['data']) && is_array($courier_history['data'])) {
            $base_data_arrays[] = $courier_history['data'];
        }
        if (is_array($courier_history)) {
            $base_data_arrays[] = $courier_history;
        }

        foreach ($base_data_arrays as $base) {
            if (isset($base[$courier_key])) {
                $c_data = $base[$courier_key];
                break;
            } else {
                foreach ($base as $ck => $cv) {
                    if (strcasecmp((string)$ck, $courier_key) === 0) {
                        $c_data = $cv;
                        break;
                    }
                }
            }
            if ($c_data !== null) break;
        }

        $c_inner = isset($c_data['data']) && is_array($c_data['data']) ? $c_data['data'] : (is_array($c_data) ? $c_data : []);
        $total = intval($c_inner['total_parcel'] ?? ($c_inner['total'] ?? 0));
        $success = intval($c_inner['success_parcel'] ?? ($c_inner['success'] ?? 0));
        $cancel = intval($c_inner['cancel_parcel'] ?? ($c_inner['canceled'] ?? ($c_inner['cancelled'] ?? ($c_inner['cancel'] ?? ($c_inner['return_parcel'] ?? ($c_inner['return'] ?? 0))))));

        // Extension equivalence math: if the API router payload optimizes by stripping 'cancel', deduce it automatically!
        if ($cancel === 0 && $total > 0 && $total >= $success) {
            $cancel = $total - $success;
        }

        return [
            'total'   => $total,
            'success' => $success,
            'cancel'  => $cancel,
        ];
    }

    /**
     * Handle AJAX request for refreshing courier data
     */
    public function refresh_courier_details() {
        check_ajax_referer('ads_refresh_courier_details_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $phone_number = $this->normalize_phone_number($order->get_billing_phone());

        if (!$phone_number || strlen($phone_number) != 11) {
            wp_send_json_error(['message' => 'Invalid phone number']);
        }

        // Force refresh courier history
        $courier_history = Courier::fetch_courier_history_from_apis($phone_number);

        // Safeguard to ensure live API data is structured identically to DB array retrieval natively
        if (!empty($courier_history)) {
            $courier_history = json_decode(json_encode($courier_history), true);
        }

        if (empty($courier_history) || (isset($courier_history['error']) && $courier_history['error'] === true)) {
            $error_msg = isset($courier_history['message']) && !empty($courier_history['message']) ? $courier_history['message'] : 'Failed to fetch active courier data or API limit reached.';
            wp_send_json_error(['message' => $error_msg]);
        }

        // Set the unified transient to guarantee frontend sync with the fresh data
        set_transient(
            'fmb_engine_courier_' . md5($phone_number),
            json_encode($courier_history),
            24 * HOUR_IN_SECONDS
        );

        // Calculate totals
        $totals = $this->calculate_courier_totals($courier_history);

        // Generate HTML
        $html = $this->render_courier_ui_html($phone_number, $totals, $courier_history);

        wp_send_json_success([
            'html' => $html,
            'totals' => $totals
        ]);
    }

    /**
     * Output inline CSS for the meta box
     */
    public function output_inline_css() {
        // Only load on order edit pages
        $screen = get_current_screen();
        if (
            !$screen ||
            ($screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders')
        ) {
            return;
        }
        ?>
        <style>
            #ads-courier-details-wrapper {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: #2d3748;
            }

            .ads-courier-top-status {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
                margin-top: 15px;
                border: 1px solid #067a6536;
                border-radius: 10px;
                padding: 5px;
            }

            .ads-status-circle {
                position: relative;
                width: 70px;
                height: 70px;
            }

            .ads-status-circle svg {
                width: 70px;
                height: 70px;
                transform: rotate(-90deg);
            }

            .ads-status-circle svg circle {
                fill: none;
                stroke-width: 6;
                stroke-linecap: round;
            }

            .ads-circle-score {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 20px;
                font-weight: 800;
            }

            .ads-status-info {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .ads-status-badge {
                display: inline-block;
                padding: 4px 15px;
                border-radius: 20px;
                font-weight: 700;
                font-size: 14px;
                align-self: flex-start;
            }

            .ads-status-phone {
                display: flex;
                align-items: center;
                gap: 5px;
                color: #4a5568;
                font-size: 15px;
                font-weight: 500;
            }

            .ads-status-phone .dashicons-phone {
                font-size: 18px;
                width: 18px;
                height: 18px;
                color: #718096;
            }

            .ads-courier-stats-row {
                display: flex;
                justify-content: space-between;
                border-radius: 10px;
                border: 1px solid #edf2f7;
                background: #fff;
                margin-bottom: 12px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }

            .ads-stat-card {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 15px 5px;
                position: relative;
            }

            .ads-stat-card:not(:last-child)::after {
                content: '';
                position: absolute;
                right: 0;
                top: 20%;
                height: 60%;
                width: 1px;
                background: #edf2f7;
            }

            .ads-card-value {
                font-size: 18px;
                font-weight: 800;
                color: #1a202c;
                line-height: 1.2;
            }

            .ads-card-label {
                font-size: 13px;
                color: #718096;
                margin-top: 4px;
                font-weight: 500;
            }

            .ads-text-success { color: #00a669; }
            .ads-text-danger { color: #e53e3e; } 
            .ads-courier-table-wrap {
                background: #f8fafc;
                border-radius: 10px;
                padding: 5px;
                margin-bottom: 10px;
            }

            .ads-breakdown-table {
                width: 100%;
                border-collapse: collapse;
                text-align: center;
                font-size: 14px;
            }

            .ads-breakdown-table th {
                color: #000;
                font-size: 14px;
                font-weight: 600;
                padding: 8px 10px;
                border-bottom: 1px solid #edf2f7;
            }

            .ads-breakdown-table td {
                padding: 10px;
                color: #2d3748;
                border-bottom: 1px solid #edf2f7;
                font-size: 14px;
            }

            .ads-breakdown-table tbody tr:last-child td {
                border-bottom: none;
            }

            .ads-refresh-courier-data {
                width: 100%;
                background: #197278;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 8px 14px;
                font-weight: 600;
                font-size: 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: background 0.3s;
                margin-top: 5px;
            }

            .ads-refresh-courier-data:hover {
                background: #145a5f;
            }

            .ads-refresh-courier-data:disabled {
                background: #718096;
                cursor: not-allowed;
            }

            .dashicons.spinning {
                animation: rotation 2s infinite linear;
            }
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(359deg); }
            }
            
            /* Notices inside meta box wrapper */
            .ads-courier-notice {
                margin-bottom: 15px;
                padding: 12px 15px;
                border-radius: 6px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 14px;
            }
            .ads-courier-notice.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .ads-courier-notice.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            /* Meta box header styling */
            #ads_courier_details_meta_box .postbox-header,
            #ads_courier_details_meta_box h2.hndle,
            #ads_courier_details_meta_box h3.hndle {
                background-color: #197278 !important;
                color: #ffffff !important;
                border-bottom: none !important;
                margin: 0;
            }
            #ads_courier_details_meta_box .postbox-header h2,
            #ads_courier_details_meta_box .postbox-header .hndle,
            #ads_courier_details_meta_box h2.hndle span,
            #ads_courier_details_meta_box h3.hndle span {
                color: #ffffff !important;
            }
            #ads_courier_details_meta_box .postbox-header button,
            #ads_courier_details_meta_box .postbox-header .toggle-indicator::before,
            #ads_courier_details_meta_box .handlediv .toggle-indicator::before {
                color: #ffffff !important;
            }
            #ads_courier_details_meta_box .handlediv {
                color: #ffffff !important;
            }
        </style>
        <?php
    }

    /**
     * Output inline JavaScript for the meta box
     */
    public function output_inline_js() {
        // Only load on order edit pages
        $screen = get_current_screen();
        if (
            !$screen ||
            ($screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders')
        ) {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.ads-refresh-courier-data').on('click', function(e) {
                    e.preventDefault();

                    var $button = $(this);
                    var $wrapper = $('#ads-courier-details-wrapper');
                    var orderId = $wrapper.data('order-id');

                    // Show loading state
                    $button.prop('disabled', true);
                    $button.find('.dashicons').addClass('spinning');

                    // Remove any existing notices
                    $('.ads-courier-notice').remove();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'ads_refresh_courier_details',
                            nonce: '<?php echo wp_create_nonce('ads_refresh_courier_details_nonce'); ?>',
                            order_id: orderId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update the content cleanly
                                $wrapper.find('.ads-meta-box-inside').html(response.data.html);

                                // Reset button to Recheck state if it was the initial "Check" workflow
                                $button.find('.dashicons').removeClass('dashicons-search spinning').addClass('dashicons-update');
                                $button.find('.ads-button-text').text('Recheck');
                                $button.prop('disabled', false);

                                // Show success message
                                var $message = $('<div class="ads-courier-notice success"><span class="dashicons dashicons-yes"></span> Courier data refreshed successfully!</div>');
                                $wrapper.prepend($message);

                                // Auto dismiss after 3 seconds
                                setTimeout(function() {
                                    $message.fadeOut(function() {
                                        $(this).remove();
                                    });
                                }, 3000);
                            } else {
                                // Show error message
                                var $message = $('<div class="ads-courier-notice error"><span class="dashicons dashicons-no"></span> Error: ' + response.data.message + '</div>');
                                $wrapper.prepend($message);
                            }
                        },
                        error: function() {
                            // Show error message
                            var $message = $('<div class="ads-courier-notice error"><span class="dashicons dashicons-no"></span> An error occurred while refreshing courier data.</div>');
                            $wrapper.prepend($message);
                        },
                        complete: function() {
                            // Reset button state
                            $button.prop('disabled', false);
                            $button.find('.dashicons').removeClass('spinning');
                        }
                    });
                });
            });
        </script>
        <?php
    }
}