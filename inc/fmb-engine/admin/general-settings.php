<?php
namespace fmb_engine\Admin;

use fmb_engine\Traits\Core;

class General_Settings {
    use Core;

    public function __construct() {
        
    }

    public static function render_optimizer_page() {
        $current_page = $_GET['page'] ?? 'fmb-engine-optimizer';
        
        $platforms = [
            'fmb-engine-optimizer' => 'fraud-checker',
            'fmb-engine-courier' => 'fraud-checker',
            'fmb-engine-fraud-customer-block' => 'fraud-block',
            'fmb-engine-courier-setup' => 'courier-setup',
            'fmb-engine-prtial-payment' => 'partial-payment',
            'fmb-engine-smart-sms' => 'smart-sms',
        ];
        
        $active_platform = $platforms[$current_page] ?? 'fraud-checker';

        if (isset($_POST['ads_save_partial_payment_settings'])) {
            $active_platform = 'partial-payment';
        } elseif (isset($_POST['ads_save_fraud_customer_block_settings'])) {
            $active_platform = 'fraud-block';
        } elseif (isset($_POST['ads_save_steadfast_settings']) || isset($_POST['ads_save_pathao_settings'])) {
            $active_platform = 'courier-setup';
        } elseif (isset($_POST['ads_save_smart_sms_settings'])) {
            $active_platform = 'smart-sms';
        } elseif (isset($_POST['ads_save_courier_settings'])) {
            $active_platform = 'fraud-checker';
        }

        echo "<div class='fmb-engine-main-box'>";
        require_once __DIR__ .'/promo.php';
        if (function_exists('fmb_engine_render_header')) { fmb_engine_render_header(); }
        ?>
        <script>
        window.fmbEngineSwitchPlatform = function(platform) {
            if (!platform) return;
            var items = document.querySelectorAll('.sidebar-menu-item');
            for (var i = 0; i < items.length; i++) {
                if (items[i].getAttribute('data-platform') === platform) {
                    items[i].classList.add('active');
                } else {
                    items[i].classList.remove('active');
                }
            }

            var containers = document.querySelectorAll('.platform-container');
            for (var j = 0; j < containers.length; j++) {
                if (containers[j].id === 'platform-' + platform) {
                    containers[j].classList.add('active');
                    containers[j].style.setProperty('display', 'block', 'important');
                } else {
                    containers[j].classList.remove('active');
                    containers[j].style.setProperty('display', 'none', 'important');
                }
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, '#platform-' + platform);
            }
        };

        window.fmbEngineSwitchTab = function(tabId, el) {
            if (!tabId || !el) return;
            var container = el.closest('.platform-container');
            if (!container) return;

            var tabs = container.querySelectorAll('.platform-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            el.classList.add('active');

            var contents = container.querySelectorAll('.platform-tab-content');
            for (var j = 0; j < contents.length; j++) {
                contents[j].style.display = 'none';
            }

            var target = container.querySelector('#' + tabId);
            if (target) {
                target.style.display = 'block';
            }

            var submitContainer = container.querySelector('#smart-sms-submit-container');
            if (submitContainer) {
                submitContainer.style.display = (tabId === 'sms-tab-dashboard') ? 'none' : 'block';
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, '#' + tabId);
            }
        };
        </script>
        <div class="fmb-engine-settings-container layout-sidebar">
            <!-- Left Sidebar Navigation -->
            <div class="fmb-engine-sidebar">
                <div class="sidebar-menu-item <?= $active_platform == 'fraud-checker' ? 'active' : '' ?>" data-platform="fraud-checker" onclick="window.fmbEngineSwitchPlatform('fraud-checker'); return false;">
                    <svg viewBox="0 0 24 24"><path fill="#2563eb" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    Fraud Checker
                </div>
                <div class="sidebar-menu-item <?= $active_platform == 'fraud-block' ? 'active' : '' ?>" data-platform="fraud-block" onclick="window.fmbEngineSwitchPlatform('fraud-block'); return false;">
                    <svg viewBox="0 0 24 24"><path fill="#dc2626" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/></svg>
                    Fraud Block
                </div>
                <div class="sidebar-menu-item <?= $active_platform == 'block-list' ? 'active' : '' ?>" data-platform="block-list" onclick="window.fmbEngineSwitchPlatform('block-list'); return false;">
                    <svg viewBox="0 0 24 24"><path fill="#64748b" d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                    Block List
                </div>
                <div class="sidebar-menu-item <?= $active_platform == 'courier-setup' ? 'active' : '' ?>" data-platform="courier-setup" onclick="window.fmbEngineSwitchPlatform('courier-setup'); return false;">
                    <svg viewBox="0 0 24 24"><path fill="#16a34a" d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                    Courier Setup
                </div>
                <div class="sidebar-menu-item <?= $active_platform == 'partial-payment' ? 'active' : '' ?>" data-platform="partial-payment" onclick="window.fmbEngineSwitchPlatform('partial-payment'); return false;">
                    <svg viewBox="0 0 24 24"><path fill="#ca8a04" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                    Partial Payment
                </div>
                <div class="sidebar-menu-item <?= $active_platform == 'smart-sms' ? 'active' : '' ?>" data-platform="smart-sms" onclick="window.fmbEngineSwitchPlatform('smart-sms'); return false;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Smart SMS
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="fmb-engine-main-content" style="width: 100%;">
                <div class="platform-container <?= $active_platform == 'fraud-checker' ? 'active' : '' ?>" id="platform-fraud-checker" style="display: <?= $active_platform == 'fraud-checker' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper" style="width: 100%;">
                        <div class="platform-tabs">
                            <a href="#tab-fraud-checker" class="platform-tab active" data-tab="tab-fraud-checker" onclick="window.fmbEngineSwitchTab('tab-fraud-checker', this); return false;">Fraud Checker</a>
                        </div>
                        <div class="fmb-engine-settings-content">
                            <div id="tab-fraud-checker" class="platform-tab-content" style="display: block;">
                                <?php self::render_courier_settings(); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="platform-container <?= $active_platform == 'fraud-block' ? 'active' : '' ?>" id="platform-fraud-block" style="display: <?= $active_platform == 'fraud-block' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper">
                        <?php self::render_fraud_customer_block_settings(); ?>
                    </div>
                </div>

                <div class="platform-container <?= $active_platform == 'block-list' ? 'active' : '' ?>" id="platform-block-list" style="display: <?= $active_platform == 'block-list' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper">
                        <?php self::render_block_list_settings(); ?>
                    </div>
                </div>

                <div class="platform-container <?= $active_platform == 'courier-setup' ? 'active' : '' ?>" id="platform-courier-setup" style="display: <?= $active_platform == 'courier-setup' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper">
                        <?php self::render_courier_setup_settings(); ?>
                    </div>
                </div>

                <div class="platform-container <?= $active_platform == 'partial-payment' ? 'active' : '' ?>" id="platform-partial-payment" style="display: <?= $active_platform == 'partial-payment' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper" style="display: block;">
                        <div class="fmb-engine-settings-content" style="display: block; box-sizing: border-box;">
                            <?php self::render_partial_payment_settings(); ?>
                        </div>
                    </div>
                </div>

