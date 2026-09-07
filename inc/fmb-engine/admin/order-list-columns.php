<?php
namespace fmb_engine\Admin;

use fmb_engine\Core\Base;
use fmb_engine\Courier\Courier;
use fmb_engine\Traits\Core;

class Order_List_Columns extends Base {
    use Core;

    /**
     * @var array<int, \WC_Order|false|null> Per-request cache: order ID => order object.
     */
    private static $order_list_order_cache = array();

    public function __construct() {
        if ( ! self::is_license_active()  ) {
            return;
        }
        // Add custom columns
        add_filter('manage_edit-shop_order_columns', [$this, 'add_columns'], 20);
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_columns'], 20);
        // Add content to custom columns
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_column_content'], 20, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_column_content'], 20, 2);

        // Add AJAX handler for refreshing courier history (Individual refresh is kept)
        add_action('wp_ajax_refresh_courier_history', [$this, 'refresh_courier_history']);

        // Add AJAX handler for popup insights
        add_action('wp_ajax_fetch_customer_insights', [$this, 'fetch_customer_insights']);

        // Add AJAX handler for popup count trigger
        add_action('wp_ajax_fetch_customer_order_count', [$this, 'fetch_customer_order_count']);

        // Add JavaScript for AJAX functionality
        add_action('admin_footer', [$this, 'add_courier_history_script']);
        add_action('admin_footer', [$this, 'add_courier_sync_script']);

        // Add Steadfast & Pathao AJAX handlers
        add_action('wp_ajax_fmb_engine_send_steadfast', [$this, 'send_to_steadfast_ajax']);
        add_action('wp_ajax_fmb_engine_send_pathao', [$this, 'send_to_pathao_ajax']);

        // Add Auto-Saver Handler for custom amounts
        add_action('wp_ajax_fmb_engine_save_courier_amount', [$this, 'save_courier_amount_ajax']);

        // Add handler for bulk order analysis before booking
        add_action('wp_ajax_fmb_engine_analyze_bulk_orders', [$this, 'analyze_bulk_orders_ajax']);

        // Add handler for merging orders and canceling orders
        add_action('wp_ajax_fmb_engine_merge_orders_ajax', [$this, 'merge_orders_ajax']);
        add_action('wp_ajax_fmb_engine_cancel_order_ajax', [$this, 'cancel_order_ajax']);

        // Add handler for single courier status refresh
        add_action('wp_ajax_refresh_single_courier_status', [$this, 'refresh_single_courier_status_ajax']);

        // Track who confirmed or cancelled the order
        add_action('woocommerce_order_status_changed', [$this, 'track_order_action_by'], 10, 3);
    }

    /**
     * Track who confirmed or cancelled the order
     */
    public function track_order_action_by($order_id, $old_status, $new_status) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user && $user->exists()) {
                $order = wc_get_order($order_id);
                if ($order) {
                    if ($new_status === 'cancelled') {
                        $action = 'Cancelled by: ' . $user->display_name;
                    } elseif (in_array($new_status, ['processing', 'completed'])) {
                        $action = 'Confirmed by: ' . $user->display_name;
                    } else {
                        $action = ucfirst($new_status) . ' by: ' . $user->display_name;
                    }
                    $order->update_meta_data('_fmb_action_by', $action);
                    $order->save_meta_data();
                }
            }
        }
    }

    /**
     * Add custom columns to WooCommerce order list
     */
    public function add_columns($columns) {
        $new_columns = [];
        $fraud_checker_enabled = get_option('ads_enable_fraud_checker', false);
        $inserted = false;

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'order_number') {
                $new_columns['ads_fb_status'] = __('CAPI Status', 'woocommerce');
            }
            if ($key === 'order_total' || $key === 'total') {
                $inserted = true;
                $steadfast_enabled = get_option('steadfast_enable', false);
                $pathao_enabled = get_option('pathao_enable', false);

                if ($steadfast_enabled || $pathao_enabled) {
                    $new_columns['ads_courier_consignment'] = __('ConsignmentID', 'woocommerce');
                    $new_columns['ads_courier_status'] = __('DeliveryStatus', 'woocommerce');
                    
                    $show_rider_update = ($steadfast_enabled && get_option('steadfast_enable_remarks_sync', false)) || 
                                         ($pathao_enabled && get_option('pathao_enable_remarks_sync', false));
                    if ($show_rider_update) {
                        $new_columns['ads_courier_update'] = __('Rider Update', 'woocommerce');
                    }
                }

                if( $fraud_checker_enabled ) {
                    $new_columns['ads_customer_courier_history'] = __('Fraud Checker', 'woocommerce');
                }
                $new_columns['ads_customer_access'] = __('Access', 'woocommerce');
                
                if (get_option('ads_enable_order_history', false)) {
                     $new_columns['ads_order_history_insights'] = __('Order History', 'woocommerce');
                }
                
                $new_columns['ads_customer_type'] = __('Customer Type', 'woocommerce');
                $new_columns['ads_order_action_by'] = __('Action By', 'woocommerce');
            }
        }

        if (!$inserted) {
            $new_columns['ads_customer_type'] = __('Customer Type', 'woocommerce');
            $new_columns['ads_order_action_by'] = __('Action By', 'woocommerce');
        }

        return $new_columns;
    }

    /**
     * Render content for custom columns
     */
    public function render_column_content($column, $order_or_post_id) {
        if ( is_a( $order_or_post_id, 'WC_Order' ) ) {
            $order = $order_or_post_id;
            self::$order_list_order_cache[ $order->get_id() ] = $order;
        } else {
            $oid = (int) $order_or_post_id;
            if ( ! array_key_exists( $oid, self::$order_list_order_cache ) ) {
                self::$order_list_order_cache[ $oid ] = wc_get_order( $oid );
            }
            $order = self::$order_list_order_cache[ $oid ];
        }
        if ( ! $order ) {
            return;
        }

        // Courier Integration Columns
        $active_courier = get_option('steadfast_enable', false) ? 'steadfast' : (get_option('pathao_enable', false) ? 'pathao' : '');

        if ($active_courier) {
            $meta_consignment = "_{$active_courier}_consignment_id";
            $meta_status = "_{$active_courier}_delivery_status";

            if ($column === 'ads_courier_consignment') {
                $consignment_id = $order->get_meta($meta_consignment);
                if ($consignment_id) {
                    echo '<span style="font-size:13px; font-weight:500; color:#334155;">' . esc_html($consignment_id) . '</span>';
                }
            }
            
            if ($column === 'ads_courier_status') {
                $status = $order->get_meta($meta_status);
                if ($status) {
                    $status_clean = ucwords(str_replace('_', ' ', $status));
                    $bg_color = '#f1f5f9';
                    $text_color = '#475569';
                    
                    switch (strtolower($status)) {
                        case 'delivered':
                        case 'success':
                            $bg_color = '#e6fffa'; $text_color = '#166534'; break;
                        case 'in_review':
                        case 'pending':
                            $bg_color = '#fffaf0'; $text_color = '#92400e'; break;
                        case 'cancelled':
                        case 'returned':
                        case 'failed':
                        case 'pickup cancel':
                        case 'pickup_cancel':
                            $bg_color = '#fff5f5'; $text_color = '#991b1b'; break;
                        case 'processing':
                        case 'shipped':
                        case 'in_transit':
                        case 'dispatched':
                            $bg_color = '#ebf8ff'; $text_color = '#1e40af'; break;
                        default:
                            $bg_color = '#f1f5f9'; $text_color = '#475569'; break;
                    }

                    $consignment_id = $order->get_meta($meta_consignment);
                    
                    echo '<div class="courier-status-wrapper" style="display:inline-flex; align-items:center; gap:6px;">';
                    echo '<span class="courier-status-badge" style="display:inline-block; padding:4px 10px; border-radius:6px; font-weight:600; font-size:13px; background:' . $bg_color . '; color:' . $text_color . ';">' . esc_html($status_clean) . '</span>';
                    
                    if ($consignment_id) {
                        $nonce_val = esc_attr(wp_create_nonce('refresh_single_courier_status'));
                        echo '<button type="button" class="refresh-single-courier-status" data-order-id="' . esc_attr($order->get_id()) . '" data-courier="' . esc_attr($active_courier) . '" data-consignment="' . esc_attr($consignment_id) . '" data-nonce="' . $nonce_val . '" title="Fetch Status" style="background:none; border:none; padding:0; cursor:pointer; color:#10b981; transition:color 0.2s; display:flex; align-items:center;">';
                        echo '<span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px;"></span>';
                        echo '</button>';
                    }
                    echo '</div>';
                }
            }
            
            if ($column === 'ads_courier_update') {
                $remark = $order->get_meta('_ofls_courier_latest_remark');
                if ($remark) {
                    echo '<span style="font-size:12px; color:#475569; display:block; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . esc_attr($remark) . '">';
                    echo '<span class="dashicons dashicons-admin-comments" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#8b5cf6;"></span> ';
                    echo esc_html($remark);
                    echo '</span>';
                }
            }
        }

        // FB Status column
        if ($column === 'ads_fb_status') {
            $order_id = $order->get_id();
            
            // Use WooCommerce meta API instead of get_post_meta for better compatibility
            // WooCommerce 3.0+ uses $order->get_meta(), older versions use get_post_meta()
            if (method_exists($order, 'get_meta')) {
                $results = $order->get_meta('ads_purchase_event_results', true);
                $tiktok_status = $order->get_meta('_tiktok_capi_status', true);
            } else {
                $results = get_post_meta($order_id, 'ads_purchase_event_results', true);
                $tiktok_status = get_post_meta($order_id, '_tiktok_capi_status', true);
            }
            
            $sent_count = 1;
            if (is_array($results) && !empty($results)) {
                // Check first result
                $first_result = $results[0];
                if (isset($first_result['event_name']) && $first_result['event_name'] === 'Purchase') {
                    if (isset($first_result['sent_count'])) {
                        $sent_count = $first_result['sent_count'];
                    }
                    echo '<span style="
                        display: inline-block;
                        background-color: #d4edda;
                        color: #155724;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-size: 11px;
                        margin-bottom: 2px;
                    ">Purchase * ' . esc_html($sent_count) . '</span><br>';
                }
            }

            if ($tiktok_status === 'success') {
                echo '<span style="
                    display: inline-block;
                    background-color: #d4edda;
                    color: #155724;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 11px;
                ">TikTok * 1</span>';
            }
        }

        // Customer Access column
            if ($column === 'ads_customer_access') {
                $phone = $this->normalize_phone_number($order->get_billing_phone());
                $device_hash = method_exists($order, 'get_meta') ? $order->get_meta('_ads_device_hash', true) : get_post_meta($order->get_id(), '_ads_device_hash', true);
                $phone_data = $this->fetch_customer_record('phone_number', $phone);
                $device_data = $device_hash ? $this->fetch_customer_record('device_hash', $device_hash) : null;
                $order_id_attr = esc_attr($order->get_id());

                if (!empty($phone)) {
                    $row = $phone_data;
                    $class = self::determine_customer_access_class($row);
                    $text = ($row && $row->data_access === 'blocked') ? 'Blocked' : 'Allowed';
                    echo '<span class="' . esc_attr($class) . '">' . esc_html($text) . '</span> ';
                }

                if ($phone && get_option('ads_block_phone_numbers')) {
                    echo '<span data-type="phone_number" data-value="' . esc_attr($phone) . '" data-order-id="' . $order_id_attr . '" class="of-action-btn-order ' . $this->determine_customer_access_class($phone_data) . '" title="Phone"><span class="dashicons dashicons-phone" style="line-height: inherit; vertical-align: middle;"></span></span>';
                }

                if ($device_hash && get_option('ads_block_device_ids')) {
                    echo '<span data-type="device_hash" data-value="' . esc_attr($device_hash) . '" data-order-id="' . $order_id_attr . '" class="of-action-btn-order ' . $this->determine_customer_access_class($device_data) . '" title="Device"><span class="dashicons dashicons-desktop" style="line-height: inherit; vertical-align: middle;"></span></span>';
                }
            }

            if ($column === 'ads_order_action_by') {
                $action_by = $order->get_meta('_fmb_action_by');
                if ($action_by) {
                    $color = strpos($action_by, 'Cancelled') !== false ? '#ef4444' : '#10b981';
                    echo '<span style="font-size:12px; font-weight:600; color:' . $color . '; display:inline-block; padding:2px 8px; background-color:' . $color . '15; border-radius:12px;">' . esc_html($action_by) . '</span>';
                } else {
                    echo '<span style="font-size:12px; color:#94a3b8;">-</span>';
                }
            }

            if ($column === 'ads_customer_type') {
                $phone = $this->normalize_phone_number($order->get_billing_phone());
                $ip = method_exists($order, 'get_customer_ip_address') ? $order->get_customer_ip_address() : $order->get_meta('_customer_ip_address');
                $seq_data = $this->get_customer_order_sequence($order->get_id(), $phone, $ip);
                
                if (!empty($seq_data['is_new'])) {
                    echo '<span style="font-size:12px; font-weight:600; color:#10b981; display:inline-block; padding:3px 10px; background-color:#dcfce7; border-radius:12px; border:1px solid #bbf7d0;">New Customer</span>';
                } else {
                    $order_num = $seq_data['order_number'] ?? ($seq_data['count'] + 1);
                    $suffix = 'th';
                    if ($order_num == 1) $suffix = 'st';
                    elseif ($order_num == 2) $suffix = 'nd';
                    elseif ($order_num == 3) $suffix = 'rd';

                    echo '<div style="font-size:12px; line-height: 1.4; display:inline-block; padding:4px 10px; background-color:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; text-align:left;">';
                    echo '<span style="font-weight:700; color:#2563eb; font-size:12px;">' . esc_html($order_num . $suffix . ' Order') . '</span> <span style="color:#64748b; font-size:11px;">(Prev: ' . esc_html($seq_data['count']) . ')</span><br>';
                    echo '<span style="color:#059669; font-weight:600; font-size:11px;">Confirmed: ' . esc_html($seq_data['confirmed']) . '</span> | ';
                    echo '<span style="color:#e11d48; font-weight:600; font-size:11px;">Cancelled: ' . esc_html($seq_data['cancelled']) . '</span>';
                    echo '</div>';
                }
            }
        
        // Courier History column - Placeholder for AJAX content
        $fraud_checker_enabled = get_option('ads_enable_fraud_checker', false);
        if ($fraud_checker_enabled && $column === 'ads_customer_courier_history') {
            $phone = $this->normalize_phone_number($order->get_billing_phone());

            if (!$phone || strlen($phone) != 11) {
                echo '<span>Invalid Phone</span>';
                return;
            }

            // Get courier history statistics (uses cache if available)
            $courier_history = null;
            if (class_exists('\fmb_engine\Courier\OFLS_BD_Courier_Engine')) {
                $courier_history = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone, true);
            } elseif (class_exists('FMB_BD_Courier_Engine')) {
                $courier_history = FMB_BD_Courier_Engine::get_customer_history($phone);
            }
            if (is_string($courier_history)) {
                $courier_history = json_decode($courier_history, true);
            }

            if(is_array($courier_history)) {
                // If cached data exists, display it immediately
                $html = $this->generate_courier_history_html($phone, $order_or_post_id, $courier_history);
                echo '<div class="courier-history-container loaded" data-phone="' . esc_attr($phone) . '" data-order-id="' . esc_attr($order->get_id()) . '">';
                echo $html;
                echo '</div>';
                return;
            }
            
            // Display initial "Check" button state
            echo '<div class="courier-history-container" data-phone="' . esc_attr($phone) . '" data-order-id="' . esc_attr($order->get_id()) . '" style="text-align: left;">';
            echo '<button class="refresh-courier-history ads-check-btn" data-phone="' . esc_attr($phone) . '" data-order-id="' . esc_attr($order->get_id()) . '"><span class="dashicons dashicons-search" style="font-size: 15px; width: 15px; height: 15px; display: flex; align-items: center; justify-content: center;"></span> Check</button>';
            echo '</div>';
        }

        // Order History Insights column
        if (get_option('ads_enable_order_history', false) && $column === 'ads_order_history_insights') {
            $phone = $this->normalize_phone_number($order->get_billing_phone());
            if ($phone && strlen($phone) >= 10) {
                 $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                 echo '<div class="ads-insight-container" data-phone="' . esc_attr($phone) . '" data-name="' . esc_attr($full_name) . '">';
                 echo '<button class="ads-insight-count-trigger" style="background: #197278; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; transition: background 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" onmouseover="this.style.background=\'#125458\'" onmouseout="this.style.background=\'#197278\'">';
                 echo 'Check History';
                 echo '</button>';
                 echo '</div>';
            } else {
                 echo '<span style="color: #a0aec0; font-size: 11px;">No Phone</span>';
            }
        }
    }

    /**
     * AJAX handler for refreshing courier history (Kept as is)
     */
    public function refresh_courier_history() {
        check_ajax_referer('refresh_courier_history_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$phone || strlen($phone) != 11 || !$order_id) {
            wp_send_json_error(['message' => 'Invalid request parameters']);
        }

        // Fetch via new 3-Tier Caching Engine (Transient -> Firebase -> BD Courier API)
        $courier_history_percent = null;
        if (class_exists('\fmb_engine\Courier\OFLS_BD_Courier_Engine')) {
            $courier_history_percent = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone);
        } elseif (class_exists('FMB_BD_Courier_Engine')) {
            $courier_history_percent = FMB_BD_Courier_Engine::get_customer_history($phone);
        }
        if (is_string($courier_history_percent)) {
            $courier_history_percent = json_decode($courier_history_percent, true);
        }

        if (!$courier_history_percent || !is_array($courier_history_percent)) {
            $courier_history_percent = [
                'total_order' => 0,
                'total_success' => 0,
                'total_cancel' => 0,
                'success_percent' => 0,
                'cancel_percent' => 0,
            ];
        }

        // Generate HTML response with updated data
        $response = [
            'success' => true,
            'html' => $this->generate_courier_history_html($phone, $order_id, $courier_history_percent)
        ];

        // Send success response with updated HTML
        wp_send_json_success($response);
    }

    /**
     * Generate HTML for courier history display
     */
    private function generate_courier_history_html($phone, $order_id, $courier_history) {
        if (is_string($courier_history)) {
            $courier_history = json_decode($courier_history, true);
        }
        if (!is_array($courier_history)) {
            $courier_history = [];
        }
        $total_order     = intval($courier_history['total_order'] ?? ($courier_history['total_orders'] ?? 0));
        $total_success   = intval($courier_history['total_success'] ?? 0);
        $total_cancel    = intval($courier_history['total_cancel'] ?? ($courier_history['total_returns'] ?? 0));
        $success_percent = floatval($courier_history['success_percent'] ?? ($courier_history['courier_ratio'] ?? 0));

        if ($total_order > 0 && $success_percent == 0) {
            $success_percent = round(($total_success / $total_order) * 100, 1);
        }
        $cancel_percent  = floatval($courier_history['cancel_percent'] ?? 0);
        if ($total_order > 0 && $cancel_percent == 0) {
            $cancel_percent = round(($total_cancel / $total_order) * 100, 1);
        }

        $status_color = '#e53e3e'; // Red
        if ($success_percent >= 70) {
            $status_color = '#00a669'; // Green 
        } else if ($success_percent >= 40) {
            $status_color = '#dd6b20'; // Orange
        }

        ob_start();
        ?>
        <div class="courier-history-stats" style="margin-bottom: 6px; font-size: 13px; display: flex; gap: 12px;">
            <span class="stat-label" title="Total" style="color: #1a202c; font-weight: 600;">📦 <?php echo esc_html($total_order); ?></span>
            <span class="stat-label success" title="Success" style="color: #00a669; font-weight: 600;">✓ <?php echo esc_html($total_success); ?></span>
            <span class="stat-label cancel" title="Cancel" style="color: #e53e3e; font-weight: 600;">✕ <?php echo esc_html($total_cancel); ?></span>
        </div>
        <div class="courier-progress-wrap" style="display: flex; align-items: center; width: 100%; max-width: 160px; gap: 10px;">
            <div class="progress-bar-bg" style="flex: 1; height: 6px; background: #edf2f7; border-radius: 4px; overflow: hidden;">
                <div class="progress-bar-fill" style="width: <?php echo esc_attr($success_percent); ?>%; height: 100%; background: <?php echo esc_attr($status_color); ?>; border-radius: 4px;"></div>
            </div>
            <div class="progress-text" style="font-size: 12px; font-weight: 700; color: <?php echo esc_attr($status_color); ?>; min-width: 25px;">
                <?php echo round($success_percent); ?>%
            </div>
            <button class="refresh-courier-history" data-phone="<?php echo esc_attr($phone); ?>" data-order-id="<?php echo esc_attr($order_id); ?>" title="Refresh" style="background: none; border: none; padding: 0; cursor: pointer; color: #a0aec0; transition: color 0.2s;">
                <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px;"></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add JavaScript for courier history functionality
     */
    public function add_courier_history_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                
                // Handle Check & Refresh button clicks
                $(document).on('click', '.refresh-courier-history, .ads-check-btn', function(e) {
                    e.preventDefault();

                    var $button = $(this);
                    var $container = $button.closest('.courier-history-container');
                    var phone = $button.data('phone');
                    var order_id = $button.data('order-id');
                    var isInitialCheck = $button.hasClass('ads-check-btn');

                    // Show loading state
                    $button.prop('disabled', true);
                    
                    if (isInitialCheck) {
                        $button.html('⏳ Checking...');
                        $button.css('background-color', '#718096');
                    } else {
                        $button.find('.dashicons').addClass('spinning');
                        $button.css('color', '#4a5568');
                    }

                    $container.addClass('loading');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'refresh_courier_history',
                            nonce: '<?php echo wp_create_nonce('refresh_courier_history_nonce'); ?>',
                            phone: phone,
                            order_id: order_id
                        },
                        success: function(response) {
                            if (response.success) {
                                $container.html(response.data.html);
                                $container.addClass('loaded');
                            } else {
                                if (isInitialCheck) {
                                    $button.html('❌ Failed');
                                    $button.css('background-color', '#e53e3e');
                                } else {
                                    alert('Error: ' + response.data.message);
                                }
                            }
                        },
                        error: function() {
                            if (isInitialCheck) {
                                $button.html('❌ Error');
                                $button.css('background-color', '#e53e3e');
                            } else {
                                alert('An error occurred while fetching courier history.');
                            }
                        },
                        complete: function() {
                            if (!isInitialCheck && !$container.hasClass('loaded')) {
                                $button.prop('disabled', false);
                                $button.find('.dashicons').removeClass('spinning');
                                $button.css('color', '#a0aec0');
                            }
                            $container.removeClass('loading');
                        }
                    });
                });

                // Step 1: Initial Button Click - Fetch Count Only
                $(document).on('click', '.ads-insight-count-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $button = $(this);
                    var $container = $button.closest('.ads-insight-container');
                    if ($button.hasClass('loading')) return;

                    var phone = $container.data('phone');
                    var name = $container.data('name');
                    
                    $button.addClass('loading');
                    $button.html('<span class="dashicons dashicons-update spinning" style="font-size:14px; width:14px; height:14px; margin-right:4px;"></span> Loading...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fetch_customer_order_count',
                            nonce: '<?php echo wp_create_nonce("refresh_courier_history_nonce"); ?>',
                            phone: phone
                        },
                        success: function(response) {
                            if (response.success) {
                                // Morph the button into "Eye X Total Orders" solid pill to match layout
                                $button.replaceWith(`
                                    <button class="ads-insights-badge" data-phone="${phone}" data-name="${name}" style="background: #197278; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; transition: background 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" onmouseover="this.style.background='#125458'" onmouseout="this.style.background='#197278'" title="View Full Insights">
                                        <span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span>
                                        ${response.data.count} Total Orders
                                    </button>
                                `);
                            } else {
                                $button.removeClass('loading').html('Error');
                            }
                        },
                        error: function() {
                            $button.removeClass('loading').html('Error');
                        }
                    });
                });

                // Step 2: Customer Insights Popup Handler (Eye Icon)
                $(document).on('click', '.ads-insights-badge', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent TR navigation
                    var $badge = $(this);
                    if ($badge.hasClass('loading')) return;

                    var phone = $badge.data('phone');
                    var name = $badge.data('name');
                    
                    $badge.addClass('loading');
                    var originalHtml = $badge.html();
                    $badge.html('<span class="dashicons dashicons-update spinning" style="font-size:14px; width:14px; height:14px; margin-right:4px;"></span> Loading...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fetch_customer_insights',
                            nonce: '<?php echo wp_create_nonce("refresh_courier_history_nonce"); ?>',
                            phone: phone,
                            name: name
                        },
                        success: function(response) {
                            $badge.removeClass('loading').html(originalHtml);
                            if (response.success) {
                                renderInsightsModal(response.data);
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        },
        error: function() {
                            $badge.removeClass('loading').html(originalHtml);
                            alert('An error occurred while fetching insights.');
                        }
                    });
                });

                function renderInsightsModal(data) {
                    $('#fmb-engine-insights-modal').remove();
                    
                    var tableRows = '';
                    if (data.orders.length > 0) {
                        data.orders.forEach(function(o) {
                            tableRows += `
                            <tr style="border-bottom: 1px solid #e9ecef; transition: background 0.2s;">
                                <td style="padding: 10px 12px; font-size: 13px; color: #197278; font-weight: 500;">#${o.id}</td>
                                <td style="padding: 10px 12px; font-size: 12px; color: #495057;">${o.date}</td>
                                <td style="padding: 10px 12px; font-size: 12px; color: #212529; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;" title="${o.name}">${o.name}</td>
                                <td style="padding: 10px 12px; font-size: 12px; color: #495057; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;" title="${o.address.replace(/"/g, '&quot;')}">${o.address}</td>
                                <td style="padding: 10px 12px; font-size: 11px;">
                                    <mark class="order-status status-${o.status}">
                                        <span>${o.status_name}</span>
                                    </mark>
                                </td>
                                <td style="padding: 10px 12px; font-size: 13px; color: #212529; font-weight: 600;">${o.total}</td>
                                <td style="padding: 10px 12px; font-size: 12px; color: #495057; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;" title="${o.products.replace(/"/g, '&quot;')}">${o.products}</td>
                                <td style="padding: 10px 12px; font-size: 12px; color: #6c757d;">${o.payment}</td>
                            </tr>`;
                        });
                    } else {
                        tableRows = `<tr><td colspan="8" style="padding: 20px; text-align: center; color: #6b7280;">No previous orders found.</td></tr>`;
                    }

                    var consistencyColor = data.stats.consistency.includes('Changed') ? '#dc3545' : '#197278';
                    var consistencyIcon = data.stats.consistency.includes('Changed') ? 'warning' : 'yes-alt';

                    var modalHtml = `
                    <div id="fmb-engine-insights-modal" class="fmb-engine-admin" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(2px); z-index: 999999; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">
                        <div style="background: #f0f2f5; border-radius: 8px; width: 95%; max-width: 1100px; height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); transform: translateY(15px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
                            
                            <!-- Header -->
                            <div style="background: #197278; padding: 16px 20px; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
                                <h2 style="margin: 0; font-size: 18px; font-weight: 600; color: white; display: flex; align-items: center;">
                                    Order History for&nbsp;${data.customer.phone} 
                                    <a href="https://wa.me/${data.customer.phone.replace(/[^0-9]/g, '')}" target="_blank" style="color: white; text-decoration: none; margin-left: 8px; display: flex; align-items: center; background: rgba(255,255,255,0.2); padding: 4px; border-radius: 50%; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                                    </a>
                                </h2>
                                <button class="close-insights-modal" style="background: rgba(255,255,255,0.2); border: none; width: 28px; height: 28px; border-radius: 4px; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                                    <span class="dashicons dashicons-no-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                </button>
                            </div>
                            
                            <!-- Static Top content (8 Boxes) -->
                            <div style="padding: 20px 24px; flex-shrink: 0; background: #f0f2f5;">
                                
                                <div style="font-size: 14px; font-weight: 600; color: #495057; margin-bottom: 12px; margin-left: 2px;">Customer Details</div>
                                
                                <!-- Top Row (4 Boxes) -->
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                                    <div style="background: white; padding: 14px 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                                        <div style="font-size: 11px; color: #868e96; margin-bottom: 6px;">Name</div>
                                        <div style="font-size: 13px; font-weight: 500; color: #343a40; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${data.customer.name}">${data.customer.name}</div>
                                    </div>
                                    <div style="background: white; padding: 14px 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                                        <div style="font-size: 11px; color: #868e96; margin-bottom: 6px;">Phone</div>
                                        <div style="font-size: 13px; font-weight: 500; color: #343a40;">${data.customer.phone}</div>
                                    </div>
                                    <div style="background: white; padding: 14px 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                                        <div style="font-size: 11px; color: #868e96; margin-bottom: 6px;">Address</div>
                                        <div style="font-size: 13px; font-weight: 500; color: #343a40; line-height: 1.3;" title="${data.customer.address}">${data.customer.address}</div>
                                    </div>
                                    <div style="background: white; padding: 14px 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                                        <div style="font-size: 11px; color: #868e96; margin-bottom: 6px;">Order Status Summary</div>
                                        <div style="display: flex; gap: 12px; font-size: 13px; font-weight: 600;">
                                            <span style="color: #28a745; display:flex; align-items:center;"><span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; margin-right: 2px;"></span> ${data.stats.s_completed}</span>
                                            <span style="color: #64748b; display:flex; align-items:center;"><span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px; margin-right: 2px;"></span> ${data.stats.s_processing}</span>
                                            <span style="color: #dc3545; display:flex; align-items:center;"><span class="dashicons dashicons-no-alt" style="font-size: 14px; width: 14px; height: 14px; margin-right: 2px;"></span> ${data.stats.s_cancelled}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bottom Row (4 Boxes) -->
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                                    <!-- Box 5: Lifetime Value -->
                                    <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: flex-start;">
                                        <div style="background: #e0f2f1; color: #197278; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                            <span class="dashicons dashicons-chart-bar" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: #868e96; margin-bottom: 2px;">Lifetime Value</div>
                                            <div style="font-size: 18px; font-weight: 700; color: #343a40;">${data.stats.ltv}</div>
                                            <div style="font-size: 12px; color: #adb5bd; margin-top: 2px;">${data.stats.total_orders} orders</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Box 6: Order Patterns -->
                                    <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: flex-start;">
                                        <div style="background: #eef2ff; color: #4f46e5; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                            <span class="dashicons dashicons-calendar-alt" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: #868e96; margin-bottom: 2px;">Order Patterns</div>
                                            <div style="font-size: 16px; font-weight: 700; color: #343a40;">${data.stats.customer_type}</div>
                                            <div style="font-size: 12px; color: #adb5bd; margin-top: 2px;">Last order: ${data.stats.last_order_date}</div>
                                        </div>
                                    </div>

                                    <!-- Box 7: Contact Consistency -->
                                    <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: flex-start;">
                                        <div style="background: ${consistencyColor}15; color: ${consistencyColor}; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                            <span class="dashicons dashicons-${consistencyIcon}" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: #868e96; margin-bottom: 2px;">Contact Consistency</div>
                                            <div style="font-size: 16px; font-weight: 700; color: ${consistencyColor};">${data.stats.consistency}</div>
                                            <div style="font-size: 12px; color: #adb5bd; margin-top: 2px;">${data.stats.consistency_detail}</div>
                                        </div>
                                    </div>

                                    <!-- Box 8: Recent Activity -->
                                    <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: flex-start;">
                                        <div style="background: #f8f9fa; color: #6c757d; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                            <span class="dashicons dashicons-clock" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: #868e96; margin-bottom: 2px;">Recent Activity</div>
                                            <div style="font-size: 16px; font-weight: 700; color: #343a40;">${data.stats.recent_30_count} in last 30 days</div>
                                            <div style="font-size: 12px; color: #adb5bd; margin-top: 2px;">${data.stats.recent_30_spend} recent spend</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scrollable Table Body -->
                            <div style="flex: 1; display: flex; flex-direction: column; background: white; margin: 0 24px 24px 24px; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;">
                                <div style="padding: 16px; border-bottom: 1px solid #dee2e6; font-size: 14px; font-weight: 600; color: #495057; background: white;">
                                    Complete Order History
                                </div>
                                <div style="flex: 1; overflow-y: auto;">
                                    <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 900px;">
                                        <thead style="background: #fdfdfd; position: sticky; top: 0; z-index: 10; box-shadow: 0 1px 0 #dee2e6;">
                                            <tr>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Order</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Date</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Name</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Address</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Status</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Total</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Products</th>
                                                <th style="padding: 12px 14px; font-size: 12px; font-weight: 600; color: #495057;">Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${tableRows}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    $('body').append(modalHtml);
                    
                    // Trigger animations
                    setTimeout(function() {
                        $('#fmb-engine-insights-modal').css('opacity', '1').find('> div').css('transform', 'translateY(0)');
                    }, 10);
                }

                // Close modal events
                $(document).on('click', '.close-insights-modal, #fmb-engine-insights-modal', function(e) {
                    if (e.target === this || $(this).closest('.close-insights-modal').length) {
                         $('#fmb-engine-insights-modal').css('opacity', '0').find('> div').css('transform', 'translateY(15px)');
                         setTimeout(function() { $('#fmb-engine-insights-modal').remove(); }, 300);
                    }
                });
            });
        </script>
        <style>
            .ads-check-btn {
                background-color: #197278;
                color: #e2e8f0; /* Slightly muted white for default state */
                border: none;
                border-radius: 6px;
                padding: 6px 14px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
            }
            .ads-check-btn:hover:not(:disabled) {
                background-color: #197278; /* Keeps the same deep background color */
                color: #ffffff !important; /* Explicitly turns text bright white */
                transform: translateY(-1px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            .ads-check-btn:disabled {
                opacity: 0.9;
                cursor: not-allowed;
            }
            .dashicons.spinning {
                animation: rotation 1.5s infinite linear;
            }
            .refresh-courier-history:hover {
                color: #4a5568 !important;
            }
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(359deg); }
            }
            .ads-insights-badge:hover {
                background: #125458 !important;
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(18, 84, 88, 0.2) !important;
            }
        </style>
        <?php
    }

    /**
     * AJAX handler for Customer Insights Popup
     */
    public function fetch_customer_insights() {
        check_ajax_referer('refresh_courier_history_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : 'Customer';

        if (!$phone || strlen($phone) < 10) {
            wp_send_json_error(['message' => 'Invalid phone number']);
        }

        global $wpdb;
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        $orders_data = [];

        $unique_names = [];
        $unique_addresses = [];
        $latest_name = '';
        $latest_address = '';
        $recent_30_count = 0;
        $recent_30_spend = 0;
        $thirty_days_ago = strtotime('-30 days');

        // Support for WooCommerce HPOS & Classic Posts
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $table = $wpdb->prefix . 'wc_orders';
            $table_addresses = $wpdb->prefix . 'wc_order_addresses';
            
            $query = $wpdb->prepare(
                "SELECT o.id, o.date_created_gmt as date, o.total_amount, o.status 
                 FROM $table o
                 JOIN $table_addresses a ON o.id = a.order_id
                 WHERE a.address_type = 'billing' AND a.phone LIKE %s AND o.status != 'trash'
                 ORDER BY o.date_created_gmt DESC LIMIT 100",
                '%' . $wpdb->esc_like($phone_clean) . '%'
            );
            $results = $wpdb->get_results($query);
            foreach($results as $r) {
                $order = wc_get_order($r->id);
                if($order) {
                    $items = $order->get_items();
                    $product_names = [];
                    foreach($items as $item) { $product_names[] = $item->get_name() . ' (x' . $item->get_quantity() . ')'; }
                    
                    $fname = $order->get_billing_first_name();
                    $lname = $order->get_billing_last_name();
                    $full_name = trim($fname . ' ' . $lname);
                    $full_address = trim($order->get_billing_address_1() . ', ' . $order->get_billing_city(), ", \t\n\r\0\x0B");
                    
                    if(!empty($full_name)) {
                        $unique_names[strtolower($full_name)] = true;
                        if(empty($latest_name)) $latest_name = $full_name;
                    }
                    if(!empty($full_address)) {
                        $unique_addresses[strtolower($full_address)] = true;
                        if(empty($latest_address)) $latest_address = $full_address;
                    }

                    $order_ts = $order->get_date_created() ? $order->get_date_created()->getOffsetTimestamp() : 0;
                    $status_cleaned = str_replace('wc-', '', $r->status);
                    
                    if ($order_ts > $thirty_days_ago && in_array($status_cleaned, ['completed', 'processing'])) {
                        $recent_30_count++;
                        $recent_30_spend += (float)$order->get_total();
                    }

                    $orders_data[] = [
                        'id' => $order->get_order_number(),
                        'date' => wc_format_datetime($order->get_date_created(), 'M j, Y'),
                        'raw_ts' => $order_ts,
                        'name' => $full_name,
                        'address' => $full_address,
                        'products' => implode(', ', $product_names),
                        'total' => html_entity_decode(strip_tags(wc_price($order->get_total()))),
                        'status' => $status_cleaned,
                        'status_name' => wc_get_order_status_name($status_cleaned),
                        'payment' => $order->get_payment_method_title(),
                        'raw_total' => $order->get_total()
                    ];
                }
            }
        } else {
            $query = $wpdb->prepare(
                "SELECT p.ID, p.post_date as date, p.post_status as status 
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_billing_phone' AND pm.meta_value LIKE %s 
                 AND p.post_type = 'shop_order' AND p.post_status != 'trash'
                 ORDER BY p.post_date DESC LIMIT 100",
                '%' . $wpdb->esc_like($phone_clean) . '%'
            );
            $results = $wpdb->get_results($query);
            foreach($results as $r) {
                $order = wc_get_order($r->ID);
                if($order) {
                    $items = $order->get_items();
                    $product_names = [];
                    foreach($items as $item) { $product_names[] = $item->get_name() . ' (x' . $item->get_quantity() . ')'; }
                    
                    $fname = $order->get_billing_first_name();
                    $lname = $order->get_billing_last_name();
                    $full_name = trim($fname . ' ' . $lname);
                    $full_address = trim($order->get_billing_address_1() . ', ' . $order->get_billing_city(), ", \t\n\r\0\x0B");
                    
                    if(!empty($full_name)) {
                        $unique_names[strtolower($full_name)] = true;
                        if(empty($latest_name)) $latest_name = $full_name;
                    }
                    if(!empty($full_address)) {
                        $unique_addresses[strtolower($full_address)] = true;
                        if(empty($latest_address)) $latest_address = $full_address;
                    }

                    $order_ts = $order->get_date_created() ? $order->get_date_created()->getOffsetTimestamp() : 0;
                    $status_cleaned = str_replace('wc-', '', $r->status);
                    
                    if ($order_ts > $thirty_days_ago && in_array($status_cleaned, ['completed', 'processing'])) {
                        $recent_30_count++;
                        $recent_30_spend += (float)$order->get_total();
                    }

                    $orders_data[] = [
                        'id' => $order->get_order_number(),
                        'date' => wc_format_datetime($order->get_date_created(), 'M j, Y'),
                        'raw_ts' => $order_ts,
                        'name' => $full_name,
                        'address' => $full_address,
                        'products' => implode(', ', $product_names),
                        'total' => html_entity_decode(strip_tags(wc_price($order->get_total()))),
                        'status' => $status_cleaned,
                        'status_name' => wc_get_order_status_name($status_cleaned),
                        'payment' => $order->get_payment_method_title(),
                        'raw_total' => $order->get_total()
                    ];
                }
            }
        }

        $total_spent = 0;
        $total_orders = count($orders_data);
        $completed = 0;
        $processing = 0;
        $cancelled = 0;
        
        foreach($orders_data as $od) {
            if ($od['status'] === 'completed') {
                $total_spent += (float)$od['raw_total'];
                $completed++;
            } else if ($od['status'] === 'processing') {
                $total_spent += (float)$od['raw_total'];
                $processing++;
            } else if ($od['status'] === 'cancelled' || $od['status'] === 'failed') {
                $cancelled++;
            }
        }
        
        $success_rate = ($total_orders > 0 && ($completed + $cancelled) > 0) 
                        ? round(($completed / ($completed + $cancelled)) * 100) 
                        : ($completed > 0 ? 100 : 0);

        $consistency = (count($unique_names) > 1 || count($unique_addresses) > 1) ? 'Changed' : 'Consistent';
        $consistency_detail = '';
        if(count($unique_names) > 1 && count($unique_addresses) > 1) $consistency_detail = 'Changed: Name & Address';
        else if(count($unique_names) > 1) $consistency_detail = 'Changed: Name';
        else if(count($unique_addresses) > 1) $consistency_detail = 'Changed: Address';
        else $consistency_detail = 'Name/Address matching';

        $customer_type = 'First time';
        $last_order_date = 'Never';

        if ($total_orders > 0) {
            $customer_type = $total_orders > 1 ? 'Returning' : 'First time';
            foreach($orders_data as $od) {
                if (in_array($od['status'], ['completed', 'processing'])) {
                    $time_diff = human_time_diff($od['raw_ts'], current_time('timestamp')) . ' ago';
                    $last_order_date = $time_diff;
                    break;
                }
            }
            if ($last_order_date === 'Never') {
                 $last_order_date = human_time_diff($orders_data[0]['raw_ts'], current_time('timestamp')) . ' ago';
            }
        }

        if(empty($latest_name)) $latest_name = $name;

        wp_send_json_success([
            'orders' => $orders_data,
            'stats' => [
                'total_orders' => $total_orders,
                'ltv' => strip_tags(wc_price($total_spent)),
                'success_rate' => $success_rate . '%',
                'customer_type' => $customer_type,
                'last_order_date' => $last_order_date,
                'consistency' => $consistency,
                'consistency_detail' => $consistency_detail,
                'recent_30_count' => $recent_30_count,
                'recent_30_spend' => strip_tags(wc_price($recent_30_spend)),
                's_completed' => $completed,
                's_processing' => $processing,
                's_cancelled' => $cancelled
            ],
            'customer' => [
                'name' => $latest_name,
                'phone' => $phone,
                'address' => $latest_address ?: 'Address not verified'
            ]
        ]);
    }
    
    /**
     * AJAX handler for lightweight Customer Order Count mapping
     */
    public function fetch_customer_order_count() {
        check_ajax_referer('refresh_courier_history_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if (!$phone || strlen($phone) < 10) {
            wp_send_json_error(['message' => 'Invalid phone number']);
        }

        global $wpdb;
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        $count = 0;

        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $table = $wpdb->prefix . 'wc_orders';
            $table_addresses = $wpdb->prefix . 'wc_order_addresses';
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(o.id) FROM $table o JOIN $table_addresses a ON o.id = a.order_id WHERE a.address_type = 'billing' AND a.phone LIKE %s AND o.status != 'trash'",
                '%' . $wpdb->esc_like($phone_clean) . '%'
            ));
        } else {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_billing_phone' AND pm.meta_value LIKE %s AND p.post_type = 'shop_order' AND p.post_status != 'trash'",
                '%' . $wpdb->esc_like($phone_clean) . '%'
            ));
        }

        wp_send_json_success(['count' => $count]);
    }

    public function refresh_single_courier_status_ajax() {
        check_ajax_referer('refresh_single_courier_status', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $courier = isset($_POST['courier']) ? sanitize_text_field($_POST['courier']) : '';
        $consignment = isset($_POST['consignment']) ? sanitize_text_field($_POST['consignment']) : '';

        if (!$order_id || !$courier || !$consignment) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $new_status = '';

        if ($courier === 'steadfast') {
            $api_key = get_option('steadfast_api_key');
            $secret_key = get_option('steadfast_secret_key');

            $response = wp_remote_get('https://portal.packzy.com/api/v1/status_by_cid/' . urlencode($consignment), [
                'headers' => [
                    'Api-Key' => $api_key,
                    'Secret-Key' => $secret_key
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'API Error: ' . $response->get_error_message()]);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['status']) && $data['status'] === 200) {
                if (isset($data['delivery_status'])) {
                    $new_status = $data['delivery_status'];
                } elseif (isset($data['data']['delivery_status'])) {
                    $new_status = $data['data']['delivery_status'];
                } else {
                    $new_status = 'pending'; // Fallback if format is weird
                }
                
                // Extract remark
                $rider_name = isset($data['rider_name']) ? $data['rider_name'] : (isset($data['data']['rider_name']) ? $data['data']['rider_name'] : '');
                $rider_phone = isset($data['rider_phone']) ? $data['rider_phone'] : (isset($data['data']['rider_phone']) ? $data['data']['rider_phone'] : '');
                $rider_info = '';
                if ($rider_name) $rider_info .= "Rider: " . $rider_name;
                if ($rider_phone) $rider_info .= " (" . $rider_phone . ")";

                if (isset($data['note']) && !empty($data['note'])) {
                    $remark = $data['note'];
                } elseif (isset($data['data']['note']) && !empty($data['data']['note'])) {
                    $remark = $data['data']['note'];
                } elseif (isset($data['remarks']) && !empty($data['remarks'])) {
                    $remark = $data['remarks'];
                } elseif (isset($data['data']['remarks']) && !empty($data['data']['remarks'])) {
                    $remark = $data['data']['remarks'];
                }

                if (!empty($rider_info)) {
                    $remark = $remark ? $rider_info . " - " . $remark : $rider_info;
                }
            } else {
                wp_send_json_error(['message' => isset($data['message']) ? $data['message'] : 'Failed to fetch status from Steadfast']);
            }
        } elseif ($courier === 'pathao') {
            $token = $this->get_pathao_access_token();
            if (!$token) {
                wp_send_json_error(['message' => 'Pathao token error. Reconnect in settings.']);
            }

            $response = wp_remote_get('https://api-hermes.pathao.com/aladdin/api/v1/orders/' . urlencode($consignment), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'API Error: ' . $response->get_error_message()]);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['data']['order_status'])) {
                $new_status = $data['data']['order_status'];
                
                $rider_name = isset($data['data']['rider_name']) ? $data['data']['rider_name'] : '';
                $rider_phone = isset($data['data']['rider_phone']) ? $data['data']['rider_phone'] : '';
                $rider_info = '';
                if ($rider_name) $rider_info .= "Rider: " . $rider_name;
                if ($rider_phone) $rider_info .= " (" . $rider_phone . ")";

                if (isset($data['data']['reason']) && !empty($data['data']['reason'])) {
                    $remark = $data['data']['reason'];
                } elseif (isset($data['data']['remarks']) && !empty($data['data']['remarks'])) {
                    $remark = $data['data']['remarks'];
                } elseif (isset($data['data']['comments']) && !empty($data['data']['comments'])) {
                    $remark = $data['data']['comments'];
                }

                if (!empty($rider_info)) {
                    $remark = $remark ? $rider_info . " - " . $remark : $rider_info;
                }
            } else {
                wp_send_json_error(['message' => isset($data['message']) ? $data['message'] : 'Failed to fetch status from Pathao']);
            }
        } else {
            wp_send_json_error(['message' => 'Unsupported courier']);
        }

        if ($new_status) {
            $order->update_meta_data("_{$courier}_delivery_status", $new_status);
            
            if (!empty($remark)) {
                $old_remark = $order->get_meta('_ofls_courier_latest_remark');
                if ($old_remark !== $remark) {
                    $order->update_meta_data('_ofls_courier_latest_remark', sanitize_text_field($remark));
                    $order->add_order_note(sprintf(__('🚚 Courier Remarks Update (Manual): %s', 'FMB Engine'), sanitize_text_field($remark)));
                }
            }
            
            $order->save();

            $status_clean = ucwords(str_replace('_', ' ', $new_status));
            $bg_color = '#f1f5f9';
            $text_color = '#475569';
            
            switch (strtolower($new_status)) {
                case 'delivered':
                case 'success':
                    $bg_color = '#e6fffa'; $text_color = '#166534'; break;
                case 'in_review':
                case 'pending':
                    $bg_color = '#fffaf0'; $text_color = '#92400e'; break;
                case 'cancelled':
                case 'returned':
                case 'failed':
                case 'pickup cancel':
                case 'pickup_cancel':
                    $bg_color = '#fff5f5'; $text_color = '#991b1b'; break;
                case 'processing':
                case 'shipped':
                case 'in_transit':
                case 'dispatched':
                    $bg_color = '#ebf8ff'; $text_color = '#1e40af'; break;
                default:
                    $bg_color = '#f1f5f9'; $text_color = '#475569'; break;
            }

            wp_send_json_success([
                'status_clean' => $status_clean,
                'bg_color' => $bg_color,
                'text_color' => $text_color,
                'remark' => !empty($remark) ? esc_html($remark) : ''
            ]);
        }

        wp_send_json_error(['message' => 'Could not determine new status']);
    }

    /**
     * AJAX handler to passively save Courier Custom COD Amount
     */
    public function save_courier_amount_ajax() {
        check_ajax_referer('save_courier_amount');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';

        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }

        if ($amount > 100000) {
            wp_send_json_error('Amount exceeds 100000');
        }

        $order->update_meta_data('_courier_custom_cod_amount', $amount);
        $order->save();

        wp_send_json_success(['message' => 'Amount saved.']);
    }

    /**
     * AJAX handler for analyzing bulk orders before sending to Steadfast
     */
    public function analyze_bulk_orders_ajax() {
        check_ajax_referer('steadfast_send');
        
        $order_ids = isset($_POST['order_ids']) ? array_map('intval', (array) $_POST['order_ids']) : [];
        if (empty($order_ids)) {
            wp_send_json_error('No orders selected.');
        }

        $response_data = [
            'safe' => [],
            'grouped' => [],
            'risky' => [],
            'already_booked' => []
        ];

        // Track phones to find same-day duplicates
        $phone_tracker = [];
        $today = gmdate('Y-m-d');

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            if ($order->get_status() === 'cancelled') continue;

            $consignment_id = $order->get_meta('_steadfast_consignment_id') 
                           ?: ($order->get_meta('_courier_consignment_id') 
                           ?: ($order->get_meta('_fmb_consignment_id') 
                           ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
            if (!empty($consignment_id)) {
                $response_data['already_booked'][] = [
                    'id' => $order_id,
                    'number' => $order->get_order_number(),
                    'consignment' => $consignment_id
                ];
                continue; // Skip further analysis for this one - NEVER book twice!
            }

            $phone = $this->normalize_phone_number($order->get_billing_phone());
            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $total = wc_price($order->get_total());

            $order_info = [
                'id' => $order_id,
                'number' => $order->get_order_number(),
                'name' => $name,
                'phone' => $phone,
                'total' => $total,
                'edit_url' => get_edit_post_link($order_id, 'raw')
            ];

            // Same batch grouping
            if (!isset($phone_tracker[$phone])) {
                $phone_tracker[$phone] = [];
            }
            $phone_tracker[$phone][] = $order_info;
        }

        // Now process groups and risky behaviors
        foreach ($phone_tracker as $phone => $orders) {
            if (empty($phone)) continue;

            // Are there multiple in the same batch?
            if (count($orders) > 1) {
                $response_data['grouped'][] = [
                    'phone' => $phone,
                    'orders' => $orders
                ];
                continue; 
            }
            
            // Check past history
            $order_info = $orders[0];
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p 
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE pm.meta_key = '_billing_phone' AND pm.meta_value LIKE %s 
                 AND p.post_type = 'shop_order' AND p.post_status != 'trash' AND p.ID != %d",
                '%' . $wpdb->esc_like($phone) . '%',
                $order_info['id']
            );
            $past_orders = $wpdb->get_col($query);
            
            if (!empty($past_orders)) {
                // Determine if it's risky (e.g. they have past orders)
                // For simplicity, we just flag them as having history and ask user to confirm
                $success_count = 0;
                $return_count = 0;
                foreach($past_orders as $pid) {
                    $post_status = get_post_status($pid);
                    if (in_array($post_status, ['wc-completed', 'wc-ads-delivered'])) {
                        $success_count++;
                    } elseif (in_array($post_status, ['wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-ads-returned'])) {
                        $return_count++;
                    }
                }
                
                $order_info['history'] = "Total Past: " . count($past_orders) . " (Delivered: $success_count, Returned: $return_count)";
                $response_data['risky'][] = $order_info;
            } else {
                $response_data['safe'][] = $order_info;
            }
        }

        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler to merge multiple orders into one
     */
    public function merge_orders_ajax() {
        check_ajax_referer('steadfast_send');
        
        $order_ids = isset($_POST['order_ids']) ? array_map('intval', (array) $_POST['order_ids']) : [];
        if (count($order_ids) < 2) {
            wp_send_json_error('Select at least two orders to merge.');
        }

        // Sort by ID to make the oldest one the primary order
        sort($order_ids);
        $primary_id = array_shift($order_ids);
        $primary_order = wc_get_order($primary_id);

        if (!$primary_order) {
            wp_send_json_error('Primary order not found.');
        }

        $merged_ids = [];
        
        foreach ($order_ids as $secondary_id) {
            $secondary_order = wc_get_order($secondary_id);
            if (!$secondary_order) continue;

            // Copy items
            foreach ($secondary_order->get_items() as $item) {
                $new_item = new \WC_Order_Item_Product();
                $new_item->set_product_id($item->get_product_id());
                $new_item->set_variation_id($item->get_variation_id());
                $new_item->set_quantity($item->get_quantity());
                $new_item->set_name($item->get_name());
                $new_item->set_subtotal($item->get_subtotal());
                $new_item->set_total($item->get_total());
                
                // Copy item meta
                $item_meta = $item->get_meta_data();
                foreach($item_meta as $meta) {
                    $new_item->add_meta_data($meta->key, $meta->value);
                }

                $primary_order->add_item($new_item);
            }
            
            // Note: Not merging shipping or fees, keeping primary's shipping to save cost.
            
            // Mark secondary as cancelled
            $secondary_order->update_status('cancelled', 'Merged into Order #' . $primary_id);
            $merged_ids[] = $secondary_id;
        }

        $primary_order->calculate_totals();
        $primary_order->add_order_note('Merged with Order(s): ' . implode(', ', $merged_ids));
        $primary_order->save();

        wp_send_json_success(['message' => 'Orders successfully merged.']);
    }

    /**
     * AJAX handler to quickly cancel an order
     */
    public function cancel_order_ajax() {
        check_ajax_referer('steadfast_send');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error('Order not found.');
        }

        $order->update_status('cancelled', 'Cancelled from Smart Booking Assistant.');
        wp_send_json_success(['message' => 'Order cancelled.']);
    }

    /**
     * Steadfast AJAX Handler
     */
    public function send_to_steadfast_ajax() {
        check_admin_referer('steadfast_send');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) wp_send_json_error('Invalid order ID');

        $order = wc_get_order($order_id);
        if (!$order) wp_send_json_error('Order not found');

        $existing_cid = $order->get_meta('_steadfast_consignment_id') 
                     ?: ($order->get_meta('_courier_consignment_id') 
                     ?: ($order->get_meta('_fmb_consignment_id') 
                     ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
        if (!empty($existing_cid)) {
            wp_send_json_error(sprintf('Order #%s is already booked (Consignment ID: %s). An order cannot be booked more than once.', $order->get_order_number(), $existing_cid));
        }

        $api_key = get_option('steadfast_api_key');
        $secret_key = get_option('steadfast_secret_key');
        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error('Steadfast API keys are missing in settings.');
        }

        $custom_cod = $order->get_meta('_courier_custom_cod_amount');
        $cod_amount = $custom_cod !== '' ? floatval($custom_cod) : floatval($order->get_total());

        $payload = array(
            'invoice'           => $order->get_order_number(),
            'recipient_name'    => $order->get_formatted_billing_full_name(),
            'recipient_phone'   => $order->get_billing_phone(),
            'recipient_address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() . ' ' . $order->get_billing_city(),
            'cod_amount'        => $cod_amount,
            'note'              => $order->get_customer_note()
        );

        $response = wp_remote_post('https://portal.packzy.com/api/v1/create_order', array(
            'headers' => array(
                'Api-Key'      => $api_key,
                'Secret-Key'   => $secret_key,
                'Content-Type' => 'application/json'
            ),
            'body'    => wp_json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('API Request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] === 200 && isset($data['consignment']['consignment_id'])) {
            $consignment_id = $data['consignment']['consignment_id'];
            $delivery_status = $data['consignment']['status'];

            $order->update_meta_data('_steadfast_consignment_id', $consignment_id);
            $order->update_meta_data('_steadfast_delivery_status', $delivery_status);
            $order->save();

            // Save to dedicated courier database table
            if (function_exists('fmb_courier_db_save')) {
                fmb_courier_db_save($order_id, array(
                    'courier_name'    => 'steadfast',
                    'consignment_id'  => $consignment_id,
                    'tracking_code'   => $consignment_id,
                    'delivery_status' => $delivery_status,
                    'cod_amount'      => floatval($order->get_total()),
                ));
            }
            
            // Change status to Shipping since it was successfully booked to courier
            $order->update_status('ads-shipping', 'Order successfully booked to Steadfast Courier. Consignment ID: ' . $consignment_id);

            wp_send_json_success(array(
                'message' => 'Successfully sent. Consignment ID: ' . $consignment_id,
                'consignment_id' => $consignment_id
            ));
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'Failed to create consignment.';
            if (isset($data['errors'])) {
                $error_message .= ' ' . wp_json_encode($data['errors']);
            }
            wp_send_json_error($error_message);
        }
    }

    /**
     * AJAX handler for sending an order to Pathao Courier
     */
    public function send_to_pathao_ajax() {
        if (!current_user_can('manage_woocommerce') || !isset($_GET['order_id'])) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('pathao_send');

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found');
        }

        $token = $this->get_pathao_access_token();
        if (!$token) {
            wp_send_json_error('Pathao Error: Could not generate valid OAuth token. Check your Client ID and Secret in Settings.');
        }

        $store_id = get_option('pathao_store_id');
        $default_city = get_option('pathao_default_city', '1');
        $default_zone = get_option('pathao_default_zone', '1');

        $custom_cod = $order->get_meta('_courier_custom_cod_amount');
        $cod_amount = $custom_cod !== '' ? (int) floatval($custom_cod) : ($order->get_payment_method() === 'cod' ? (int) $order->get_total() : 0);

        $payload = array(
            'store_id' => intval($store_id),
            'merchant_order_id' => (string)$order->get_order_number(),
            'recipient_name' => $order->get_shipping_first_name() ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name(),
            'recipient_phone' => $order->get_billing_phone(),
            'recipient_address' => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
            'recipient_city' => intval($default_city),
            'recipient_zone' => intval($default_zone),
            'delivery_type' => 48,
            'item_type' => 2,
            'special_instruction' => $order->get_customer_note(),
            'item_quantity' => 1,
            'item_weight' => 0.5,
            'amount_to_collect' => $cod_amount,
        );

        $api_url = 'https://api-hermes.pathao.com/aladdin/api/v1/orders';

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('API Request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300 && !empty($data['data']['consignment_id'])) {
            $consignment_id = $data['data']['consignment_id'];
            
            $order->update_meta_data('_pathao_consignment_id', $consignment_id);
            $order->update_meta_data('_pathao_delivery_status', 'pending');
            $order->save();

            // Save to dedicated courier database table
            if (function_exists('fmb_courier_db_save')) {
                fmb_courier_db_save($order_id, array(
                    'courier_name'    => 'pathao',
                    'consignment_id'  => $consignment_id,
                    'tracking_code'   => $consignment_id,
                    'delivery_status' => 'pending',
                    'cod_amount'      => floatval($order->get_total()),
                ));
            }

            wp_send_json_success(array(
                'message' => 'Successfully sent. Consignment ID: ' . $consignment_id,
                'consignment_id' => $consignment_id
            ));
        } else {
            $error_message = 'Failed to create Pathao consignment. ';
            if (!empty($data['message'])) {
                $error_message .= $data['message'];
            }
            if (!empty($data['errors'])) {
                $error_message .= ' ' . wp_json_encode($data['errors']);
            }
            wp_send_json_error($error_message);
        }
    }

    /**
     * Generates and returns a Pathao OAuth Token using WordPress Transients to cache until expiry
     */
    private function get_pathao_access_token() {
        $token_data = get_transient('pathao_api_token_data');
        if ($token_data && !empty($token_data['access_token'])) {
            return $token_data['access_token'];
        }

        $api_key = get_option('pathao_api_key');
        $secret_key = get_option('pathao_secret_key');
        
        $response = wp_remote_post('https://api-hermes.pathao.com/aladdin/api/v1/external/login', array(
            'headers' => array(
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'client_id' => $api_key,
                'client_secret' => $secret_key,
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['access_token'])) {
            $expires = !empty($data['expires_in']) ? intval($data['expires_in']) : 86400;
            set_transient('pathao_api_token_data', $data, max(60, $expires - 60));
            return $data['access_token'];
        }

        return false;
    }

    /**
     * JS for Courier Sync
     */
    public function add_courier_sync_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var steadfast_nonce = '<?php echo wp_create_nonce("steadfast_send"); ?>';

                // Inject a visible bulk book button next to bulk actions
                if ($('select[name="action"]').length > 0) {
                    var btnHtml = '<button type="button" id="fmb-visible-bulk-book" class="button button-primary" style="margin-left:5px; margin-right:5px; background:#0ea5e9; border-color:#0284c7;">Send Selected to Steadfast</button>';
                    $('.bulkactions').first().append(btnHtml);
                }

                // Inject Modal HTML into Footer
                var modalHtml = `
                <div id="fmb-smart-booking-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:99999; align-items:center; justify-content:center; padding:20px;">
                    <div style="background:#fff; border-radius:12px; width:100%; max-width:700px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                        
                        <!-- Header -->
                        <div style="padding:20px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                            <h2 style="margin:0; font-size:18px; color:#0f172a; font-weight:600; display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-shield-alt" style="color:#0284c7; font-size:24px; width:24px; height:24px;"></span>
                                Smart Booking Assistant
                            </h2>
                            <button class="fmb-modal-close" style="background:none; border:none; cursor:pointer; color:#64748b; padding:4px; border-radius:4px;"><span class="dashicons dashicons-no-alt"></span></button>
                        </div>
                        
                        <!-- Body -->
                        <div id="fmb-sba-body" style="padding:24px; overflow-y:auto; flex:1; background:#fff;">
                            <div style="text-align:center; padding:40px 0;">
                                <span class="dashicons dashicons-update spinning" style="font-size:32px; width:32px; height:32px; color:#3b82f6;"></span>
                                <p style="margin-top:16px; color:#64748b; font-size:15px;">Analyzing selected orders...</p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div style="padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:flex-end; gap:12px; align-items:center;">
                            <div id="fmb-sba-progress-text" style="flex:1; font-size:14px; color:#475569; font-weight:500; display:none;"></div>
                            <button class="button fmb-modal-close" style="padding:6px 16px; border-color:#cbd5e1; color:#475569;">Cancel</button>
                            <button id="fmb-sba-confirm-btn" class="button button-primary" style="padding:6px 20px; background:#0ea5e9; border-color:#0284c7; display:none;" disabled>Confirm & Book</button>
                        </div>
                    </div>
                </div>
                `;
                $('body').append(modalHtml);

                var fmbSelectedOrdersToBook = [];

                $(document).on('click', '.fmb-modal-close', function(e) {
                    e.preventDefault();
                    if ($('#fmb-sba-confirm-btn').hasClass('booking-in-progress')) {
                        if(!confirm('Booking is in progress. Are you sure you want to stop?')) return;
                    }
                    $('#fmb-smart-booking-modal').hide();
                });

                $(document).on('click', '#fmb-visible-bulk-book', function(e) {
                    e.preventDefault();
                    var $checked = $('input[name="post[]"]:checked, input[name="id[]"]:checked');
                    if ($checked.length === 0) {
                        alert('Please select at least one order to book.');
                        return;
                    }
                    
                    var orderIds = [];
                    $checked.each(function() { orderIds.push($(this).val()); });

                    $('#fmb-smart-booking-modal').css('display', 'flex');
                    $('#fmb-sba-body').html(`
                        <div style="text-align:center; padding:40px 0;">
                            <span class="dashicons dashicons-update spinning" style="font-size:32px; width:32px; height:32px; color:#3b82f6;"></span>
                            <p style="margin-top:16px; color:#64748b; font-size:15px;">Analyzing ${orderIds.length} selected orders...</p>
                        </div>
                    `);
                    $('#fmb-sba-confirm-btn').hide();
                    $('#fmb-sba-progress-text').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fmb_engine_analyze_bulk_orders',
                            order_ids: orderIds,
                            _wpnonce: steadfast_nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                renderSmartReport(response.data);
                            } else {
                                $('#fmb-sba-body').html('<div style="color:#ef4444; padding:20px; text-align:center;">Failed to analyze orders: ' + response.data + '</div>');
                            }
                        },
                        error: function() {
                            $('#fmb-sba-body').html('<div style="color:#ef4444; padding:20px; text-align:center;">Network error during analysis.</div>');
                        }
                    });
                });

                function renderSmartReport(data) {
                    var html = '';
                    fmbSelectedOrdersToBook = [];

                    // Grouped Orders
                    if (data.grouped && data.grouped.length > 0) {
                        html += `
                        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:16px; margin-bottom:16px;">
                            <h3 style="margin:0 0 12px 0; color:#c2410c; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                                Grouped Orders (Same Day / Same Customer)
                            </h3>
                            <p style="margin:0 0 12px 0; font-size:13px; color:#9a3412;">Select the orders you want to merge. The products will be combined into a single order, and the others will be cancelled.</p>
                        `;
                        data.grouped.forEach(function(group) {
                            html += `<div class="fmb-group-box" style="margin-bottom:12px; background:#fff; padding:12px; border-radius:6px; border:1px solid #fdba74;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <div style="font-weight:600; font-size:13px; color:#431407;">📞 ${group.phone}</div>
                                    <button class="fmb-merge-group-btn" data-phone="${group.phone}" style="background:#ea580c; color:#fff; border:none; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600;">Merge Selected</button>
                                </div>`;
                            group.orders.forEach(function(order) {
                                fmbSelectedOrdersToBook.push(order.id);
                                html += `
                                <label style="display:flex; align-items:center; gap:10px; margin-bottom:6px; padding:4px 8px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='#ffedd5'" onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" class="fmb-order-cb fmb-group-cb-${group.phone}" value="${order.id}" checked>
                                    <span style="flex:1; font-size:13px;">#${order.number} - ${order.name} - <b>${order.total}</b></span>
                                    <button class="fmb-cancel-order-btn" data-id="${order.id}" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:12px; font-weight:600;" title="Cancel Order">❌ Cancel</button>
                                    <a href="${order.edit_url}" target="_blank" style="color:#ea580c; text-decoration:none; font-size:12px; font-weight:600; padding:2px 8px; border:1px solid #fb923c; border-radius:4px;">Edit</a>
                                </label>`;
                            });
                            html += `</div>`;
                        });
                        html += `</div>`;
                    }

                    // Risky Orders
                    if (data.risky && data.risky.length > 0) {
                        html += `
                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px; margin-bottom:16px;">
                            <h3 style="margin:0 0 12px 0; color:#b91c1c; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-flag" style="font-size:18px; width:18px; height:18px;"></span>
                                Customer History Found
                            </h3>
                        `;
                        data.risky.forEach(function(order) {
                            fmbSelectedOrdersToBook.push(order.id);
                            html += `
                            <label style="display:flex; align-items:center; gap:10px; margin-bottom:8px; padding:8px; background:#fff; border:1px solid #fca5a5; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
                                <input type="checkbox" class="fmb-order-cb" value="${order.id}" checked>
                                <div style="flex:1; display:flex; flex-direction:column;">
                                    <span style="font-size:13px; font-weight:600;">#${order.number} - ${order.name}</span>
                                    <span style="font-size:12px; color:#dc2626;">${order.history}</span>
                                </div>
                                <button class="fmb-cancel-order-btn" data-id="${order.id}" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:12px; font-weight:600;" title="Cancel Order">❌ Cancel</button>
                                <button class="fmb-details-btn ads-insights-badge" data-phone="${order.phone}" data-name="${order.name}" style="background:#b91c1c; color:#fff; border:none; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600;">Details</button>
                            </label>`;
                        });
                        html += `</div>`;
                    }

                    // Already Booked
                    if (data.already_booked && data.already_booked.length > 0) {
                        html += `
                        <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:16px; margin-bottom:16px;">
                            <h3 style="margin:0 0 12px 0; color:#475569; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-info" style="font-size:18px; width:18px; height:18px;"></span>
                                Already Booked (Skipping)
                            </h3>
                        `;
                        data.already_booked.forEach(function(order) {
                            html += `
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; padding:6px 8px; background:#fff; border-radius:4px; border:1px solid #e2e8f0; opacity:0.7;">
                                <span class="dashicons dashicons-yes" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                                <span style="flex:1; font-size:13px; color:#64748b;">#${order.number} - ID: ${order.consignment}</span>
                            </div>`;
                        });
                        html += `</div>`;
                    }

                    // Safe Orders
                    if (data.safe && data.safe.length > 0) {
                        html += `
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:16px;">
                            <h3 style="margin:0 0 12px 0; color:#166534; font-size:15px; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size:18px; width:18px; height:18px;"></span>
                                Safe to Book
                            </h3>
                            <div style="max-height:200px; overflow-y:auto; border:1px solid #dcfce7; border-radius:6px; background:#fff;">
                        `;
                        data.safe.forEach(function(order) {
                            fmbSelectedOrdersToBook.push(order.id);
                            html += `
                            <label style="display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid #f0fdf4;">
                                <input type="checkbox" class="fmb-order-cb" value="${order.id}" checked>
                                <span style="flex:1; font-size:13px;">#${order.number} - ${order.name} - <b>${order.total}</b></span>
                            </label>`;
                        });
                        html += `</div></div>`;
                    }

                    if (fmbSelectedOrdersToBook.length === 0) {
                        html += `<div style="text-align:center; padding:30px; color:#64748b;">No valid orders to book.</div>`;
                    }

                    $('#fmb-sba-body').html(html);
                    
                    if (fmbSelectedOrdersToBook.length > 0) {
                        $('#fmb-sba-confirm-btn').text(`Confirm & Book (${fmbSelectedOrdersToBook.length})`).prop('disabled', false).show();
                    } else {
                        $('#fmb-sba-confirm-btn').hide();
                    }

                    // Update count when checkboxes change
                    $('.fmb-order-cb').on('change', function() {
                        var count = $('.fmb-order-cb:checked').length;
                        $('#fmb-sba-confirm-btn').text(`Confirm & Book (${count})`).prop('disabled', count === 0);
                    });
                }

                // Merge Handlers
                $(document).on('click', '.fmb-merge-group-btn', function(e) {
                    e.preventDefault();
                    var phone = $(this).data('phone');
                    var $checkboxes = $('.fmb-group-cb-' + phone + ':checked');
                    
                    if ($checkboxes.length < 2) {
                        alert('Please select at least 2 orders to merge.');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to merge these ' + $checkboxes.length + ' orders into one? The first order will keep its shipping fee and all products will be combined. Other orders will be cancelled.')) {
                        var orderIds = [];
                        $checkboxes.each(function() { orderIds.push($(this).val()); });
                        
                        var $btn = $(this);
                        $btn.text('Merging...').prop('disabled', true);
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'fmb_engine_merge_orders_ajax',
                                order_ids: orderIds,
                                _wpnonce: steadfast_nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Orders successfully merged!');
                                    // The first order is the primary one, others are cancelled. Uncheck the cancelled ones from the main table.
                                    orderIds.sort(function(a, b) { return a - b; });
                                    for (var i = 1; i < orderIds.length; i++) {
                                        $('input[value="' + orderIds[i] + '"]').prop('checked', false);
                                    }
                                    $('#fmb-visible-bulk-book').trigger('click'); // Re-analyze
                                } else {
                                    alert('Merge failed: ' + response.data);
                                    $btn.text('Merge Selected').prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('Network error.');
                                $btn.text('Merge Selected').prop('disabled', false);
                            }
                        });
                    }
                });

                // Cancel Handlers
                $(document).on('click', '.fmb-cancel-order-btn', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('id');
                    
                    if (confirm('Are you sure you want to completely cancel this order?')) {
                        var $btn = $(this);
                        $btn.text('Canceling...').prop('disabled', true);
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'fmb_engine_cancel_order_ajax',
                                order_id: orderId,
                                _wpnonce: steadfast_nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Uncheck the cancelled order from the main table so it's excluded from analysis
                                    $('input[value="' + orderId + '"]').prop('checked', false);
                                    $('#fmb-visible-bulk-book').trigger('click'); // Re-analyze
                                } else {
                                    alert('Cancel failed: ' + response.data);
                                    $btn.text('❌ Cancel').prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('Network error.');
                                $btn.text('❌ Cancel').prop('disabled', false);
                            }
                        });
                    }
                });

                $(document).on('click', '#fmb-sba-confirm-btn', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    
                    var finalOrders = [];
                    $('.fmb-order-cb:checked').each(function() {
                        finalOrders.push($(this).val());
                    });

                    if (finalOrders.length === 0) return;

                    $btn.prop('disabled', true).addClass('booking-in-progress');
                    $('.fmb-modal-close').prop('disabled', true);
                    $('.fmb-order-cb').prop('disabled', true);
                    
                    var total = finalOrders.length;
                    var current = 0;
                    var successCount = 0;
                    var failedCount = 0;
                    
                    $('#fmb-sba-progress-text').show();

                    function processNext() {
                        if (current >= total) {
                            $btn.text('Done!').removeClass('booking-in-progress');
                            $('.fmb-modal-close').prop('disabled', false).text('Close & Reload');
                            
                            $('#fmb-sba-progress-text').html(`<span style="color:#10b981;"><b>Completed!</b> Success: ${successCount}, Failed: ${failedCount}</span>`);
                            $('#fmb-sba-body').prepend(`
                                <div style="background:#e0f2fe; border:1px solid #bae6fd; padding:16px; border-radius:8px; margin-bottom:16px; text-align:center;">
                                    <h3 style="color:#0369a1; margin:0 0 8px 0;">Booking Complete</h3>
                                    <p style="margin:0; font-size:14px; color:#0c4a6e;">You can now close this window and the page will reload.</p>
                                </div>
                            `);
                            return;
                        }
                        
                        var orderId = finalOrders[current];
                        $('#fmb-sba-progress-text').text(`Booking ${current + 1} of ${total} (Order ID: ${orderId})...`);
                        $btn.text(`${Math.round(((current) / total) * 100)}%`);
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'GET',
                            data: {
                                action: 'fmb_engine_send_steadfast',
                                order_id: orderId,
                                _wpnonce: steadfast_nonce
                            },
                            success: function(response) {
                                if (response.success) { successCount++; } else { failedCount++; }
                            },
                            error: function() { failedCount++; },
                            complete: function() {
                                current++;
                                processNext();
                            }
                        });
                    }
                    
                    processNext();
                });
                
                // Handle close reload if finished
                $(document).on('click', '.fmb-modal-close', function() {
                    if ($(this).text() === 'Close & Reload') {
                        location.reload();
                    }
                });

                $(document).on('click', '.trigger-courier-sync', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    
                    if ($button.hasClass('processing')) return;
                    
                    var targetUrl = $button.attr('href');
                    $button.addClass('processing').css('opacity', '0.5');

                    $.ajax({
                        url: targetUrl,
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                // Add success visual
                                var $parent = $button.closest('td');
                                $parent.html('<span style="display:inline-flex; align-items:center; padding:5px 12px; background:#dcfce7; color:#166534; border-radius:6px; font-weight:600; font-size:13px;">Sent</span>');
                                
                                // Also update adjacent columns
                                $parent.next('td.column-ads_courier_consignment').html('<span style="font-size:13px; font-weight:500; color:#334155;">' + response.data.consignment_id + '</span>');
                                $parent.next().next('td.column-ads_courier_status').html('<span style="display:inline-block; padding:4px 10px; border-radius:6px; font-weight:600; font-size:13px; background:#fef3c7; color:#92400e;">In Review</span>');
                            } else {
                                alert('Error: ' + response.data);
                                $button.removeClass('processing').css('opacity', '1').text('Send');
                            }
                        },
                        error: function() {
                            alert('Network error occurred.');
                            $button.removeClass('processing').css('opacity', '1').text('Send');
                        }
                    });
                });

                $(document).on('click', '.courier-custom-amount-input', function(e) {
                    e.stopPropagation();
                });

                var courierDebounceTimer;
                $(document).on('input change blur', '.courier-custom-amount-input', function(e) {
                    var $input = $(this);
                    
                    clearTimeout(courierDebounceTimer);
                    courierDebounceTimer = setTimeout(function() {
                        var orderId = $input.data('order-id');
                        var nonce = $input.data('nonce');
                        var amount = $input.val();

                        $input.css('border-color', '#cbd5e1').css('opacity', '0.6');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'fmb_engine_save_courier_amount',
                                _ajax_nonce: nonce,
                                order_id: orderId,
                                amount: amount
                            },
                            success: function(response) {
                                console.log("AJAX Save Response:", response);
                                if (response.success) {
                                    $input.css('border-color', '#22c55e').css('opacity', '1');
                                } else {
                                    $input.css('border-color', '#ef4444').css('opacity', '1');
                                    alert("Save failed: " + JSON.stringify(response.data));
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("AJAX Save Error:", xhr.responseText, error);
                                $input.css('border-color', '#ef4444').css('opacity', '1');
                                alert("Network request failed: " + status + " - " + error);
                            }
                        });
                    }, 650);
                });

                $(document).on('click', '.refresh-single-courier-status', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var $btn = $(this);
                    if ($btn.hasClass('spinning')) return;

                    var orderId = $btn.data('order-id');
                    var courier = $btn.data('courier');
                    var consignment = $btn.data('consignment');
                    var nonce = $btn.data('nonce');
                    var $wrapper = $btn.closest('.courier-status-wrapper');

                    $btn.addClass('spinning');
                    $btn.find('.dashicons').css('animation', 'rotation 1s infinite linear');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'refresh_single_courier_status',
                            nonce: nonce,
                            order_id: orderId,
                            courier: courier,
                            consignment: consignment
                        },
                        success: function(response) {
                            $btn.removeClass('spinning');
                            $btn.find('.dashicons').css('animation', 'none');
                            
                            if (response.success) {
                                var badgeHtml = '<span class="courier-status-badge status-updated-flash" style="display:inline-block; padding:4px 10px; border-radius:6px; font-weight:600; font-size:13px; background:' + response.data.bg_color + '; color:' + response.data.text_color + ';">' + response.data.status_clean + '</span>';
                                $wrapper.find('.courier-status-badge').replaceWith(badgeHtml);
                                
                                if (response.data.remark) {
                                    var remarkHtml = '<span style="font-size:12px; color:#475569; display:block; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' + response.data.remark + '"><span class="dashicons dashicons-admin-comments" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#8b5cf6;"></span> ' + response.data.remark + '</span>';
                                    $btn.closest('tr').find('.column-ads_courier_update').html(remarkHtml);
                                }
                            } else {
                                alert('Could not update status: ' + response.data.message);
                            }
                        },
                        error: function() {
                            $btn.removeClass('spinning');
                            $btn.find('.dashicons').css('animation', 'none');
                            alert('Network error while updating status.');
                        }
                    });
                });
            });
        </script>
        <style>
            @keyframes highlightFlash {
                0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); transform: scale(1); }
                50% { transform: scale(1.05); }
                70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
                100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); transform: scale(1); }
            }
            .status-updated-flash {
                animation: highlightFlash 0.8s ease-out;
            }
            input.courier-custom-amount-input::-webkit-outer-spin-button,
            input.courier-custom-amount-input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            input.courier-custom-amount-input[type=number] {
                -moz-appearance: textfield;
            }
        </style>
        <?php
    }

    /**
     * Get customer order sequence history based on phone and IP (HPOS & CPT compatible)
     */
    private function get_customer_order_sequence($order_id, $phone, $ip = null) {
        global $wpdb;
        $order_id = absint($order_id);
        $clean_phone = $this->normalize_phone_number($phone);
        
        $matched_order_ids = [];

        // Match strictly by Phone Number (Last 10 digits)
        // IP matching is intentionally disabled because shared mobile/broadband IPs (CGNAT) falsely group different customers together.
        if (!empty($clean_phone) && strlen($clean_phone) >= 10) {
            $last_10 = substr($clean_phone, -10);

            // Check HPOS Address table
            $hpos_addr_table = $wpdb->prefix . 'wc_order_addresses';
            $hpos_orders_table = $wpdb->prefix . 'wc_orders';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_addr_table}'") === $hpos_addr_table) {
                $hpos_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT a.order_id 
                     FROM {$hpos_addr_table} a
                     JOIN {$hpos_orders_table} o ON a.order_id = o.id
                     WHERE a.address_type = 'billing' 
                       AND a.phone LIKE %s 
                       AND a.order_id != %d
                       AND o.status NOT IN ('trash', 'auto-draft', 'checkout-draft')",
                    '%' . $wpdb->esc_like($last_10),
                    $order_id
                ));
                if (!empty($hpos_ids)) {
                    $matched_order_ids = array_merge($matched_order_ids, $hpos_ids);
                }
            }

            // Check Classic CPT Postmeta table
            $cpt_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT pm.post_id 
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_billing_phone' 
                   AND pm.meta_value LIKE %s 
                   AND pm.post_id != %d
                   AND p.post_type = 'shop_order'
                   AND p.post_status NOT IN ('trash', 'auto-draft')",
                '%' . $wpdb->esc_like($last_10),
                $order_id
            ));
            if (!empty($cpt_ids)) {
                $matched_order_ids = array_merge($matched_order_ids, $cpt_ids);
            }
        }

        $matched_order_ids = array_unique(array_filter(array_map('absint', $matched_order_ids)));

        // Filter only orders placed BEFORE the current order ID
        $prev_order_ids = array_filter($matched_order_ids, function($p_id) use ($order_id) {
            return $p_id > 0 && $p_id < $order_id;
        });

        $prev_count = count($prev_order_ids);
        $confirmed = 0;
        $cancelled = 0;

        foreach ($prev_order_ids as $p_id) {
            $p_order = wc_get_order($p_id);
            if ($p_order) {
                $st = $p_order->get_status();
                if (in_array($st, ['processing', 'completed', 'on-hold', 'partial-paid'])) {
                    $confirmed++;
                } elseif (in_array($st, ['cancelled', 'failed', 'refunded'])) {
                    $cancelled++;
                }
            }
        }

        return [
            'is_new'       => $prev_count === 0,
            'order_number' => $prev_count + 1,
            'count'        => $prev_count,
            'confirmed'    => $confirmed,
            'cancelled'    => $cancelled
        ];
    }
}