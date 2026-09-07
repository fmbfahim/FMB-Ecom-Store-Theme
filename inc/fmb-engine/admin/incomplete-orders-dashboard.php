<?php
namespace fmb_engine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use fmb_engine\Incomplete_Orders_Tracker;

class Incomplete_Orders_Dashboard {
    private $tracker;

    public function __construct() {
        $this->tracker = new Incomplete_Orders_Tracker();
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_ads_get_analytics_data', [$this, 'get_analytics_data']);
        add_action('wp_ajax_ads_get_visionary_data', [$this, 'get_visionary_data']);
    }

    public function enqueue_scripts($hook) {
        $is_fmb_engine_page = strpos($hook, 'fmb-engine') !== false;
        $is_legacy_dashboard = isset($_GET['page']) && $_GET['page'] === 'ads-incomplete-analytics';
        $is_wc_orders_page = ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') || $hook === 'woocommerce_page_wc-orders';
        
        if (!$is_fmb_engine_page && !$is_wc_orders_page && !$is_legacy_dashboard) {
            return;
        }

        wp_enqueue_style('ads-admin-css', FMB_ENGINE_URL . 'assets/css/admin.css', [], FMB_THEME_VERSION);

        if ($hook === 'toplevel_page_FMB Engine' || $is_legacy_dashboard || strpos($hook, 'fmb-engine-incomplete-orders') !== false) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
            wp_enqueue_script('ads-analytics-js', FMB_ENGINE_URL . 'assets/js/analytics.js', ['jquery', 'chartjs'], time(), true);
            wp_localize_script('ads-analytics-js', 'ads_analytics', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ads_analytics_nonce'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'date_format' => get_option('date_format'),
            ]);

