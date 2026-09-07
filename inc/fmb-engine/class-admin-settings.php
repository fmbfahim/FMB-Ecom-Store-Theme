<?php
/**
 * FMB Engine Admin Settings Panel
 * Integrates FMB Commerce Engine settings into WordPress Admin under FMB Store.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings_menu() {
        add_submenu_page(
            'fmb-store',
            'FMB Engine Settings',
            'FMB Engine',
            'manage_options',
            'fmb-engine-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        // Courier
        register_setting('fmb_engine_opts', 'fmb_bd_courier_api_key');
        register_setting('fmb_engine_opts', 'fmb_steadfast_api_key');
        register_setting('fmb_engine_opts', 'fmb_steadfast_secret_key');

        // Restrictions
        register_setting('fmb_engine_opts', 'fmb_enable_phone_length_validation');
        register_setting('fmb_engine_opts', 'fmb_enable_order_limit');
        register_setting('fmb_engine_opts', 'fmb_max_orders_per_customer');
        register_setting('fmb_engine_opts', 'fmb_enable_vpn_shield');

        // Partial Payment
        register_setting('fmb_engine_opts', 'fmb_enable_partial_payment');
        register_setting('fmb_engine_opts', 'fmb_courier_rate_threshold');
        register_setting('fmb_engine_opts', 'fmb_partial_payment_amount');

        // SMS & OTP
        register_setting('fmb_engine_opts', 'fmb_sms_gateway');
        register_setting('fmb_engine_opts', 'fmb_sms_api_key');
        register_setting('fmb_engine_opts', 'fmb_sms_sender_id');
        register_setting('fmb_engine_opts', 'fmb_enable_checkout_otp');

        // CAPI
        register_setting('fmb_engine_opts', 'fmb_fb_pixel_id');
        register_setting('fmb_engine_opts', 'fmb_fb_capi_access_token');
        register_setting('fmb_engine_opts', 'fmb_tiktok_pixel_id');
        register_setting('fmb_engine_opts', 'fmb_ga4_measurement_id');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap" style="background:#fff; padding:24px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-top:20px;">
            <h1 style="font-size:22px; font-weight:700; color:#1f2937; margin-bottom:20px;">🚀 FMB Commerce Engine Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('fmb_engine_opts');
                do_settings_sections('fmb_engine_opts');
                ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
                    <!-- BD Courier Settings -->
                    <div style="background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb;">
                        <h2 style="font-size:16px; font-weight:600; color:#111827; border-bottom:2px solid #2563eb; padding-bottom:6px;">📦 BD Courier API Settings (bdcourier.com)</h2>
                        <p>
                            <label><strong>BD Courier Check API Key (bdcourier.com):</strong></label><br>
                            <input type="text" name="fmb_bd_courier_api_key" value="<?php echo esc_attr(get_option('fmb_bd_courier_api_key')); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><strong>Steadfast API Key:</strong></label><br>
                            <input type="text" name="fmb_steadfast_api_key" value="<?php echo esc_attr(get_option('fmb_steadfast_api_key')); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><strong>Steadfast Secret Key:</strong></label><br>
                            <input type="text" name="fmb_steadfast_secret_key" value="<?php echo esc_attr(get_option('fmb_steadfast_secret_key')); ?>" class="regular-text">
                        </p>
                    </div>

                    <!-- Restrictions -->
                    <div style="background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb;">
                        <h2 style="font-size:16px; font-weight:600; color:#111827; border-bottom:2px solid #ef4444; padding-bottom:6px;">🛡️ Anti-Fraud & Restrictions</h2>
                        <p>
                            <label><input type="checkbox" name="fmb_enable_phone_length_validation" value="1" <?php checked(get_option('fmb_enable_phone_length_validation', true), 1); ?>> Enable BD 11-digit Phone Format Check</label>
                        </p>
                        <p>
                            <label><input type="checkbox" name="fmb_enable_order_limit" value="1" <?php checked(get_option('fmb_enable_order_limit'), 1); ?>> Enable Max Orders Limit per Customer (24h)</label>
                        </p>
                        <p>
                            <label><strong>Max Orders Allowed in 24 Hours:</strong></label><br>
                            <input type="number" name="fmb_max_orders_per_customer" value="<?php echo esc_attr(get_option('fmb_max_orders_per_customer', 2)); ?>" style="width:80px;">
                        </p>
                        <p>
                            <label><input type="checkbox" name="fmb_enable_vpn_shield" value="1" <?php checked(get_option('fmb_enable_vpn_shield'), 1); ?>> Enable VPN / Proxy Warning Shield</label>
                        </p>
                    </div>

                    <!-- Partial Payment -->
                    <div style="background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb;">
                        <h2 style="font-size:16px; font-weight:600; color:#111827; border-bottom:2px solid #f59e0b; padding-bottom:6px;">💳 Courier Rate & Partial Payment</h2>
                        <p>
                            <label><input type="checkbox" name="fmb_enable_partial_payment" value="1" <?php checked(get_option('fmb_enable_partial_payment'), 1); ?>> Enable Partial Payment Engine</label>
                        </p>
                        <p>
                            <label><strong>Success Rate Threshold (%):</strong></label><br>
                            <input type="number" name="fmb_courier_rate_threshold" value="<?php echo esc_attr(get_option('fmb_courier_rate_threshold', 70)); ?>" style="width:80px;"> %
                        </p>
                        <p>
                            <label><strong>Advance Partial Payment Amount (Tk):</strong></label><br>
                            <input type="number" name="fmb_partial_payment_amount" value="<?php echo esc_attr(get_option('fmb_partial_payment_amount', 100)); ?>" style="width:100px;"> Tk
                        </p>
                    </div>

                    <!-- SMS & OTP -->
                    <div style="background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb;">
                        <h2 style="font-size:16px; font-weight:600; color:#111827; border-bottom:2px solid #10b981; padding-bottom:6px;">📲 Smart SMS & OTP</h2>
                        <p>
                            <label><strong>SMS Gateway:</strong></label><br>
                            <select name="fmb_sms_gateway">
                                <option value="bulksmsbd" <?php selected(get_option('fmb_sms_gateway'), 'bulksmsbd'); ?>>BulkSMSBD</option>
                                <option value="greenweb" <?php selected(get_option('fmb_sms_gateway'), 'greenweb'); ?>>Greenweb BD</option>
                            </select>
                        </p>
                        <p>
                            <label><strong>SMS API Key:</strong></label><br>
                            <input type="text" name="fmb_sms_api_key" value="<?php echo esc_attr(get_option('fmb_sms_api_key')); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><strong>Sender ID / Masking:</strong></label><br>
                            <input type="text" name="fmb_sms_sender_id" value="<?php echo esc_attr(get_option('fmb_sms_sender_id')); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><input type="checkbox" name="fmb_enable_checkout_otp" value="1" <?php checked(get_option('fmb_enable_checkout_otp'), 1); ?>> Enable OTP Verification at Checkout</label>
                        </p>
                    </div>

                    <!-- CAPI Analytics -->
                    <div style="background:#f9fafb; padding:16px; border-radius:8px; border:1px solid #e5e7eb; grid-column:span 2;">
                        <h2 style="font-size:16px; font-weight:600; color:#111827; border-bottom:2px solid #8b5cf6; padding-bottom:6px;">📊 CAPI & Multi-Platform Analytics</h2>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div>
                                <p>
                                    <label><strong>Meta/Facebook Pixel ID:</strong></label><br>
                                    <input type="text" name="fmb_fb_pixel_id" value="<?php echo esc_attr(get_option('fmb_fb_pixel_id')); ?>" class="regular-text">
                                </p>
                                <p>
                                    <label><strong>Facebook CAPI Access Token:</strong></label><br>
                                    <textarea name="fmb_fb_capi_access_token" rows="3" class="large-text"><?php echo esc_textarea(get_option('fmb_fb_capi_access_token')); ?></textarea>
                                </p>
                            </div>
                            <div>
                                <p>
                                    <label><strong>TikTok Pixel ID:</strong></label><br>
                                    <input type="text" name="fmb_tiktok_pixel_id" value="<?php echo esc_attr(get_option('fmb_tiktok_pixel_id')); ?>" class="regular-text">
                                </p>
                                <p>
                                    <label><strong>Google Analytics 4 Measurement ID:</strong></label><br>
                                    <input type="text" name="fmb_ga4_measurement_id" value="<?php echo esc_attr(get_option('fmb_ga4_measurement_id')); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php submit_button('Save FMB Engine Settings'); ?>
            </form>
        </div>
        <?php
    }
}