                <div class="platform-container <?= $active_platform == 'smart-sms' ? 'active' : '' ?>" id="platform-smart-sms" style="display: <?= $active_platform == 'smart-sms' ? 'block' : 'none' ?>;">
                    <div class="fmb-engine-settings-wrapper" style="position: relative;">
                        <?php self::render_smart_sms_settings(); ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            function checkHashOnLoad() {
                if (window.location.hash) {
                    var hash = window.location.hash.substring(1);
                    if (hash.indexOf('platform-') === 0) {
                        window.fmbEngineSwitchPlatform(hash.replace('platform-', ''));
                    } else {
                        var target = document.getElementById(hash);
                        if (target) {
                            var pc = target.closest('.platform-container');
                            if (pc) {
                                window.fmbEngineSwitchPlatform(pc.id.replace('platform-', ''));
                                var tabEl = pc.querySelector('.platform-tab[data-tab="' + hash + '"]');
                                if (tabEl) {
                                    window.fmbEngineSwitchTab(hash, tabEl);
                                }
                            }
                        }
                    }
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkHashOnLoad);
            } else {
                checkHashOnLoad();
            }
            window.addEventListener('hashchange', checkHashOnLoad);
        })();
        </script>
        <?php
        echo "</div>";
    }

    public static function submit_partial_payment_settings() {
        if (!self::is_license_active()) {
            return;
        }

        // Save enable/disable option
        update_option('ads_enable_partial_payment', !empty($_POST['ads_enable_partial_payment']));

        // Save enable/disable zero rate option
        update_option('ads_enable_partial_payment_zero_rate', !empty($_POST['ads_enable_partial_payment_zero_rate']));

        // Save courier rate threshold
        $threshold = isset($_POST['ads_courier_rate_threshold'])
            ? max(0, min(100, (int)$_POST['ads_courier_rate_threshold']))
            : 70;
        update_option('ads_courier_rate_threshold', $threshold);

        // Save partial payment amount
        $amount = isset($_POST['ads_partial_payment_amount'])
            ? max(0, (float)$_POST['ads_partial_payment_amount'])
            : 100;
        update_option('ads_partial_payment_amount', $amount);

        update_option('ads_partial_label_one', $_POST['ads_partial_label_one']);
        update_option('ads_partial_label_two', $_POST['ads_partial_label_two']);
        
        $posted_gateways = isset($_POST['ads_partial_payment_allowed_gateways']) ? $_POST['ads_partial_payment_allowed_gateways'] : [];
        if (!is_array($posted_gateways)) {
            $posted_gateways = array($posted_gateways);
        }
        $allowed_gateways = array_map('sanitize_text_field', wp_unslash($posted_gateways));
        update_option('ads_partial_payment_allowed_gateways', $allowed_gateways);
    }

    public static function render_partial_payment_settings() {
        if (isset($_POST['ads_save_partial_payment_settings'])) {
            check_admin_referer('ads_partial_payment_settings_form_nonce')
                ? self::submit_partial_payment_settings()
                : self::render_error('Security check failed');
        }

        $enabled = get_option('ads_enable_partial_payment', false);
        $enabled_zero_rate = get_option('ads_enable_partial_payment_zero_rate', false);
        $threshold = get_option('ads_courier_rate_threshold', 70);
        $amount = get_option('ads_partial_payment_amount', 100);
        $ads_partial_label_one = get_option('ads_partial_label_one', 'এখন পরিশোধ করুন');
        $ads_partial_label_two = get_option('ads_partial_label_two', 'ডেলিভারিতে পরিশোধ করুন');
        $allowed_gateways = get_option('ads_partial_payment_allowed_gateways', []);
        ?>
        <div class="fmb-engine-section">
            <h2 class="fmb-engine-section-title">Partial Payment Settings</h2>
            <form method="post">
                <?php wp_nonce_field('ads_partial_payment_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Partial Payment

                            <p class="ads-settings-description">
                                Enable or disable the partial payment feature for orders with low delivery success rates.
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox"
                                       name="ads_enable_partial_payment"
                                       value="1"
                                    <?= checked($enabled); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Partial Payment for 0% Rate

                            <p class="ads-settings-description">
                                Enable partial payment feature for orders with 0% delivery success rates.
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox"
                                       name="ads_enable_partial_payment_zero_rate"
                                       value="1"
                                    <?= checked($enabled_zero_rate); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Courier Rate Threshold (%)
                            <p class="ads-settings-description">
                                Orders with delivery success rates below this threshold will require partial payment.
                                Default: 70%
                            </p></div>
                        <div class="fmb-engine-field-input">
                            <input type="number"
                                   name="ads_courier_rate_threshold"
                                   value="<?= esc_attr($threshold); ?>"
                                   min="0"
                                   max="100"
                                   step="1">
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Partial Payment Amount   <p class="ads-settings-description">
                                The fixed amount to be paid upfront when partial payment is required.
                                Default: 100
                            </p></div>
                        <div class="fmb-engine-field-input">
                            <input type="number"
                                   name="ads_partial_payment_amount"
                                   value="<?= esc_attr($amount); ?>"
                                   min="0"
                                   step="0.01">

                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Label One</div>
                        <div class="fmb-engine-field-input">
                            <input type="text"
                                   name="ads_partial_label_one"
                                   value="<?= esc_attr($ads_partial_label_one); ?>">
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Label Two </div>
                        <div class="fmb-engine-field-input">
                            <input type="text"
                                   name="ads_partial_label_two"
                                   value="<?= esc_attr($ads_partial_label_two); ?>">

                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Allowed Gateways
                            <p class="ads-settings-description">
                                Select which payment gateways are allowed for partial payments. If none are selected, all gateways except Cash on Delivery will be allowed. Use Ctrl/Cmd + Click to select multiple.
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <select name="ads_partial_payment_allowed_gateways[]" multiple="multiple" style="width: 100%; min-height: 120px; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px;">
                                <?php
                                if (class_exists('WooCommerce')) {
                                    $available_gateways = WC()->payment_gateways->payment_gateways();
                                    foreach ($available_gateways as $gateway) {
                                        if ($gateway->id === 'cod') continue;
                                        $selected = in_array($gateway->id, $allowed_gateways) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($gateway->id) . '" ' . $selected . '>' . esc_html($gateway->title) . ' (' . esc_html($gateway->id) . ')</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="ads_save_partial_payment_settings">
                    Save Settings
                </button>
            </form>
        </div>
        <?php
    }

    public static function submit_courier_settings() {
        if (!self::is_license_active()) {
            return;
        }

        // Save enable/disable option
        update_option('ads_enable_fraud_checker', !empty($_POST['ads_enable_fraud_checker']));
        update_option('ads_enable_order_history', !empty($_POST['ads_enable_order_history']));
        
        $posted_api_key = sanitize_text_field($_POST['ofls_bd_courier_api_key'] ?? '');
        $current_api_key = get_option('ofls_bd_courier_api_key', '');
        $is_masked_submission = false;
        
        if (!empty($current_api_key)) {
            $len = strlen($current_api_key);
            if ($len > 8) {
                $masked_api_key = substr($current_api_key, 0, 4) . str_repeat('*', $len - 8) . substr($current_api_key, -4);
            } else {
                $masked_api_key = str_repeat('*', $len);
            }
            if ($posted_api_key === $masked_api_key) {
                $is_masked_submission = true;
            }
        }

        if (!$is_masked_submission) {
            update_option('ofls_bd_courier_api_key', $posted_api_key);
        }


        // Reload the page
        $redirect_url = admin_url('admin.php?page=fmb-engine-courier&updated=1');
        wp_redirect($redirect_url);
        exit;
    }

    public static function render_courier_settings() {
        // Check for test results in option
        $test_results = get_option('ads_courier_api_test_results');
        if ($test_results) {
            // Delete the option after retrieving
            delete_option('ads_courier_api_test_results');

            // Display test results
            echo '<div class="notice-container">';
            echo '<h3>API Test Results</h3>';

            foreach ($test_results as $courier => $result) {
                $status_class = $result['status'] === 'error' ? 'api-test-error' : 'success';
                $icon = $result['status'] === 'error' ? '⛔' : '✅';

                echo '<div class="api-test-notice ' . $status_class . '">';
                echo '<span class="notice-icon">' . $icon . '</span>';
                echo '<strong>' . ucfirst($courier) . ' API:</strong> ' . esc_html($result['message']);

                // Show additional details for errors
                if ($result['status'] === 'error' && isset($result['data']['error_type'])) {
                    echo '<div class="error-details">Error Type: ' . esc_html($result['data']['error_type']) . '</div>';
                }

                echo '</div>';
            }
            echo '</div>';
        }

        if (isset($_POST['ads_save_courier_settings'])) {
            check_admin_referer('ads_courier_settings_form_nonce')
                ? self::submit_courier_settings()
                : self::render_error('Security check failed');
        }

        $enabled = get_option('ads_enable_fraud_checker', false);
        ?>
        <div class="fmb-engine-section">
            <h2 class="fmb-engine-section-title">Fraud Checker Settings</h2>
            <p class="fmb-engine-subtitle">Settings for fraud checker service.</p>
            <form method="post">
                <?php wp_nonce_field('ads_courier_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Fraud Checker

                            <p class="ads-settings-description">
                                Enable or disable the fraud checker feature.
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox"
                                       name="ads_enable_fraud_checker"
                                       value="1"
                                    <?= checked($enabled); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <?php $history_enabled = get_option('ads_enable_order_history', false); ?>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Order History Popup
                            <p class="ads-settings-description">
                                Enable or disable the Customer Insight & Order History feature.
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox"
                                       name="ads_enable_order_history"
                                       value="1"
                                    <?= checked($history_enabled); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">BD Courier API Key
                            <p class="ads-settings-description">
                                <a href="https://app.bdcourier.com/login" target="_blank" style="color: #1a7278; text-decoration: underline;">Create an account</a> to get your API Key. Enjoy 50 free requests every day!
                            </p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <?php
                            $api_key = get_option('ofls_bd_courier_api_key', '');
                            $masked_api_key = '';
                            if (!empty($api_key)) {
                                $len = strlen($api_key);
                                if ($len > 8) {
                                    $masked_api_key = substr($api_key, 0, 4) . str_repeat('*', $len - 8) . substr($api_key, -4);
                                } else {
                                    $masked_api_key = str_repeat('*', $len);
                                }
                            }
                            ?>
                            <input type="text" name="ofls_bd_courier_api_key" value="<?= esc_attr($masked_api_key); ?>" placeholder="Enter your BD Courier Merchant API Key" autocomplete="off">
                        </div>
                    </div>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="ads_save_courier_settings">
                    Save Settings
                </button>
            </form>
        </div>

        <style>
            .notice-container {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #00a0d2;
                border-radius: 3px;
            }

            .api-test-notice {
                display: flex;
                align-items: center;
                padding: 10px;
                margin: 5px 0;
                border-radius: 3px;
            }

            .api-test-notice.success {
                background-color: #edfaef;
                border-left: 4px solid #46b450;
            }

            .api-test-notice.api-test-error {
                background-color: #fdf2f2;
                border-left: 4px solid #dc3232;
            }

            .notice-icon {
                font-size: 1.2em;
                margin-right: 10px;
            }

            .error-details {
                margin-top: 5px;
                font-size: 0.9em;
                color: #666;
                padding-left: 30px;
            }
        </style>
        <?php
    }

    public static function submit_fraud_customer_block_settings() {
        if ( ! self::is_license_active() ) {
            return;
        }

        $checkboxes = [
            'ads_validate_phone_number_length_number',
            'ads_block_phone_numbers',
            'ads_block_device_ids',
            'ads_restrict_multiple_orders_device',
            'ads_restrict_multiple_orders_phone',
            'ads_enable_vpn_shield'
        ];

        foreach ($checkboxes as $option) {
            update_option($option, !empty($_POST[$option]));
        }

        update_option('ads_restrict_multiple_orders_device_time',
            isset($_POST['ads_restrict_multiple_orders_device_time'])
                ? max(1, (int)$_POST['ads_restrict_multiple_orders_device_time'])
                : 24
        );

        update_option('ads_restrict_multiple_orders_device_limit',
            isset($_POST['ads_restrict_multiple_orders_device_limit'])
                ? max(1, (int)$_POST['ads_restrict_multiple_orders_device_limit'])
                : 1
        );

        update_option('ads_restrict_multiple_orders_phone_time',
            isset($_POST['ads_restrict_multiple_orders_phone_time'])
                ? max(1, (int)$_POST['ads_restrict_multiple_orders_phone_time'])
                : 24
        );

        update_option('ads_restrict_multiple_orders_phone_limit',
            isset($_POST['ads_restrict_multiple_orders_phone_limit'])
                ? max(1, (int)$_POST['ads_restrict_multiple_orders_phone_limit'])
                : 1
        );

        update_option('ads_checkout_process_error_contact_phone_number',
            isset($_POST['ads_checkout_process_error_contact_phone_number'])
                ? sanitize_text_field($_POST['ads_checkout_process_error_contact_phone_number'])
                : ''
        );
        
        update_option('ads_messenger_link',
            isset($_POST['ads_messenger_link'])
                ? esc_url_raw($_POST['ads_messenger_link'])
                : ''
        );
        
        update_option('ads_checkout_process_error_blocked_customer_message',
            isset($_POST['ads_checkout_process_error_blocked_customer_message'])
                ? sanitize_textarea_field(stripslashes($_POST['ads_checkout_process_error_blocked_customer_message']))
                : ''
        );
    }

    public static function render_fraud_customer_block_settings() {
        if (isset($_POST['ads_save_fraud_customer_block_settings'])) {
            check_admin_referer('ads_fraud_customer_block_settings_form_nonce')
                ? self::submit_fraud_customer_block_settings()
                : self::render_error('Security check failed');
        }

        $checkboxes = [
            'ads_validate_phone_number_length_number' => array(
                'text'  => 'Check 11 digits of phone number',
                'dis'  => 'Validate 11-digit phone numbers.',
            ),
            'ads_block_phone_numbers' => array(
                'text'  => 'Block Phone Numbers',
                'dis'  => 'Block orders from blocked phone numbers.',
            ),


            'ads_block_device_ids' => array(
                'text'  => 'Block Device IDs',
                'dis'  => 'Enable advanced fingerprinting to automatically block orders from known fraudulent devices (Mobile, Laptop, Desktop). Works even in Incognito mode.',
            ),
            'ads_enable_vpn_shield' => array(
                'text'  => 'VPN Shield (Zero-Lag)',
                'dis'  => 'Instantly detect and block users attempting to order via proxy or VPN servers. Displays a secure popup modal.',
            ),
            'ads_restrict_multiple_orders_device' =>  array(
                'text'  => 'Restrict multiple orders from same Device ID',
                'dis'  => 'Limit orders from the same physical device/fingerprint.',
            ),
            'ads_restrict_multiple_orders_phone' =>  array(
                'text'  => 'Restrict multiple orders from same Phone Number',
                'dis'  => 'Limit orders using the same billing phone number.',
            )
        ];

        $text_fields = [
            'ads_restrict_multiple_orders_device_time' => [
                'label' => 'Device multiple orders Time (Hours)',
                'dis' => 'Time limit in hours for same device multiple orders.',
                'default' => 24,
                'min' => 1,
                'max' => 168
            ],
            'ads_restrict_multiple_orders_device_limit' => [
                'label' => 'Device multiple orders limit',
                'dis' => 'Maximum number of orders allowed from same device.',
                'default' => 1,
                'min' => 1,
                'max' => 100
            ],
            'ads_restrict_multiple_orders_phone_time' => [
                'label' => 'Phone multiple orders Time (Hours)',
                'dis' => 'Time limit in hours for same phone multiple orders.',
                'default' => 24,
                'min' => 1,
                'max' => 168
            ],
            'ads_restrict_multiple_orders_phone_limit' => [
                'label' => 'Phone multiple orders limit',
                'dis' => 'Maximum number of orders allowed from same phone.',
                'default' => 1,
                'min' => 1,
                'max' => 100
            ],
            'ads_restrict_multiple_orders_phone_message' => [
                'label' => 'Phone custom error message',
                'dis' => 'Custom message for this restriction. You can use {time} and {limit} as placeholders.',
                'default' => 'আপনি সর্বোচ্চ অর্ডার সীমা পূর্ণ করেছেন। আমাদের পলিসি অনুযায়ী, নির্দিষ্ট এই {time} ঘন্টার মধ্যে {limit} টি অর্ডার করতে পারবেন। অনুগ্রহ করে নির্দিষ্ট সময় পরে আবার চেষ্টা করুন অথবা আমাদের সাথে যোগাযোগ করুন: ',
                'type' => 'textarea'
            ],
            'ads_checkout_process_error_contact_phone_number' => [
                'label' => 'Checkout Process Error Contact Phone Number',
                'dis' => 'Phone number to display for checkout errors.',
                'default' => '',
                'type' => 'text'
            ],
            'ads_messenger_link' => [
                'label' => 'Messenger Link',
                'dis' => 'Facebook Messenger link to display in the Fraud Block popup (e.g. https://m.me/yourpage)',
                'default' => '',
                'type' => 'text'
            ],
            'ads_checkout_process_error_blocked_customer_message' => [
                'label' => 'Fraud Blocked Error Message',
                'dis' => 'Custom message to display on the checkout page when a customer is blocked by IP, Device, Email, or Phone rule.',
                'default' => 'দুঃখিত আপনার অর্ডারটি এই মুহূর্তে গ্রহণ করা যাচ্ছে না, আমাদের সাথে যোগাযোগ করুন: ',
                'type' => 'textarea'
            ]
        ];

        // Blocked list is now fetched asynchronously via AJAX in the frontend for ultra-fast performance.
        // We only maintain the active tab state logic here.

        // Detect active tab from URL query param, default to settings
        $active_subtab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'settings';
        ?>

        <style>
            .fmb-engine-blocked-table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                overflow: hidden;
            }
            .fmb-engine-blocked-table th, .fmb-engine-blocked-table td {
                padding: 14px 16px;
                text-align: left;
                border-bottom: 1px solid #f1f5f9;
                font-size: 14px;
            }
            .fmb-engine-blocked-table th {
                background: #f8fafc;
                font-weight: 600;
                color: #475569;
            }
            .fmb-engine-blocked-table td {
                color: #334155;
            }
            .fmb-engine-table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        </style>

        <div class="fmb-engine-settings-content">
            <div class="fmb-engine-section">
            <h2 class="fmb-engine-section-title">Fraud Block Settings</h2>
            <form method="post">
                <?php wp_nonce_field('ads_fraud_customer_block_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <?php foreach ($checkboxes as $key => $label): ?>
                        <div class="fmb-engine-field-row">
                            <div class="fmb-engine-field-label">
                                <?= esc_html($label['text']); ?>
                                <p class="ads-settings-description"><?= esc_html($label['dis']); ?></p>
                            </div>
                            <div class="fmb-engine-field-input">
                                <label class="fmb-engine-toggle-switch">
                                    <input type="checkbox"
                                           name="<?= esc_attr($key); ?>"
                                           value="1"
                                        <?= checked(get_option($key)); ?>>
                                    <span class="fmb-engine-slider"></span>
                                </label>
                            </div>
                        </div>

                        <?php if ($key === 'ads_restrict_multiple_orders_device'): ?>
                            <!-- Device restriction fields -->
                            <div class="restrict-multiple-orders-device-container" style="display: none;">
                                <?php foreach (['ads_restrict_multiple_orders_device_time', 'ads_restrict_multiple_orders_device_limit'] as $sub_key): $field = $text_fields[$sub_key]; ?>
                                    <div class="fmb-engine-field-row" style="background: #f8fafc;">
                                        <div class="fmb-engine-field-label" style="padding-left: 20px; border-left: 3px solid #cbd5e1;">
                                            <?= esc_html($field['label']); ?>
                                            <p class="ads-settings-description"><?= esc_html($field['dis']); ?></p>
                                        </div>
                                        <div class="fmb-engine-field-input">
                                            <input type="number" name="<?= esc_attr($sub_key); ?>" value="<?= esc_attr(get_option($sub_key, $field['default'])); ?>" min="<?= $field['min'] ?>" max="<?= $field['max'] ?>">
                                            <p class="description">Min: <?= $field['min']; ?>, Max: <?= $field['max']; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($key === 'ads_restrict_multiple_orders_phone'): ?>
                            <!-- Phone restriction fields -->
                            <div class="restrict-multiple-orders-phone-container" style="display: none;">
                                <?php foreach (['ads_restrict_multiple_orders_phone_time', 'ads_restrict_multiple_orders_phone_limit', 'ads_restrict_multiple_orders_phone_message'] as $sub_key): $field = $text_fields[$sub_key]; ?>
                                    <div class="fmb-engine-field-row" style="background: #f8fafc;">
                                        <div class="fmb-engine-field-label" style="padding-left: 20px; border-left: 3px solid #cbd5e1;">
                                            <?= esc_html($field['label']); ?>
                                            <p class="ads-settings-description"><?= esc_html($field['dis']); ?></p>
                                        </div>
                                        <div class="fmb-engine-field-input">
                                            <?php if (isset($field['type']) && $field['type'] === 'textarea'): ?>
                                                <textarea name="<?= esc_attr($sub_key); ?>" rows="4" style="width:100%;"><?= esc_textarea(get_option($sub_key, $field['default'])); ?></textarea>
                                            <?php else: ?>
                                                <input type="number" name="<?= esc_attr($sub_key); ?>" value="<?= esc_attr(get_option($sub_key, $field['default'])); ?>" min="<?= $field['min'] ?>" max="<?= $field['max'] ?>">
                                                <p class="description">Min: <?= $field['min']; ?>, Max: <?= $field['max']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Always visible contact phone field -->
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">
                            <?= esc_html($text_fields['ads_checkout_process_error_contact_phone_number']['label']); ?>
                            <p class="ads-settings-description"><?= esc_html($text_fields['ads_checkout_process_error_contact_phone_number']['dis']); ?></p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <input type="text"
                                   name="ads_checkout_process_error_contact_phone_number"
                                   value="<?= esc_attr(get_option('ads_checkout_process_error_contact_phone_number', $text_fields['ads_checkout_process_error_contact_phone_number']['default'])); ?>">
                        </div>
                    </div>
                    
                    <!-- Always visible messenger field -->
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">
                            <?= esc_html($text_fields['ads_messenger_link']['label']); ?>
                            <p class="ads-settings-description"><?= esc_html($text_fields['ads_messenger_link']['dis']); ?></p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <input type="text"
                                   name="ads_messenger_link"
                                   value="<?= esc_attr(get_option('ads_messenger_link', $text_fields['ads_messenger_link']['default'])); ?>">
                        </div>
                    </div>
                    
                    <!-- Blocked customer custom message -->
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">
                            <?= esc_html($text_fields['ads_checkout_process_error_blocked_customer_message']['label']); ?>
                            <p class="ads-settings-description"><?= esc_html($text_fields['ads_checkout_process_error_blocked_customer_message']['dis']); ?></p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <textarea name="ads_checkout_process_error_blocked_customer_message" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px;"><?= esc_textarea(get_option('ads_checkout_process_error_blocked_customer_message', $text_fields['ads_checkout_process_error_blocked_customer_message']['default'])); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="ads_save_fraud_customer_block_settings">
                    Save Settings
                </button>
            </form>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function toggleRestrictionFields() {
                    $('.restrict-multiple-orders-device-container').css('display', $('input[name="ads_restrict_multiple_orders_device"]').is(':checked') ? 'block' : 'none');
                    $('.restrict-multiple-orders-phone-container').css('display', $('input[name="ads_restrict_multiple_orders_phone"]').is(':checked') ? 'block' : 'none');
                }
                toggleRestrictionFields();
                $('input[name="ads_restrict_multiple_orders_device"], input[name="ads_restrict_multiple_orders_phone"]').on('change', toggleRestrictionFields);
            });
        </script>
        <?php
    }

    public static function render_block_list_settings() {
        ?>
        <style>
            .fmb-engine-blocked-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
            .fmb-engine-blocked-table th, .fmb-engine-blocked-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
            .fmb-engine-blocked-table th { background: #f8fafc; font-weight: 600; color: #475569; }
            .fmb-engine-blocked-table td { color: #334155; }
            .fmb-engine-table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        </style>
        <div class="fmb-engine-settings-content">
            <div class="fmb-engine-section">
            <h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 0 0 16px 0; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center;">
                <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px; fill: #64748b;"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                Block List
            </h2>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <input type="text" id="fmb-engine-blocklist-search" placeholder="Search Phone, Order ID, or Reason..." style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 300px; max-width: 100%;">
                <div style="display: flex; gap: 8px;">
                    <button type="button" id="fmb-engine-add-manual-block" style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        Add Block
                    </button>
                    <button type="button" id="fmb-engine-export-csv" style="background: #197278; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Export CSV
                    </button>
                </div>
            </div>
            <div class="fmb-engine-table-responsive">
                <table class="fmb-engine-blocked-table" id="fmb-engine-blocklist-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Phone Number</th>
                            <th>Device IP / Hash</th>
                            <th>Reason</th>
                            <th>Note</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="fmb-engine-block-list-tbody">
                        <tr><td colspan="6" style="text-align: center; padding: 32px; color: #94a3b8;">Loading blocked list...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Manual Block Modal -->
            <div id="fmb-engine-manual-block-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
                <div style="background: white; padding: 24px; border-radius: 8px; width: 400px; max-width: 90%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; font-size: 18px; color: #1e293b;">Manually Block Customer</h3>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #475569;">Phone Number</label>
                        <input type="text" id="ofls_manual_block_phone" placeholder="e.g. 01700000000" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #475569;">Note / Reason (Optional)</label>
                        <textarea id="ofls_manual_block_note" placeholder="e.g. Fake order placed" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; height: 80px;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" id="ofls_close_manual_block" style="padding: 8px 16px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; font-weight: 600;">Cancel</button>
                        <button type="button" id="ofls_save_manual_block" style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Block Customer</button>
                    </div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                let blockListTimeout;
                const fetchBlockList = function() {
                    const searchVal = $('#fmb-engine-blocklist-search').val();
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'fmb_engine_fetch_blocked_list', _ajax_nonce: '<?php echo wp_create_nonce("ads_set_customer_access_secure_nonce"); ?>', search: searchVal },
                        success: function(response) { if (response.success) { $('#fmb-engine-block-list-tbody').html(response.data.html); } }
                    });
                };
                $('#fmb-engine-blocklist-search').on('keyup', function() { clearTimeout(blockListTimeout); blockListTimeout = setTimeout(fetchBlockList, 300); });
                fetchBlockList();
                $('#fmb-engine-export-csv').on('click', function() {
                    var csv = [];
                    var rows = document.querySelectorAll("table#fmb-engine-blocklist-table tr");
                    for (var i = 0; i < rows.length; i++) {
                        if ($(rows[i]).css('display') === 'none') continue;
                        var row = [], cols = rows[i].querySelectorAll("td, th");
                        for (var j = 0; j < cols.length - 1; j++) {
                            var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                            data = data.replace(/"/g, '""');
                            row.push('"' + data + '"');
                        }
                        csv.push(row.join(","));
                    }
                    var csv_string = csv.join("\n");
                    var filename = "fraud-block-list_" + new Date().toISOString().slice(0,10) + ".csv";
                    var link = document.createElement("a");
                    link.style.display = "none";
                    link.setAttribute("target", "_blank");
                    link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csv_string));
                    link.setAttribute("download", filename);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });

                // Dynamic Action Button State
                $(document).on('click', '.ads-customer-access-btn', function() {
                    const btn = $(this);
                    if(btn.hasClass('ads-customer-access-allowed')) {
                        btn.text('Blocked').css('background', '#ef4444').removeClass('ads-customer-access-allowed').addClass('ads-customer-access-blocked');
                    } else {
                        btn.text('Unblocked').css('background', '#10b981').removeClass('ads-customer-access-blocked').addClass('ads-customer-access-allowed');
                        setTimeout(() => {
                            btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                        }, 800);
                    }
                });

                // Manual Block Modal Logic
                $('#fmb-engine-add-manual-block').on('click', function() {
                    $('#ofls_manual_block_phone').val('');
                    $('#ofls_manual_block_note').val('Manual Block');
                    $('#fmb-engine-manual-block-modal').css('display', 'flex');
                });

                $('#ofls_close_manual_block').on('click', function() {
                    $('#fmb-engine-manual-block-modal').hide();
                });

                $('#ofls_save_manual_block').on('click', function() {
                    var phone = $('#ofls_manual_block_phone').val().trim();
                    var note = $('#ofls_manual_block_note').val().trim();
                    if (!phone) {
                        alert('Please enter a phone number to block.');
                        return;
                    }
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text('Saving...').prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ads_set_customer_access',
                            _ajax_nonce: '<?php echo wp_create_nonce("ads_set_customer_access_secure_nonce"); ?>',
                            type: 'phone_number',
                            value: phone,
                            note: note
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#fmb-engine-manual-block-modal').hide();
                                fetchBlockList(); // Refresh the table
                            } else {
                                alert('Failed to block customer. Please try again.');
                            }
                        },
                        complete: function() {
                            $btn.text(originalText).prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            </div>
            </div>

        <script>
            jQuery(document).ready(function($) {
                // Function to toggle visibility based on checkbox
                function toggleRestrictionFields() {
                    $('.restrict-multiple-orders-device-container').css('display', $('input[name="ads_restrict_multiple_orders_device"]').is(':checked') ? 'block' : 'none');
                    $('.restrict-multiple-orders-phone-container').css('display', $('input[name="ads_restrict_multiple_orders_phone"]').is(':checked') ? 'block' : 'none');
                }

                // Set initial state
                toggleRestrictionFields();

                // Listen for changes
                $('input[name="ads_restrict_multiple_orders_device"], input[name="ads_restrict_multiple_orders_phone"]').on('change', toggleRestrictionFields);
            });
        </script>
        <?php
    }

    public static function submit_steadfast_settings() {
        if (!self::is_license_active()) return;
        
        $api_key = sanitize_text_field($_POST['steadfast_api_key']);
        $secret_key = sanitize_text_field($_POST['steadfast_secret_key']);
        $enabled = !empty($_POST['steadfast_enable']);
        $remarks_sync_enabled = !empty($_POST['steadfast_enable_remarks_sync']);

        update_option('steadfast_enable', $enabled);
        update_option('steadfast_enable_remarks_sync', $remarks_sync_enabled);
        update_option('steadfast_api_key', $api_key);
        update_option('steadfast_secret_key', $secret_key);

        if ($enabled) {
            update_option('pathao_enable', false);
        }

        // Validate Keys dynamically via API check
        $response = wp_remote_get('https://portal.packzy.com/api/v1/get_balance', [
            'headers' => [
                'Api-Key' => $api_key,
                'Secret-Key' => $secret_key
            ],
            'timeout' => 10
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['status']) && $body['status'] == 200) {
                update_option('steadfast_keys_valid', true);
            } else {
                update_option('steadfast_keys_valid', false);
            }
        } else {
            update_option('steadfast_keys_valid', false);
        }

        $redirect_url = admin_url('admin.php?page=fmb-engine-courier-setup&tab=steadfast&updated=1');
        wp_redirect($redirect_url);
        exit;
    }

    public static function submit_pathao_settings() {
        if (!self::is_license_active()) return;
        
        $enabled = !empty($_POST['pathao_enable']);
        $remarks_sync_enabled = !empty($_POST['pathao_enable_remarks_sync']);
        $api_key = sanitize_text_field($_POST['pathao_api_key']);
        $secret_key = sanitize_text_field($_POST['pathao_secret_key']);
        $store_id = sanitize_text_field($_POST['pathao_store_id']);
        $city_id = sanitize_text_field($_POST['pathao_default_city']);
        $zone_id = sanitize_text_field($_POST['pathao_default_zone']);
        $webhook_secret = sanitize_text_field($_POST['pathao_webhook_secret']);

        update_option('pathao_enable', $enabled);
        update_option('pathao_enable_remarks_sync', $remarks_sync_enabled);
        update_option('pathao_api_key', $api_key);
        update_option('pathao_secret_key', $secret_key);
        update_option('pathao_webhook_secret', $webhook_secret);
        update_option('pathao_store_id', $store_id);
        update_option('pathao_default_city', $city_id);
        update_option('pathao_default_zone', $zone_id);

        if ($enabled) {
            update_option('steadfast_enable', false);
        }
        
        // Validate Pathao Token API
        $response = wp_remote_post('https://api-hermes.pathao.com/aladdin/api/v1/external/login', array(
            'headers' => array(
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => json_encode(array(
                'client_id' => $api_key,
                'client_secret' => $secret_key,
            )),
            'timeout' => 10
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                update_option('pathao_keys_valid', true);
            } else {
                update_option('pathao_keys_valid', false);
            }
        } else {
            update_option('pathao_keys_valid', false);
        }
        
        $redirect_url = admin_url('admin.php?page=fmb-engine-courier-setup&tab=pathao&updated=1');
        wp_redirect($redirect_url);
        exit;
    }

    public static function render_courier_setup_settings() {
        if (isset($_POST['save_steadfast_settings'])) {
            check_admin_referer('steadfast_settings_form_nonce')
                ? self::submit_steadfast_settings()
                : self::render_error('Security check failed');
        } elseif (isset($_POST['save_pathao_settings'])) {
            check_admin_referer('pathao_settings_form_nonce')
                ? self::submit_pathao_settings()
                : self::render_error('Security check failed');
        }

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'steadfast';

        ?>
        <div class="platform-tabs">
            <a href="#tab-steadfast" class="platform-tab <?= $current_tab === 'steadfast' ? 'active' : '' ?>" data-tab="tab-steadfast" onclick="window.fmbEngineSwitchTab('tab-steadfast', this); return false;">
                Steadfast Courier
            </a>
            <a href="#tab-pathao" class="platform-tab <?= $current_tab === 'pathao' ? 'active' : '' ?>" data-tab="tab-pathao" onclick="window.fmbEngineSwitchTab('tab-pathao', this); return false;">
                Pathao Courier
            </a>
        </div>
        <div class="fmb-engine-settings-content">
            <div class="fmb-engine-section">

                <div id="tab-steadfast" class="platform-tab-content" style="<?= $current_tab === 'steadfast' ? 'display: block;' : 'display: none;' ?>">
                <?php 
                    $enabled = get_option('steadfast_enable', false);
                    $is_valid = get_option('steadfast_keys_valid', false);
                    $saved_api = get_option('steadfast_api_key', '');
                    $saved_secret = get_option('steadfast_secret_key', '');
                ?>
                    <h2 class="fmb-engine-section-title">Steadfast Courier Integration</h2>
                    <p class="fmb-engine-subtitle">Configure your Steadfast Courier API keys for one-click order syncing.</p>
                    <form method="post">
                        <?php wp_nonce_field('steadfast_settings_form_nonce'); ?>
                        <div class="fmb-engine-form-container">
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Enable Steadfast</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="steadfast_enable" value="1" <?= checked($enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Rider Update</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="steadfast_enable_remarks_sync" value="1" <?= checked(get_option('steadfast_enable_remarks_sync', false)); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Automatically fetch and display courier remarks every hour.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">API Key</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" name="steadfast_api_key" value="<?= esc_attr($saved_api); ?>">
                                    <?php if ($saved_api && $is_valid): ?>
                                        <span style="display: block; color: #155724; font-size: 12px; margin-top: 5px; font-weight: 500;">
                                            ✅ API Keys are connected and working perfectly.
                                        </span>
                                    <?php elseif ($saved_api && !$is_valid): ?>
                                        <span style="display: block; color: #721c24; font-size: 12px; margin-top: 5px; font-weight: 500;">
                                            ❌ Invalid API keys. Could not authenticate with Steadfast.
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Secret Key</div>
                                <div class="fmb-engine-field-input">
                                    <input type="password" name="steadfast_secret_key" value="<?= esc_attr($saved_secret); ?>">
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" style="background: #f8fafc; padding: 16px; border-left: 3px solid #8b5cf6; border-radius: 4px; margin-top: 16px;">
                                <div class="fmb-engine-field-label" style="color: #1e293b; font-weight: 600;">Webhook URL</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" value="<?= esc_url(rest_url('FMB Engine/v1/steadfast-webhook')); ?>" readonly onclick="this.select();" style="background: #ffffff; cursor: pointer; color: #475569; font-family: monospace;">
                                    <p style="font-size: 12px; color:#64748b; margin-top:6px; font-weight: 500;">
                                        📋 Click to copy. Then <a href="https://steadfast.com.bd/user/webhook/add" target="_blank" style="color: #8b5cf6; text-decoration: underline;">log in to your Steadfast panel</a>, go to <strong>Settings » Webhook</strong>, and paste this URL to automatically receive live status updates!
                                    </p>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="fmb-engine-submit-button" name="save_steadfast_settings" style="background-color: #197278; color: white;">
                            Save Settings
                        </button>
                    </form>
                </div>

                <div id="tab-pathao" class="platform-tab-content" style="<?= $current_tab === 'pathao' ? 'display: block;' : 'display: none;' ?>">
                <?php 
                    $enabled = get_option('pathao_enable', false);
                    $is_valid = get_option('pathao_keys_valid', false);
                    $saved_api = get_option('pathao_api_key', '');
                    $saved_secret = get_option('pathao_secret_key', '');
                    $saved_webhook_secret = get_option('pathao_webhook_secret', '');
                    $saved_store = get_option('pathao_store_id', '');
                    $saved_city = get_option('pathao_default_city', '1');
                    $saved_zone = get_option('pathao_default_zone', '1');
                ?>
                    <h2 class="fmb-engine-section-title">Pathao Courier Integration</h2>
                    <p class="fmb-engine-subtitle">Configure your Pathao API keys for one-click order syncing. Note: Only one courier can be enabled at a time.</p>
                    <form method="post">
                        <?php wp_nonce_field('pathao_settings_form_nonce'); ?>
                        <div class="fmb-engine-form-container">
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Enable Pathao</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="pathao_enable" value="1" <?= checked($enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Rider Update</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="pathao_enable_remarks_sync" value="1" <?= checked(get_option('pathao_enable_remarks_sync', false)); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Automatically fetch and display courier remarks every hour.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Client ID</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" name="pathao_api_key" value="<?= esc_attr($saved_api); ?>">
                                    <?php if ($saved_api && $is_valid): ?>
                                        <span style="display: block; color: #155724; font-size: 12px; margin-top: 5px; font-weight: 500;">
                                            ✅ Client ID and Secret are working perfectly.
                                        </span>
                                    <?php elseif ($saved_api && !$is_valid): ?>
                                        <span style="display: block; color: #721c24; font-size: 12px; margin-top: 5px; font-weight: 500;">
                                            ❌ Invalid Client ID or Secret. Could not generate OAuth token.
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Client Secret</div>
                                <div class="fmb-engine-field-input">
                                    <input type="password" name="pathao_secret_key" value="<?= esc_attr($saved_secret); ?>">
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Store ID</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" name="pathao_store_id" value="<?= esc_attr($saved_store); ?>" placeholder="E.g. 84123">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">
                                        Find your Store ID <a href="https://merchant.pathao.com/courier/stores/list" target="_blank" style="color:#197278; text-decoration:underline;">here</a>. (Do not use your Merchant ID!)
                                    </p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Default City ID</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" name="pathao_default_city" value="<?= esc_attr($saved_city); ?>" placeholder="E.g. 1">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Fallback City ID (e.g., 1 for Dhaka) used when sending raw text addresses.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Default Zone ID</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" name="pathao_default_zone" value="<?= esc_attr($saved_zone); ?>" placeholder="E.g. 1">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Fallback Zone ID used when sending raw text addresses.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" style="background: #f8fafc; padding: 16px; border-left: 3px solid #ef4444; border-radius: 4px; margin-top: 16px;">
                                <div class="fmb-engine-field-label" style="color: #1e293b; font-weight: 600;">Webhook URL</div>
                                <div class="fmb-engine-field-input">
                                    <input type="text" value="<?= esc_url(rest_url('FMB Engine/v1/pathao-webhook')); ?>" readonly onclick="this.select();" style="background: #ffffff; cursor: pointer; color: #475569; font-family: monospace; margin-bottom: 8px;">
                                    
                                    <div style="margin-top: 10px;">
                                        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Webhook Secret</label>
                                        <input type="text" name="pathao_webhook_secret" value="<?= esc_attr($saved_webhook_secret); ?>" placeholder="Paste the exact Secret from Pathao here" style="background: #ffffff; color: #475569;">
                                    </div>
                                    
                                    <p style="font-size: 12px; color:#64748b; margin-top:8px; font-weight: 500;">
                                        📋 <a href="https://merchant.pathao.com/courier/developer-api" target="_blank" style="color: #ef4444; text-decoration: underline;">Log in to your Pathao panel</a>, go to <strong>Developers API » Webhook integration</strong>.<br>
                                        1. Paste the Webhook URL above.<br>
                                        2. Copy the generated Webhook Secret from Pathao and paste it in the field above.<br>
                                        3. Click "Save Settings" here, then click "Verify" on Pathao!
                                    </p>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="fmb-engine-submit-button" name="save_pathao_settings" style="background-color: #197278; color: white;">
                            Save Settings
                        </button>
                    </form>
                </div>
                
            </div>
            
        </div>
        <?php
    }

    public static function submit_smart_sms_settings() {
        if (!current_user_can('manage_options')) return;
        
        $sms_enabled = !empty($_POST['ofls_sms_enabled']);
        $sms_provider = sanitize_text_field($_POST['ofls_sms_provider'] ?? 'bulksmsbd');
        $bdbulksms_token = sanitize_text_field($_POST['ofls_bdbulksms_token'] ?? '');
        $bulksmsbd_api_key = sanitize_text_field($_POST['ofls_bulksmsbd_api_key'] ?? '');
        $bulksmsbd_senderid = sanitize_text_field($_POST['ofls_bulksmsbd_senderid'] ?? '');
        
        $mimsms_api_key = sanitize_text_field($_POST['ofls_mimsms_api_key'] ?? '');
        $mimsms_secret_key = sanitize_text_field($_POST['ofls_mimsms_secret_key'] ?? '');
        $mimsms_caller_id = sanitize_text_field($_POST['ofls_mimsms_caller_id'] ?? '');
        
        $otp_enabled = !empty($_POST['ofls_sms_otp_enabled']);
        $otp_condition = sanitize_text_field($_POST['ofls_sms_otp_condition']);
        $otp_threshold = intval($_POST['ofls_sms_otp_threshold']);
        $otp_new_zero_enabled = !empty($_POST['ofls_sms_otp_new_zero_enabled']);
        $otp_cooldown = intval($_POST['ofls_sms_otp_cooldown']);
        // enforce max 72
        if ($otp_cooldown > 72) $otp_cooldown = 72;
        
        $otp_resend_limit = intval($_POST['ofls_sms_otp_resend_limit']);
        if ($otp_resend_limit < 0) $otp_resend_limit = 0;
        
        $otp_countdown = intval($_POST['ofls_sms_otp_countdown']);
        if ($otp_countdown < 1) $otp_countdown = 1;
        
        $otp_template = sanitize_textarea_field($_POST['ofls_sms_otp_template']);
        
        $placed_template = sanitize_textarea_field($_POST['ofls_sms_placed_template']);
        $shipped_template = sanitize_textarea_field($_POST['ofls_sms_shipped_template']);
        $notifications_enabled = !empty($_POST['ofls_sms_notifications_enabled']);
        
        $cancelled_template = sanitize_textarea_field($_POST['ofls_sms_cancelled_template']);
        $shipping_template = sanitize_textarea_field($_POST['ofls_sms_shipping_template']);
        $confirmed_template = sanitize_textarea_field($_POST['ofls_sms_confirmed_template']);
        $processing_template = sanitize_textarea_field($_POST['ofls_sms_processing_template']);

        update_option('ofls_sms_enabled', $sms_enabled);
        update_option('ofls_sms_provider', $sms_provider);
        update_option('ofls_bdbulksms_token', $bdbulksms_token);
        update_option('ofls_bulksmsbd_api_key', $bulksmsbd_api_key);
        update_option('ofls_bulksmsbd_senderid', $bulksmsbd_senderid);
        
        update_option('ofls_mimsms_api_key', $mimsms_api_key);
        update_option('ofls_mimsms_secret_key', $mimsms_secret_key);
        update_option('ofls_mimsms_caller_id', $mimsms_caller_id);
        
        update_option('ofls_sms_otp_enabled', $otp_enabled);
        update_option('ofls_sms_otp_condition', $otp_condition);
        update_option('ofls_sms_otp_threshold', $otp_threshold);
        update_option('ofls_sms_otp_new_zero_enabled', $otp_new_zero_enabled);
        update_option('ofls_sms_otp_cooldown', $otp_cooldown);
        update_option('ofls_sms_otp_resend_limit', $otp_resend_limit);
        update_option('ofls_sms_otp_countdown', $otp_countdown);
        update_option('ofls_sms_otp_template', $otp_template);
        
        update_option('ofls_sms_placed_template', $placed_template);
        update_option('ofls_sms_shipped_template', $shipped_template);
        update_option('ofls_sms_notifications_enabled', $notifications_enabled);
        
        update_option('ofls_sms_cancelled_template', $cancelled_template);
        update_option('ofls_sms_shipping_template', $shipping_template);
        update_option('ofls_sms_confirmed_template', $confirmed_template);
        update_option('ofls_sms_processing_template', $processing_template);

        $redirect_url = admin_url('admin.php?page=fmb-engine-optimizer&tab=sms-tab-gateway&updated=1#platform-smart-sms');
        wp_redirect($redirect_url);
        exit;
    }

    public static function render_smart_sms_settings() {
        if (isset($_POST['save_smart_sms_settings'])) {
            check_admin_referer('smart_sms_settings_form_nonce')
                ? self::submit_smart_sms_settings()
                : self::render_error('Security check failed');
        }

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sms-tab-gateway';
        
        $sms_enabled = get_option('ofls_sms_enabled', false);
        $sms_provider = get_option('ofls_sms_provider', 'bulksmsbd');
        $bdbulksms_token = get_option('ofls_bdbulksms_token', '');
        $bulksmsbd_api_key = get_option('ofls_bulksmsbd_api_key', '');
        $bulksmsbd_senderid = get_option('ofls_bulksmsbd_senderid', '');
        
        $mimsms_api_key = get_option('ofls_mimsms_api_key', '');
        $mimsms_secret_key = get_option('ofls_mimsms_secret_key', '');
        $mimsms_caller_id = get_option('ofls_mimsms_caller_id', '');
        
        $otp_enabled = get_option('ofls_sms_otp_enabled', false);
        $otp_condition = get_option('ofls_sms_otp_condition', 'all');
        $otp_threshold = get_option('ofls_sms_otp_threshold', 80);
        $otp_new_zero_enabled = get_option('ofls_sms_otp_new_zero_enabled', true);
        $otp_cooldown = get_option('ofls_sms_otp_cooldown', 24);
        $otp_resend_limit = get_option('ofls_sms_otp_resend_limit', 3);
        $otp_countdown = get_option('ofls_sms_otp_countdown', 5);
        $otp_template = get_option('ofls_sms_otp_template', 'Your FMB Engine OTP is {otp_code}. Valid for {otp_countdown} minutes.');
        
        $placed_template = get_option('ofls_sms_placed_template', '');
        $shipped_template = get_option('ofls_sms_shipped_template', '');
        $notifications_enabled = get_option('ofls_sms_notifications_enabled', true);
        
        $cancelled_template = get_option('ofls_sms_cancelled_template', '');
        $shipping_template = get_option('ofls_sms_shipping_template', '');
        $confirmed_template = get_option('ofls_sms_confirmed_template', '');
        $processing_template = get_option('ofls_sms_processing_template', '');

        ?>
        <div class="platform-tabs">
            <a href="#sms-tab-gateway" class="platform-tab <?= $current_tab === 'sms-tab-gateway' || $current_tab === 'smart-sms' || empty($current_tab) ? 'active' : '' ?>" data-tab="sms-tab-gateway" onclick="window.fmbEngineSwitchTab('sms-tab-gateway', this); return false;">
                SMS Gateway
            </a>
            <a href="#sms-tab-otp" class="platform-tab <?= $current_tab === 'sms-tab-otp' ? 'active' : '' ?>" data-tab="sms-tab-otp" onclick="window.fmbEngineSwitchTab('sms-tab-otp', this); return false;">
                Smart OTP
            </a>
            <a href="#sms-tab-notifications" class="platform-tab <?= $current_tab === 'sms-tab-notifications' ? 'active' : '' ?>" data-tab="sms-tab-notifications" onclick="window.fmbEngineSwitchTab('sms-tab-notifications', this); return false;">
                Order Notifications
            </a>
            <a href="#sms-tab-dashboard" class="platform-tab <?= $current_tab === 'sms-tab-dashboard' ? 'active' : '' ?>" data-tab="sms-tab-dashboard" onclick="window.fmbEngineSwitchTab('sms-tab-dashboard', this); return false;">
                SMS Dashboard
            </a>
        </div>
        
        <form method="post">
            <?php wp_nonce_field('smart_sms_settings_form_nonce'); ?>
            <div class="fmb-engine-settings-content">
                <div class="fmb-engine-section">
                
                    <!-- SMS Dashboard Tab -->
                    <div id="sms-tab-dashboard" class="platform-tab-content" style="<?= $current_tab === 'sms-tab-dashboard' ? 'display: block;' : 'display: none;' ?>">

                        <?php
                        $smart_sms = \fmb_engine\SMS\Smart_SMS::instance();
                        $sms_balance = $smart_sms->get_sms_balance();
                        $total_outgoing = (int) get_option( 'ofls_total_outgoing_sms', 0 );
                        $total_otp = (int) get_option( 'ofls_total_otp_sent', 0 );
                        $recent_logs = get_option( 'ofls_recent_sms_logs', [] );
                        ?>

                        <style>
                            @media (max-width: 1024px) {
                                .ofls-dashboard-grid { grid-template-columns: repeat(2, 1fr) !important; }
                            }
                            @media (max-width: 768px) {
                                .ofls-dashboard-grid { grid-template-columns: 1fr !important; }
                            }
                        </style>
                        <div class="ofls-dashboard-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 0px; margin-bottom: 30px;">
                            <div class="ofls-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #197278; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center;">
                                    <div style="margin-right: 15px; color: #197278;">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                                    </div>
                                    <div style="font-size: 13px; color: #64748b; font-weight: 500;"><?= $sms_provider === 'bulksmsbd' ? 'Available Balance (TK)' : 'Available SMS' ?></div>
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-left: 15px;"><?= esc_html($sms_balance) ?></div>
                            </div>
                            
                            <div class="ofls-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center;">
                                    <div style="margin-right: 15px; color: #3b82f6;">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"></path><path d="M22 2L15 22L11 13L2 9L22 2Z"></path></svg>
                                    </div>
                                    <div style="font-size: 13px; color: #64748b; font-weight: 500;">Total Outgoing SMS</div>
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-left: 15px;"><?= esc_html($total_outgoing) ?></div>
                            </div>

                            <div class="ofls-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #f59e0b; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center;">
                                    <div style="margin-right: 15px; color: #f59e0b;">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    </div>
                                    <div style="font-size: 13px; color: #64748b; font-weight: 500;">Total SMS Sent</div>
                                </div>
                                <div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-left: 15px;"><?= esc_html($total_otp) ?></div>
                            </div>
                        </div>

                        <div class="ofls-logs-container" style="background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
                            <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600; color: #334155; display: flex; justify-content: space-between; align-items: center;">
                                <span>Outgoing SMS List</span>
                                <select id="ofls_logs_filter" style="font-size: 13px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; background: #fff; color: #475569;">
                                    <option value="last_15">Last 15 Items</option>
                                    <option value="today">Today</option>
                                    <option value="last_7_days">Last 7 Days</option>
                                </select>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <th style="padding: 12px 20px; color: #64748b; font-weight: 500;">Number</th>
                                        <th style="padding: 12px 20px; color: #64748b; font-weight: 500;">Message</th>
                                        <th style="padding: 12px 20px; color: #64748b; font-weight: 500;">Date</th>
                                        <th style="padding: 12px 20px; color: #64748b; font-weight: 500;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="ofls_logs_tbody">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const allLogs = <?= json_encode($recent_logs) ?>;
                            const tbody = document.getElementById('ofls_logs_tbody');
                            const filterSelect = document.getElementById('ofls_logs_filter');
                            
                            function renderLogs() {
                                const filter = filterSelect.value;
                                let filteredLogs = [];
                                
                                const now = new Date();
                                const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                                const sevenDaysAgoStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7).getTime();
                                
                                if (filter === 'today') {
                                    filteredLogs = allLogs.filter(log => {
                                        return new Date(log.date).getTime() >= todayStart;
                                    });
                                } else if (filter === 'last_7_days') {
                                    filteredLogs = allLogs.filter(log => {
                                        return new Date(log.date).getTime() >= sevenDaysAgoStart;
                                    });
                                } else {
                                    // default last 15
                                    filteredLogs = allLogs;
                                }
                                
                                // Limit to 15 items
                                filteredLogs = filteredLogs.slice(0, 15);
                                
                                tbody.innerHTML = '';
                                
                                if (filteredLogs.length === 0) {
                                    tbody.innerHTML = '<tr><td colspan="4" style="padding: 20px; text-align: center; color: #94a3b8;">No messages found.</td></tr>';
                                    return;
                                }
                                
                                filteredLogs.forEach(log => {
                                    const tr = document.createElement('tr');
                                    tr.style.borderBottom = '1px solid #f1f5f9';
                                    
                                    // Format date (dd/mm/yyyy - hh:mm:ss AM/PM)
                                    const d = new Date(log.date);
                                    const formattedDate = ("0" + d.getDate()).slice(-2) + "/" + 
                                                        ("0" + (d.getMonth() + 1)).slice(-2) + "/" + 
                                                        d.getFullYear() + " - " + 
                                                        d.toLocaleTimeString('en-US');
                                    
                                    tr.innerHTML = `
                                        <td style="padding: 12px 20px; color: #334155; font-family: monospace;">${log.phone}</td>
                                        <td style="padding: 12px 20px; color: #475569; max-width: 300px;">${log.message.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
                                        <td style="padding: 12px 20px; color: #64748b; font-size: 13px;">${formattedDate}</td>
                                        <td style="padding: 12px 20px;"><span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">${log.status}</span></td>
                                    `;
                                    tbody.appendChild(tr);
                                });
                            }
                            
                            filterSelect.addEventListener('change', renderLogs);
                            renderLogs();
                        });
                        </script>
                    </div>

                    <!-- SMS Gateway Tab -->
                    <div id="sms-tab-gateway" class="platform-tab-content" style="<?= $current_tab === 'sms-tab-gateway' || $current_tab === 'smart-sms' || empty($current_tab) ? 'display: block;' : 'display: none;' ?>">
                        <h2 class="fmb-engine-section-title">SMS Gateway</h2>
                        <p class="fmb-engine-subtitle">Configure your SMS gateway credentials.</p>
                        
                        <div class="fmb-engine-form-container">
                            
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Enable Smart SMS</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="ofls_sms_enabled" value="1" <?= checked($sms_enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">SMS Provider</div>
                                <div class="fmb-engine-field-input">
                                    <select name="ofls_sms_provider" id="ofls_sms_provider">
                                        <option value="bdbulksms" <?= selected($sms_provider, 'bdbulksms'); ?>>Bdbulksms(Greenweb)</option>
                                        <option value="bulksmsbd" <?= selected($sms_provider, 'bulksmsbd'); ?>>BulkSMSBD</option>
                                        <option value="mimsms" <?= selected($sms_provider, 'mimsms'); ?>>Custom SMS (Custom API)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Bdbulksms(Greenweb) Credentials -->
                            <div class="provider-credentials" id="credentials-bdbulksms" style="<?= $sms_provider === 'bdbulksms' ? 'display: block;' : 'display: none;' ?>">
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">Bdbulksms(Greenweb) Token</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="password" name="ofls_bdbulksms_token" value="<?= esc_attr($bdbulksms_token); ?>" placeholder="Enter Token">
                                        <p style="font-size: 11px; color:#64748b; margin-top:8px;">Don't have a token? <a href="https://sms.bdbulksms.com/gen_token.php" target="_blank" style="color:#197278; text-decoration:underline; font-weight:500;">Generate token here &rarr;</a></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- BulkSMSBD Credentials -->
                            <div class="provider-credentials" id="credentials-bulksmsbd" style="<?= $sms_provider === 'bulksmsbd' ? 'display: block;' : 'display: none;' ?>">
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">BulkSMSBD API Key</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="password" name="ofls_bulksmsbd_api_key" value="<?= esc_attr($bulksmsbd_api_key); ?>" placeholder="Enter API Key">
                                        <p style="font-size: 11px; color:#64748b; margin-top:8px;">Don't have an API key? <a href="https://bulksmsbd.net/developers" target="_blank" style="color:#197278; text-decoration:underline; font-weight:500;">Get one here &rarr;</a></p>
                                    </div>
                                </div>
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">BulkSMSBD Sender ID</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="text" name="ofls_bulksmsbd_senderid" value="<?= esc_attr($bulksmsbd_senderid); ?>" placeholder="Enter Sender ID">
                                        <p style="font-size: 11px; color:#64748b; margin-top:8px;">Don't have a Sender ID? <a href="https://bulksmsbd.net/developers" target="_blank" style="color:#197278; text-decoration:underline; font-weight:500;">Get one here &rarr;</a></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Custom SMS Credentials -->
                            <div class="provider-credentials" id="credentials-mimsms" style="<?= $sms_provider === 'mimsms' ? 'display: block;' : 'display: none;' ?>">
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">API Key</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="password" name="ofls_mimsms_api_key" value="<?= esc_attr($mimsms_api_key); ?>" placeholder="Enter API Key">
                                    </div>
                                </div>
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">Secret Key</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="password" name="ofls_mimsms_secret_key" value="<?= esc_attr($mimsms_secret_key); ?>" placeholder="Enter Secret Key">
                                    </div>
                                </div>
                                <div class="fmb-engine-field-row">
                                    <div class="fmb-engine-field-label">Caller ID</div>
                                    <div class="fmb-engine-field-input">
                                        <input type="text" name="ofls_mimsms_caller_id" value="<?= esc_attr($mimsms_caller_id); ?>" placeholder="Enter Caller ID">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Test Message System Divider is handled by the previous row's border-bottom -->
                            
                            <h3 style="font-size: 16px; margin-bottom: 12px; color: #334155;">Test Message System</h3>
                            <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Send a test message to verify your API credentials are working correctly.</p>
                            
                            <div class="fmb-engine-field-row" style="border-bottom: unset;">
                                <div class="fmb-engine-field-label">Test Phone Number</div>
                                <div class="fmb-engine-field-input">
                                    <div style="display: flex; gap: 10px; align-items: stretch; max-width: 350px; width: 100%;">
                                        <input type="text" id="ofls_test_sms_phone" placeholder="e.g. 01712345678" style="flex: 1; max-width: none; margin: 0; height: 100%;">
                                        <button type="button" id="ofls_test_sms_btn" class="button fmb-engine-action-button" style="white-space: nowrap; margin: 0; display: flex; align-items: center; justify-content: center; height: auto;">Send Test SMS</button>
                                    </div>
                                    <div id="ofls_test_sms_result" style="margin-top: 8px; font-size: 13px; font-weight: 500; display: block; min-height: 18px;"></div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Smart OTP Tab -->
                    <div id="sms-tab-otp" class="platform-tab-content" style="<?= $current_tab === 'sms-tab-otp' ? 'display: block;' : 'display: none;' ?>">
                        <h2 class="fmb-engine-section-title">Smart OTP Settings</h2>
                        <p class="fmb-engine-subtitle">Configure your smart OTP thresholds and templates.</p>
                        
                        <div class="fmb-engine-form-container">
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Enable Checkout OTP</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="ofls_sms_otp_enabled" value="1" <?= checked($otp_enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">OTP Trigger Condition</div>
                                <div class="fmb-engine-field-input">
                                    <select name="ofls_sms_otp_condition" id="ofls_sms_otp_condition">
                                        <option value="all" <?= selected($otp_condition, 'all'); ?>>For All Customers</option>
                                        <option value="ratio" <?= selected($otp_condition, 'ratio'); ?>>Based on Courier Ratio Threshold</option>
                                    </select>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" id="ofls_sms_otp_threshold_row" style="<?= $otp_condition === 'ratio' ? 'display: flex;' : 'display: none;' ?>">
                                <div class="fmb-engine-field-label">Courier Ratio Threshold (%)</div>
                                <div class="fmb-engine-field-input">
                                    <input type="number" name="ofls_sms_otp_threshold" value="<?= esc_attr($otp_threshold); ?>" min="0" max="100">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Customers with a success ratio BELOW this percentage will be forced to verify OTP.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" id="ofls_sms_otp_new_zero_row" style="<?= $otp_condition === 'ratio' ? 'display: flex;' : 'display: none;' ?>">
                                <div class="fmb-engine-field-label">OTP for New & 0% Ratio Customers</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="ofls_sms_otp_new_zero_enabled" value="1" <?= checked($otp_new_zero_enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">If disabled, new customers (no history) and customers with 0% success ratio will NOT be asked for OTP.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" id="ofls_sms_otp_cooldown_row">
                                <div class="fmb-engine-field-label">OTP Verification Cooldown Timing</div>
                                <div class="fmb-engine-field-input">
                                    <input type="number" name="ofls_sms_otp_cooldown" value="<?= esc_attr($otp_cooldown); ?>" min="0" max="72">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">In hours. If the same number checks out within this timeframe after a successful verification, the OTP is bypassed. Max 72 hours.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" id="ofls_sms_otp_resend_limit_row">
                                <div class="fmb-engine-field-label">Resend OTP Code Max Attempts</div>
                                <div class="fmb-engine-field-input">
                                    <input type="number" name="ofls_sms_otp_resend_limit" value="<?= esc_attr($otp_resend_limit); ?>" min="0">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">How many times a user can resend the OTP before being temporarily blocked (0 for unlimited).</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row" id="ofls_sms_otp_countdown_row">
                                <div class="fmb-engine-field-label">OTP Countdown Time</div>
                                <div class="fmb-engine-field-input">
                                    <input type="number" name="ofls_sms_otp_countdown" value="<?= esc_attr($otp_countdown); ?>" min="1">
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">In minutes. How long the OTP code is valid and how long the popup timer lasts.</p>
                                </div>
                            </div>
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">OTP SMS Template</div>
                                <div class="fmb-engine-field-input">
                                    <textarea name="ofls_sms_otp_template" rows="3"><?= esc_textarea($otp_template); ?></textarea>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Use <code>{otp_code}</code> to dynamically insert the OTP.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Notifications Tab -->
                    <div id="sms-tab-notifications" class="platform-tab-content" style="<?= $current_tab === 'sms-tab-notifications' ? 'display: block;' : 'display: none;' ?>">
                        <h2 class="fmb-engine-section-title">Order Status Notifications</h2>
                        <p class="fmb-engine-subtitle">Configure automated SMS alerts for different order statuses.</p>
                        
                        <div class="fmb-engine-form-container">
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Enable Order Notifications</div>
                                <div class="fmb-engine-field-input">
                                    <label class="fmb-engine-toggle-switch">
                                        <input type="checkbox" name="ofls_sms_notifications_enabled" value="1" <?= checked($notifications_enabled); ?>>
                                        <span class="fmb-engine-slider"></span>
                                    </label>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Turn on/off automated order status SMS alerts.</p>
                                </div>
                            </div>
                            
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Order Confirmed Template</div>
                                <div class="fmb-engine-field-input">
                                    <textarea name="ofls_sms_confirmed_template" rows="3" style="width: 100%; max-width: 400px; padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e1;" placeholder="Leave empty to disable. E.g. Hi {name}, your order #{order_id} has been confirmed."><?= esc_textarea($confirmed_template); ?></textarea>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Available tags: <code>{name}</code>, <code>{order_id}</code>, <code>{total}</code></p>
                                </div>
                            </div>
                            
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Order Processing Template</div>
                                <div class="fmb-engine-field-input">
                                    <textarea name="ofls_sms_processing_template" rows="3" style="width: 100%; max-width: 400px; padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e1;" placeholder="Leave empty to disable. E.g. Hi {name}, your order #{order_id} is now processing."><?= esc_textarea($processing_template); ?></textarea>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Available tags: <code>{name}</code>, <code>{order_id}</code>, <code>{total}</code></p>
                                </div>
                            </div>

                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Order Shipping Template</div>
                                <div class="fmb-engine-field-input">
                                    <textarea name="ofls_sms_shipping_template" rows="3" style="width: 100%; max-width: 400px; padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e1;" placeholder="Leave empty to disable. E.g. Hi {name}, your order #{order_id} is out for shipping."><?= esc_textarea($shipping_template); ?></textarea>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Available tags: <code>{name}</code>, <code>{order_id}</code>, <code>{tracking_code}</code></p>
                                </div>
                            </div>
                            
                            <div class="fmb-engine-field-row">
                                <div class="fmb-engine-field-label">Order Cancelled Template</div>
                                <div class="fmb-engine-field-input">
                                    <textarea name="ofls_sms_cancelled_template" rows="3" style="width: 100%; max-width: 400px; padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e1;" placeholder="Leave empty to disable. E.g. Hi {name}, your order #{order_id} was cancelled."><?= esc_textarea($cancelled_template); ?></textarea>
                                    <p style="font-size: 11px; color:#666; margin-top:4px;">Available tags: <code>{name}</code>, <code>{order_id}</code>, <code>{total}</code></p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            <div id="smart-sms-submit-container" style="padding: 0 24px 24px; <?= $current_tab === 'sms-tab-dashboard' ? 'display: none;' : '' ?>">
                <button type="submit" class="fmb-engine-submit-button" name="save_smart_sms_settings" style="background-color: #197278; color: white;">
                    Save Settings
                </button>
            </div>
        </form>
        
        <script>
            jQuery(document).ready(function($) {
                $('#ofls_sms_otp_condition').on('change', function() {
                    if ($(this).val() === 'ratio') {
                        $('#ofls_sms_otp_threshold_row, #ofls_sms_otp_new_zero_row').css('display', 'flex').hide().fadeIn();
                    } else {
                        $('#ofls_sms_otp_threshold_row, #ofls_sms_otp_new_zero_row').fadeOut();
                    }
                });
                
                $('#ofls_sms_provider').on('change', function() {
                    $('.provider-credentials').hide();
                    $('#credentials-' + $(this).val()).fadeIn();
                });
                
                $('#ofls_test_sms_btn').on('click', function() {
                    var phone = $('#ofls_test_sms_phone').val();
                    if (!phone) {
                        $('#ofls_test_sms_result').css('color', '#ef4444').text('Please enter a phone number.');
                        return;
                    }
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Sending...');
                    $('#ofls_test_sms_result').text('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fmb_engine_test_sms',
                            phone: phone,
                            nonce: '<?php echo wp_create_nonce("fmb_engine_test_sms_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#ofls_test_sms_result').css('color', '#10b981').text(response.data.message);
                            } else {
                                $('#ofls_test_sms_result').css('color', '#ef4444').text(response.data.message || 'Failed to send SMS.');
                            }
                        },
                        error: function() {
                            $('#ofls_test_sms_result').css('color', '#ef4444').text('Network error occurred.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Send Test SMS');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    private static function render_error($message) {
        echo '<div class="fmb-engine-error"><p>' . esc_html($message) . '.</p></div>';
    }
}