            // Lucide Icons
            wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js', array(), null, true);
            wp_add_inline_script('lucide-icons', 'lucide.createIcons();');
        }
    }

    public function render_dashboard() {
        ?>
        <div class="wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; position: relative; margin-top: 20px; margin-left: 20px;">
            <div style="padding: 30px; background: #fafafa; border-radius: 15px; min-height: 80vh; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;" class="of-dashboard-header">
                    <h2 style="font-size: 28px; font-weight: 600; font-family: 'Poppins', sans-serif; color: #000; margin: 0;">FMB Engine Analytics</h2>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <!-- Live Sync Indicator -->
                        <div class="ofls-live-sync-container" style="display: flex; align-items: center; gap: 8px; background: white; padding: 0 16px; height: 42px; box-sizing: border-box; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <div style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); animation: pulse 2s infinite;"></div>
                            <span style="font-size: 13px; font-weight: 600; color: #475569;">Live Sync</span>
                            <span style="font-size: 12px; color: #94a3b8; border-left: 1px solid #e2e8f0; padding-left: 8px; margin-left: 4px; white-space: nowrap;"><?php echo wp_date('M d, Y h:i A'); ?></span>
                            <button id="ads-live-sync-update" style="background: #1a7278; border: none; padding: 6px; margin-left: 6px; border-radius: 6px; cursor: pointer; color: white; transition: background 0.2s; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(26, 114, 120, 0.2);" title="Update Data">
                                <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                            </button>
                        </div>
                        
                        <!-- Package Button -->
                        <?php 
                        $license_data = get_option('my_plugin_license_data', []);
                        $package_name = !empty($license_data['type']) ? $license_data['type'] : 'Premium Package';
                        ?>
                        <div style="display: flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #1a7278 0%, #125458 100%); color: white; padding: 0 16px; height: 42px; box-sizing: border-box; border-radius: 8px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(26, 114, 120, 0.2);">
                            <i data-lucide="crown" style="width: 16px; height: 16px;"></i>
                            <?php echo esc_html($package_name); ?>
                        </div>
                    </div>
                </div>

                <!-- Date Filter Bar -->
                <?php
                $today_date = date('Y-m-d');
                $week_date = date('Y-m-d', strtotime('-7 days'));
                $month_date = date('Y-m-d', strtotime('-30 days'));
                
                $selected_preset = isset($_GET['date_preset']) ? sanitize_text_field($_GET['date_preset']) : '30_days';
                
                if (isset($_GET['from']) && isset($_GET['to'])) {
                    $from = sanitize_text_field($_GET['from']);
                    $to = sanitize_text_field($_GET['to']);
                } else {
                    $to = $today_date;
                    if ($selected_preset === 'today') {
                        $from = $today_date;
                    } elseif ($selected_preset === '7_days') {
                        $from = $week_date;
                    } else {
                        $from = $month_date;
                    }
                }
                ?>
                <form id="ads-hidden-date-form" method="get" action="" class="ads-hidden-date-form-container" style="display: flex; gap: 16px; align-items: center; margin-bottom: 24px; background: white; padding: 16px 24px; border-radius: 12px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <input type="hidden" name="page" value="FMB Engine" />
                    
                    <input type="hidden" name="from" id="ads_hidden_from" value="<?php echo esc_attr($from); ?>" />
                    <input type="hidden" name="to" id="ads_hidden_to" value="<?php echo esc_attr($to); ?>" />
                    
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label for="ads_date_preset" style="font-weight: 600; color: #475569; font-size: 14px;">Date Range</label>
                        <select name="date_preset" id="ads_date_preset" style="height: 38px; box-sizing: border-box; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #1e293b; outline: none; margin: 0; min-width: 150px;">
                            <option value="today" <?php selected($selected_preset, 'today'); ?>>Today</option>
                            <option value="7_days" <?php selected($selected_preset, '7_days'); ?>>This Week</option>
                            <option value="30_days" <?php selected($selected_preset, '30_days'); ?>>Last 30 Days</option>
                        </select>
                    </div>

                    <button type="submit" style="height: 38px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; background: #1a7278; color: white; border: none; padding: 0 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 4px rgba(26, 114, 120, 0.2); margin: 0;">
                        Filter Data
                    </button>
                    
                    <script>
                        document.getElementById('ads_date_preset').addEventListener('change', function() {
                            const today = '<?php echo $today_date; ?>';
                            const week = '<?php echo $week_date; ?>';
                            const month = '<?php echo $month_date; ?>';
                            
                            const val = this.value;
                            const fromInput = document.getElementById('ads_hidden_from');
                            const toInput = document.getElementById('ads_hidden_to');
                            
                            toInput.value = today;
                            
                            if (val === 'today') {
                                fromInput.value = today;
                            } else if (val === '7_days') {
                                fromInput.value = week;
                            } else if (val === '30_days') {
                                fromInput.value = month;
                            }
                        });
                    </script>
                </form>
                
                <style>
                @keyframes pulse {
                    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
                    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                }
                @keyframes spin {
                    100% { transform: rotate(360deg); }
                }
                @media (max-width: 1024px) {
                    .of-grid-4 { grid-template-columns: repeat(2, 1fr) !important; }
                    .of-grid-2-1 { grid-template-columns: 1fr !important; }
                    .of-grid-3 { grid-template-columns: 1fr !important; }
                }
                @media (max-width: 768px) {
                    .of-grid-4 { grid-template-columns: 1fr !important; }
                    .of-dashboard-header { flex-direction: column; align-items: flex-start !important; gap: 15px; }
                    .ads-hidden-date-form-container { flex-direction: column; align-items: stretch !important; }
                    .ads-hidden-date-form-container button { width: 100%; }
                    .of-dashboard-footer-links { flex-direction: column; }
                    .of-dashboard-footer-link { border-right: none !important; border-bottom: 1px dashed #cbd5e1; }
                    .of-dashboard-footer-link:last-child { border-bottom: none; }
                    .ofls-live-sync-container { height: auto !important; padding: 10px 16px !important; flex-wrap: wrap; }
                }
                </style>
                
                <div class="of-grid-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 30px;">
                    <!-- 1. Total Incomplete Orders -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #1a7278; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Total Incomplete Orders</div>
                            <div id="visionary-total-incomplete" style="font-size: 32px; font-weight: 700; color: #1a7278;">...</div>
                        </div>
                        <i data-lucide="shopping-cart" style="color: #1a7278; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 2. Incomplete Recovery Amount -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #10b981; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Recovery Amount</div>
                            <div id="visionary-recovery-amount" style="font-size: 32px; font-weight: 700; color: #10b981;">...</div>
                        </div>
                        <i data-lucide="dollar-sign" style="color: #10b981; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 3. Fraud Blocks -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #ef4444; display: flex; align-items: center; justify-content: space-between;">
                        <div style="flex: 1;">
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Fraud Blocks</div>
                            <div style="display: flex; gap: 20px; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 6px;" title="Phone Blocks">
                                    <i data-lucide="phone-off" style="width: 16px; height: 16px; color: #ef4444; opacity: 0.8;"></i>
                                    <span id="visionary-fraud-phone" style="font-size: 24px; font-weight: 700; color: #ef4444;">...</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;" title="IP/Device Blocks">
                                    <i data-lucide="monitor-off" style="width: 16px; height: 16px; color: #ef4444; opacity: 0.8;"></i>
                                    <span id="visionary-fraud-ip" style="font-size: 24px; font-weight: 700; color: #ef4444;">...</span>
                                </div>
                            </div>
                        </div>
                        <i data-lucide="shield-alert" style="color: #ef4444; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 4. Active Pixels -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #8b5cf6; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Active Pixels</div>
                            <div id="visionary-active-pixels" style="font-size: 32px; font-weight: 700; color: #8b5cf6;">...</div>
                        </div>
                        <i data-lucide="activity" style="color: #8b5cf6; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 5. Cancel Order -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #f59e0b; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Cancel Orders</div>
                            <div id="visionary-cancel-orders" style="font-size: 32px; font-weight: 700; color: #f59e0b;">...</div>
                        </div>
                        <i data-lucide="x-circle" style="color: #f59e0b; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 6. Total Returned Parcels -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #3b82f6; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Returned Parcels</div>
                            <div id="visionary-returned-parcels" style="font-size: 32px; font-weight: 700; color: #3b82f6;">...</div>
                        </div>
                        <i data-lucide="package-minus" style="color: #3b82f6; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 7. Estimated Return Cost -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #e11d48; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Return Loss/Cost</div>
                            <div id="visionary-return-cost" style="font-size: 32px; font-weight: 700; color: #e11d48;">...</div>
                        </div>
                        <i data-lucide="trending-down" style="color: #e11d48; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                    <!-- 8. Total SMS Sent -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; border-left: 4px solid #0ea5e9; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="color: #64748b; font-size: 14px; font-weight: 500; margin-bottom: 8px;">Total SMS Sent</div>
                            <div id="visionary-otp-sent" style="font-size: 32px; font-weight: 700; color: #0ea5e9;">...</div>
                        </div>
                        <i data-lucide="message-square" style="color: #0ea5e9; width: 40px; height: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
                <!-- Row 3: Traffic & Conversion Insights and Recent Activity -->
                <div class="of-grid-2-1" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 30px;">
                    <!-- Traffic & Conversion Insights -->
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; min-height: 350px; display: flex; flex-direction: column;">
                        <h3 style="margin-top:0; font-size: 18px; color: #1e293b; font-weight: 500; margin-bottom: 24px;">Traffic & Conversion Insights</h3>
                        
                        <div class="of-grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; flex-grow: 1;">
                            
                            <!-- Traffic Sources -->
                            <div style="background: white; border-radius: 10px; padding: 16px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="users" style="width: 16px; height: 16px; color: #3b82f6;"></i>
                                    Traffic Sources (Orders)
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(24, 119, 242, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #1877f2;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                            <span style="font-size: 13px; color: #1877f2; font-weight: 500;">Facebook</span>
                                        </div>
                                        <span id="vis-traffic-fb" style="font-size: 14px; font-weight: 700; color: #1877f2;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0, 0, 0, 0.05); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #0f172a;"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                                            <span style="font-size: 13px; color: #0f172a; font-weight: 500;">TikTok</span>
                                        </div>
                                        <span id="vis-traffic-tt" style="font-size: 14px; font-weight: 700; color: #0f172a;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(234, 67, 53, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #ea4335;"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                                            <span style="font-size: 13px; color: #ea4335; font-weight: 500;">Google</span>
                                        </div>
                                        <span id="vis-traffic-google" style="font-size: 14px; font-weight: 700; color: #ea4335;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(16, 185, 129, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i data-lucide="link" style="width: 14px; height: 14px; color: #10b981;"></i>
                                            <span style="font-size: 13px; color: #10b981; font-weight: 500;">Direct Order</span>
                                        </div>
                                        <span id="vis-traffic-direct" style="font-size: 14px; font-weight: 700; color: #10b981;">...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Courier Order Status -->
                            <div style="background: white; border-radius: 10px; padding: 16px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="truck" style="width: 16px; height: 16px; color: #f59e0b;"></i>
                                    Courier Order Status
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(99, 102, 241, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i data-lucide="send" style="width: 14px; height: 14px; color: #6366f1;"></i>
                                            <span id="vis-courier-name-label" style="font-size: 13px; color: #6366f1; font-weight: 500;">Courier Sent</span>
                                        </div>
                                        <span id="vis-courier-sent" style="font-size: 14px; font-weight: 700; color: #6366f1;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(16, 185, 129, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i data-lucide="check-circle" style="width: 14px; height: 14px; color: #10b981;"></i>
                                            <span style="font-size: 13px; color: #10b981; font-weight: 500;">Delivered Order</span>
                                        </div>
                                        <span id="vis-courier-delivered" style="font-size: 14px; font-weight: 700; color: #10b981;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(245, 158, 11, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i data-lucide="clock" style="width: 14px; height: 14px; color: #f59e0b;"></i>
                                            <span style="font-size: 13px; color: #f59e0b; font-weight: 500;">Pending Order</span>
                                        </div>
                                        <span id="vis-courier-pending" style="font-size: 14px; font-weight: 700; color: #f59e0b;">...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- CAPI Success Events -->
                            <div style="background: white; border-radius: 10px; padding: 16px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="server" style="width: 16px; height: 16px; color: #10b981;"></i>
                                    Purchase Events (CAPI)
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(24, 119, 242, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #1877f2;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                            <span style="font-size: 13px; color: #1877f2; font-weight: 500;">FB Sent</span>
                                        </div>
                                        <span id="vis-capi-fb" style="font-size: 14px; font-weight: 700; color: #1877f2;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0, 0, 0, 0.05); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #0f172a;"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                                            <span style="font-size: 13px; color: #0f172a; font-weight: 500;">TikTok Sent</span>
                                        </div>
                                        <span id="vis-capi-tt" style="font-size: 14px; font-weight: 700; color: #0f172a;">...</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(234, 67, 53, 0.08); padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color: #ea4335;"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                                            <span style="font-size: 13px; color: #ea4335; font-weight: 500;">Google Sent</span>
                                        </div>
                                        <span id="vis-capi-google" style="font-size: 14px; font-weight: 700; color: #ea4335;">...</span>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; min-height: 350px;">
                        <h3 style="margin-top:0; font-size: 18px; color: #1e293b; font-weight: 500;">Recent Activity</h3>
                        <ul id="vis-recent-activities" style="list-style: none; padding: 0; margin: 24px 0 0 0; max-height: 230px; overflow-y: auto; overflow-x: hidden; scrollbar-width: thin; padding-right: 8px;">
                            <li style="margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;"><div style="width: 85%; height: 14px; background: #e2e8f0; border-radius: 6px;"></div></li>
                            <li style="margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;"><div style="width: 65%; height: 14px; background: #e2e8f0; border-radius: 6px;"></div></li>
                            <li style="margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;"><div style="width: 95%; height: 14px; background: #e2e8f0; border-radius: 6px;"></div></li>
                            <li style="margin-bottom: 20px;"><div style="width: 75%; height: 14px; background: #e2e8f0; border-radius: 6px;"></div></li>
                        </ul>
                    </div>
                </div>

                <!-- Footer Links Box -->
                <style>
                    .of-dashboard-footer-links-wrapper {
                        margin-top: 24px;
                        padding: 1px;
                        border-radius: 13px;
                        background: linear-gradient(90deg, #ea4335, #fbbc05, #34a853, #4285f4);
                    }
                    .of-dashboard-footer-links {
                        display: flex;
                        background: #f8fafc;
                        border-radius: 12px;
                        overflow: hidden;
                    }
                    .of-dashboard-footer-link {
                        flex: 1;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                        padding: 20px;
                        font-size: 13px;
                        font-weight: 600;
                        color: #64748b;
                        text-transform: uppercase;
                        text-decoration: none;
                        border-right: 1px dashed #cbd5e1;
                        transition: all 0.2s;
                    }
                    .of-dashboard-footer-link:last-child {
                        border-right: none;
                    }
                    .of-dashboard-footer-link:hover {
                        color: #1a7278;
                        background: #f1f5f9;
                    }
                    .of-dashboard-footer-link svg {
                        width: 16px;
                        height: 16px;
                    }
                </style>
                <div class="of-dashboard-footer-links-wrapper">
                    <div class="of-dashboard-footer-links">
                    <a href="https://youtube.com/playlist?list=PLnm9iWq4vOTK71U93Zf3roBRrIMj-MT-R&si=97pSs7A1UmgamNCh" target="_blank" class="of-dashboard-footer-link" style="color: #ea4335;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                        VIDEO TUTORIAL
                    </a>
                    <a href="https://FMB Enginebd.com/fraud-checker/" target="_blank" class="of-dashboard-footer-link" style="color: #1a7278;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><path d="M7 12h10"></path></svg>
                        FRAUD CHECK
                    </a>
                    <a href="https://wa.me/8801777231474" target="_blank" class="of-dashboard-footer-link" style="color: #10b981;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
                        LIVE SUPPORT
                    </a>
                </div>
                </div>
            </div>

        </div>
        <?php
    }

    public function get_analytics_data() {
        check_ajax_referer('ads_analytics_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : date('Y-m-d', strtotime('-30 days'));
        $to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : date('Y-m-d');

        // Get analytics data from tracker
        $analytics = $this->tracker->get_analytics($from, $to);

        // Get incomplete orders by day
        $incomplete_by_day = $this->get_incomplete_orders_by_day($from, $to);

        // Get orders by status
        $orders_by_status = $this->get_orders_by_status($from, $to);

        // Get top recovery days
        $top_recovery_days = $this->get_top_recovery_days($from, $to);

        // Calculate total recovery orders (sum of all non-incomplete statuses)
        $total_recovery_orders = 0;
        foreach ($orders_by_status['data'] as $key => $count) {
            $status = $orders_by_status['labels'][$key];
            if ($status !== 'Incomplete') {
                $total_recovery_orders += $count;
            }
        }

        // Get incomplete count
        $incomplete_count = $orders_by_status['incomplete'] ?? 0;

        wp_send_json_success([
            'total_incomplete' => $incomplete_count,
            'total_recovery_orders' => $total_recovery_orders,
            'recovery_rate' => $analytics['recovery_rate'],
            'recovery_amount' => $analytics['recovery_amount'],
            'incomplete_by_day' => $incomplete_by_day,
            'orders_by_status' => $orders_by_status,
            'top_recovery_days' => $top_recovery_days
        ]);
    }

    private function get_incomplete_orders_by_day($from, $to) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        // Direct count from tracker table, NO join for purely incomplete orders.
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(id) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            AND status_to = 'ads-incomplete'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $from . ' 00:00:00', $to . ' 23:59:59'));

        $data = [
            'labels' => [],
            'data' => []
        ];

        // Fill missing dates
        $period = new \DatePeriod(
            new \DateTime($from),
            new \DateInterval('P1D'),
            new \DateTime($to . ' +1 day')
        );

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->date] = $row->count;
        }

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');
            $data['labels'][] = $date;
            $data['data'][] = $counts[$date] ?? 0;
        }

        return $data;
    }

    private function get_orders_by_status($from, $to) {
        global $wpdb;

        // নতুন স্ট্যাটাস ads-recovered যোগ করা হয়েছে
        $statuses = [
            'ads-incomplete' => 'incomplete',
            'ads-recovered' => 'recovered', // পরিবর্তন: ads-purchase থেকে ads-recovered
            'processing' => 'pending',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            'refunded' => 'refunded'
        ];

        $data = [
            'labels' => [],
            'data' => [],
            'backgroundColors' => [],
            'incomplete' => 0
        ];

        // নতুন স্ট্যাটাসের জন্য রঙ যোগ করা হয়েছে
        $colors = [
            'ads-incomplete' => '#FF6384',
            'ads-recovered' => '#36A2EB', // পরিবর্তন: ads-purchase থেকে ads-recovered
            'processing' => '#36A2EB',
            'completed' => '#4BC0C0',
            'cancelled' => '#FFCE56',
            'failed' => '#FF6384',
            'refunded' => '#9966FF'
        ];

        $tracker_table = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $join = $hpos_enabled 
            ? "INNER JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id" 
            : "INNER JOIN {$wpdb->posts} p ON t.order_id = p.ID AND p.post_type = 'shop_order'";

        foreach ($statuses as $status => $label) {
            if (in_array($status, ['ads-incomplete', 'ads-recovered'])) { // পরিবর্তন: ads-purchase থেকে ads-recovered
                $date_col = $status === 'ads-recovered' ? 't.recovered_at' : 't.created_at';
                
                // Only join for recovered status to verify they are legitimate WP/WC orders
                $current_join = ($status === 'ads-recovered') ? $join : '';
                
                // Get counts from tracker table for custom statuses
                $sql_query = $wpdb->prepare("
                    SELECT COUNT(DISTINCT t.order_id) 
                    FROM $tracker_table t
                    $current_join
                    WHERE t.status_to = %s
                    AND $date_col BETWEEN %s AND %s
                ", $status, $from . ' 00:00:00', $to . ' 23:59:59');
                
                $count = $wpdb->get_var($sql_query);
            } else {
                // Use WooCommerce function with proper querying
                $wc_status = 'wc-' . $status;
                if ($hpos_enabled) {
                    $table_orders = $wpdb->prefix . 'wc_orders';
                    $count = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(id) FROM $table_orders
                        WHERE status = %s AND date_created_gmt >= %s AND date_created_gmt <= %s
                    ", $wc_status, $from . ' 00:00:00', $to . ' 23:59:59'));
                } else {
                    $count = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(ID) FROM {$wpdb->posts}
                        WHERE post_type = 'shop_order' AND post_status = %s
                        AND post_date >= %s AND post_date <= %s
                    ", $wc_status, $from . ' 00:00:00', $to . ' 23:59:59'));
                }
            }

            // Only include incomplete and recovery statuses in the chart
            if ($status === 'ads-incomplete' || $status === 'ads-recovered') { // পরিবর্তন: ads-purchase থেকে ads-recovered
                $data['labels'][] = ucfirst($label);
                $data['data'][] = $count;
                $data['backgroundColors'][] = $colors[$status];
            }

            if ($status === 'ads-incomplete') {
                $data['incomplete'] = $count;
            }
        }

        return $data;
    }

    private function get_top_recovery_days($from, $to) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $join = $hpos_enabled 
            ? "INNER JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id" 
            : "INNER JOIN {$wpdb->posts} p ON t.order_id = p.ID AND p.post_type = 'shop_order'";

        // পরিবর্তন: শুধুমাত্র ads-recovered স্ট্যাটাসের জন্য ডেটা নেওয়া হচ্ছে
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(t.recovered_at) as recovery_date, 
                   COUNT(t.id) as recovered_carts,
                   SUM(t.order_total) as recovered_revenue
            FROM $table_name t
            $join
            WHERE t.recovered_at BETWEEN %s AND %s
            AND t.status_to = 'ads-recovered' -- পরিবর্তন: status_to != 'ads-incomplete' থেকে status_to = 'ads-recovered'
            GROUP BY DATE(t.recovered_at)
            ORDER BY recovered_carts DESC, recovered_revenue DESC
            LIMIT 10
        ", $from . ' 00:00:00', $to . ' 23:59:59'));

        return $results;
    }

    public function get_visionary_data() {
        check_ajax_referer('ads_analytics_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : date('Y-m-d', strtotime('-30 days'));
        $to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : date('Y-m-d');
        $force = isset($_POST['force']) && $_POST['force'] === 'true';
        
        $cache_key = 'of_visionary_stats_' . md5($from . $to);
        $cached_data = $force ? false : get_transient($cache_key);

        if (false === $cached_data) {
            global $wpdb;

            // 1. Incomplete Order Revenue (from recovered analytics)
            $analytics = $this->tracker->get_analytics($from, $to);
            
            // Check HPOS
            $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

            if ($hpos_enabled) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

                // Real Recovery Amount
                $real_recovery_amount = $wpdb->get_var("SELECT SUM(meta_value) FROM {$orders_meta_table} meta JOIN {$orders_table} o ON o.id = meta.order_id WHERE o.status = 'wc-ads-recovered' AND meta.meta_key = '_order_total' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;

                // Return Parcels
                $returns_tracker = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'wc-returned' AND date_created_gmt >= '$from' AND date_created_gmt <= '$to 23:59:59'");
                $courier_returns = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_meta_table} meta JOIN {$orders_table} o ON o.id = meta.order_id WHERE meta.meta_key = 'ads_courier_status' AND meta.meta_value = 'Returned' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'");
                $return_tracker_count = max((int)$returns_tracker, (int)$courier_returns);

                // Financials
                $total_sell_amount = $wpdb->get_var("SELECT SUM(meta_value) FROM {$orders_meta_table} meta JOIN {$orders_table} o ON o.id = meta.order_id WHERE o.status = 'wc-completed' AND meta.meta_key = '_order_total' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $total_cancel_amount = $wpdb->get_var("SELECT SUM(meta_value) FROM {$orders_meta_table} meta JOIN {$orders_table} o ON o.id = meta.order_id WHERE o.status = 'wc-cancelled' AND meta.meta_key = '_order_total' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $total_return_amount = $wpdb->get_var("SELECT SUM(m_total.meta_value) FROM {$orders_meta_table} m_total JOIN {$orders_table} o ON o.id = m_total.order_id LEFT JOIN {$orders_meta_table} m_courier ON o.id = m_courier.order_id AND m_courier.meta_key = 'ads_courier_status' WHERE m_total.meta_key = '_order_total' AND (o.status = 'wc-refunded' OR o.status = 'wc-returned' OR m_courier.meta_value = 'Returned') AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;

                // Orders Split
                $count_completed = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'wc-completed' AND date_created_gmt >= '$from' AND date_created_gmt <= '$to 23:59:59'") ?: 0;
                $count_canceled = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'wc-cancelled' AND date_created_gmt >= '$from' AND date_created_gmt <= '$to 23:59:59'") ?: 0;
                $count_processing = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'wc-processing' AND date_created_gmt >= '$from' AND date_created_gmt <= '$to 23:59:59'") ?: 0;
                $count_on_hold = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'wc-on-hold' AND date_created_gmt >= '$from' AND date_created_gmt <= '$to 23:59:59'") ?: 0;
            } else {
                // Real Recovery Amount
                $real_recovery_amount = $wpdb->get_var("SELECT SUM(pm.meta_value) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-ads-recovered' AND pm.meta_key = '_order_total' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;

                // Return Parcels
                $returns_tracker = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-returned' AND post_date >= '$from' AND post_date <= '$to 23:59:59'");
                $courier_returns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = 'ads_courier_status' AND pm.meta_value = 'Returned' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'");
                $return_tracker_count = max((int)$returns_tracker, (int)$courier_returns);

                // Financials
                $total_sell_amount = $wpdb->get_var("SELECT SUM(pm.meta_value) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND pm.meta_key = '_order_total' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $total_cancel_amount = $wpdb->get_var("SELECT SUM(pm.meta_value) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-cancelled' AND pm.meta_key = '_order_total' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $total_return_amount = $wpdb->get_var("SELECT SUM(pm_total.meta_value) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id LEFT JOIN {$wpdb->postmeta} pm_courier ON p.ID = pm_courier.post_id AND pm_courier.meta_key = 'ads_courier_status' WHERE p.post_type = 'shop_order' AND pm_total.meta_key = '_order_total' AND (p.post_status = 'wc-refunded' OR p.post_status = 'wc-returned' OR pm_courier.meta_value = 'Returned') AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;

                // Orders Split
                $count_completed = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-completed' AND post_date >= '$from' AND post_date <= '$to 23:59:59'") ?: 0;
                $count_canceled = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-cancelled' AND post_date >= '$from' AND post_date <= '$to 23:59:59'") ?: 0;
                $count_processing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-processing' AND post_date >= '$from' AND post_date <= '$to 23:59:59'") ?: 0;
                $count_on_hold = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-on-hold' AND post_date >= '$from' AND post_date <= '$to 23:59:59'") ?: 0;
            }
            $total_orders = $count_completed + $count_canceled + $count_processing + $count_on_hold;

            // 2. Facebook CAPI (Global)
            $capi_success = get_option('of_fb_capi_success_count', 0);

            // 3. Fraud Blocks
            $fraud_table = $wpdb->prefix . 'ads_customers_data';
            $fraud_blocks_phone = 0;
            $fraud_blocks_ip = 0;
            if ($wpdb->get_var("SHOW TABLES LIKE '$fraud_table'") == $fraud_table) {
                $fraud_blocks_phone = (int) $wpdb->get_var("SELECT COUNT(*) FROM $fraud_table WHERE data_access = 'blocked' AND data_type IN ('phone_number', 'phone') AND created_at >= '$from 00:00:00' AND created_at <= '$to 23:59:59'");
                $fraud_blocks_ip = (int) $wpdb->get_var("SELECT COUNT(*) FROM $fraud_table WHERE data_access = 'blocked' AND data_type IN ('ip_address', 'device_hash', 'ip') AND created_at >= '$from 00:00:00' AND created_at <= '$to 23:59:59'");
            }

            // 7. Top Products
            $top_products = $wpdb->get_results("
                SELECT order_item_name as name, COUNT(order_item_id) as count
                FROM {$wpdb->prefix}woocommerce_order_items
                WHERE order_item_type = 'line_item'
                GROUP BY order_item_name
                ORDER BY count DESC
                LIMIT 5
            ");

            // 8. Daily Sales Trend
            $sales_trend_raw = $wpdb->get_results("
                SELECT DATE(p.post_date) as date, SUM(pm.meta_value) as revenue
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND pm.meta_key = '_order_total'
                AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'
                GROUP BY DATE(p.post_date)
                ORDER BY DATE(p.post_date) ASC
            ");

            // Pad missing dates
            $period = new \DatePeriod(
                new \DateTime($from),
                new \DateInterval('P1D'),
                new \DateTime($to . ' +1 day')
            );
            $sales_trend_mapped = [];
            foreach ($sales_trend_raw as $row) {
                $sales_trend_mapped[$row->date] = $row->revenue;
            }
            $sales_trend = ['labels' => [], 'data' => []];
            foreach ($period as $day) {
                $date = $day->format('Y-m-d');
                $sales_trend['labels'][] = $date;
                $sales_trend['data'][] = $sales_trend_mapped[$date] ?? 0;
            }

            // Total Incomplete Count
            $total_incomplete_count = $analytics['created'] ?? 0;

            // Active Pixels
            $active_pixels = [
                'facebook' => false,
                'tiktok' => false,
                'google' => false
            ];
            
            if (class_exists('\fmb_engine\Facebook\Facebook')) {
                $facebook = \fmb_engine\Facebook\Facebook::instance();
                if ($facebook->enabled() || !empty($facebook->getPixelIDs())) {
                    $active_pixels['facebook'] = true;
                }
            } else {
                $fb_options = get_option('fmb_engine_facebook', []);
                if (!empty($fb_options['fmb_engine_enabled'])) {
                    $active_pixels['facebook'] = true;
                }
            }
            if (get_option('ofls_tiktok_enabled', false)) {
                $active_pixels['tiktok'] = true;
            }
            if (get_option('ofls_google_enabled', false) || get_option('ofls_ga_enabled', false)) {
                $active_pixels['google'] = true;
            }

            // Total SMS Sent
            $total_otp_sent = (int) get_option('ofls_total_outgoing_sms', 0);

            // Traffic & Conversion Insights (Attribution)
            if ($hpos_enabled) {
                $fb_orders = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND (m.meta_value LIKE '%facebook%' OR m.meta_value LIKE '%fb%' OR m.meta_value LIKE '%ig%' OR m.meta_value LIKE '%instagram%') AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $tt_orders = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND m.meta_value LIKE '%tiktok%' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $google_orders = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND (m.meta_value LIKE '%google%' OR m.meta_value LIKE '%adwords%' OR m.meta_value LIKE '%youtube%') AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;

                // CAPI Server Side Successes (Date filtered)
                $fb_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = 'ads_purchase_event_results' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $tt_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = '_tiktok_capi_status' AND m.meta_value = 'success' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $google_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = '_ofls_google_server_processed' AND m.meta_value = 'yes' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                
                $courier_sent = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = 'ads_courier_status' AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $courier_delivered = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = 'ads_courier_status' AND (m.meta_value LIKE '%eliver%') AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
                $courier_pending = $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o JOIN {$orders_meta_table} m ON o.id = m.order_id WHERE m.meta_key = 'ads_courier_status' AND (m.meta_value LIKE '%eview%' OR m.meta_value LIKE '%ending%') AND o.date_created_gmt >= '$from' AND o.date_created_gmt <= '$to 23:59:59'") ?: 0;
            } else {
                $fb_orders = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND (pm.meta_value LIKE '%facebook%' OR pm.meta_value LIKE '%fb%' OR pm.meta_value LIKE '%ig%' OR pm.meta_value LIKE '%instagram%') AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $tt_orders = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND pm.meta_value LIKE '%tiktok%' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $google_orders = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key IN ('_wc_order_attribution_utm_source', '_utm_source') AND (pm.meta_value LIKE '%google%' OR pm.meta_value LIKE '%adwords%' OR pm.meta_value LIKE '%youtube%') AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;

                $fb_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = 'ads_purchase_event_results' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $tt_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = '_tiktok_capi_status' AND pm.meta_value = 'success' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $google_capi_date = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = '_ofls_google_server_processed' AND pm.meta_value = 'yes' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                
                $courier_sent = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = 'ads_courier_status' AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $courier_delivered = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = 'ads_courier_status' AND (pm.meta_value LIKE '%eliver%') AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
                $courier_pending = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type='shop_order' AND pm.meta_key = 'ads_courier_status' AND (pm.meta_value LIKE '%eview%' OR pm.meta_value LIKE '%ending%') AND p.post_date >= '$from' AND p.post_date <= '$to 23:59:59'") ?: 0;
            }

            $direct_orders = max(0, $total_orders - ($fb_orders + $tt_orders + $google_orders));
            
            $active_courier = 'Courier';
            if (get_option('steadfast_enable', false)) {
                $active_courier = 'Steadfast';
            } elseif (get_option('pathao_enable', false)) {
                $active_courier = 'Pathao';
            } elseif (get_option('redx_enable', false)) {
                $active_courier = 'RedX';
            }

            $cached_data = [
                'total_incomplete' => (int)$total_incomplete_count,
                'incomplete_revenue' => $analytics['recovery_amount'] ?? 0,
                'capi_success' => $capi_success,
                'fraud_blocks_phone' => $fraud_blocks_phone,
                'fraud_blocks_ip' => $fraud_blocks_ip,
                'total_orders' => $total_orders,
                'processing' => $count_processing,
                'on_hold' => $count_on_hold,
                'completed' => $count_completed,
                'canceled' => $count_canceled,
                'returned_parcels' => $return_tracker_count,
                'total_sell' => $total_sell_amount,
                'total_cancel' => $total_cancel_amount,
                'total_return' => $total_return_amount,
                'active_pixels' => $active_pixels,
                'total_otp_sent' => $total_otp_sent,
                'top_products' => $top_products,
                'sales_trend' => $sales_trend,
                'traffic_insights' => [
                    'orders_fb' => (int)$fb_orders,
                    'orders_tt' => (int)$tt_orders,
                    'orders_google' => (int)$google_orders,
                    'orders_direct' => (int)$direct_orders,
                    'capi_fb' => (int)$fb_capi_date,
                    'capi_tt' => (int)$tt_capi_date,
                    'capi_google' => (int)$google_capi_date,
                    'courier_name' => $active_courier,
                    'courier_sent' => (int)$courier_sent,
                    'courier_delivered' => (int)$courier_delivered,
                    'courier_pending' => (int)$courier_pending
                ]
            ];

            // Cache heavy queries for 1 hour
            set_transient($cache_key, $cached_data, HOUR_IN_SECONDS);
        }

        // Fast retrieval from WP options (Not query intense, safe to run dynamically)
        $expiry = get_option('ads_license_expiry_date', date('Y-m-d', strtotime('+365 days')));
        $now = current_time('timestamp');
        $expiry_time = strtotime($expiry);
        $days_left = max(0, floor(($expiry_time - $now) / (60 * 60 * 24)));
        $cached_data['license_days'] = $days_left;
        $cached_data['recent_activities'] = get_option('ofls_recent_activities', []);

        wp_send_json_success($cached_data);
    }
}