<?php
namespace fmb_engine\Admin;

use fmb_engine\Traits\Core;

/**
 * Class Renewal_Popup
 * Handles the logic and display of the subscription renewal notification popup.
 */
class Renewal_Popup {

    /**
     * Initialize the popup hooks
     */
    public function __construct() {
        add_action('admin_footer', [$this, 'render_popup']);
        add_action('wp_ajax_ofls_dismiss_renewal_popup', [$this, 'handle_dismissal']);
    }

    /**
     * Check if popup should be displayed and render it
     */
    public function render_popup() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Must have Core trait available for license checks
        if (!class_exists('\fmb_engine\Core') && !trait_exists('\fmb_engine\Traits\Core')) {
            return;
        }

        // Get license info using an anonymous class that uses the trait, 
        // or just use \fmb_engine\Core since it uses the trait and is loaded
        $is_active = \fmb_engine\Core::is_license_active();
        if (!$is_active) {
            return;
        }

        $license_info = \fmb_engine\Core::get_license_info();
        $type = isset($license_info['type']) ? $license_info['type'] : '';

        // Exclude lifetime packages
        if (strtolower($type) === 'lifetime') {
            return;
        }

        $remaining_days = \fmb_engine\Core::get_remaining_days();

        // Only trigger on exactly 3 days or 1 day remaining
        if ($remaining_days !== 3 && $remaining_days !== 1) {
            return;
        }

        // Check if user already dismissed it for this specific day milestone
        $user_id = get_current_user_id();
        $dismissed_key = 'ofls_renewal_dismissed_' . $remaining_days;
        $is_dismissed = get_user_meta($user_id, $dismissed_key, true);

        if ($is_dismissed) {
            if ($remaining_days === 1) {
                // For 1 day remaining, show again after 1 hour (3600 seconds)
                $dismissed_time = strtotime($is_dismissed);
                $current_time = current_time('timestamp');
                if (($current_time - $dismissed_time) < 3600) {
                    return; // Still within the 1-hour window
                }
            } else {
                // Already dismissed forever for the 3-day milestone
                return; 
            }
        }

        $this->output_html($remaining_days);
    }

    /**
     * Handle the AJAX request to dismiss the popup
     */
    public function handle_dismissal() {
        check_ajax_referer('ofls_renewal_popup_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $days = isset($_POST['days']) ? intval($_POST['days']) : 0;
        
        if ($days === 3 || $days === 1) {
            $user_id = get_current_user_id();
            $dismissed_key = 'ofls_renewal_dismissed_' . $days;
            // Set the meta flag so it doesn't appear again for this specific day count
            update_user_meta($user_id, $dismissed_key, current_time('mysql'));
            wp_send_json_success();
        }

        wp_send_json_error(['message' => 'Invalid days parameter']);
    }

    /**
     * Output the popup HTML, CSS, and JS
     */
    private function output_html($remaining_days) {
        $nonce = wp_create_nonce('ofls_renewal_popup_nonce');
        $renew_link = 'https://FMB Enginebd.com/step/checkout-woo/';
        
        $subtitle = '';
        if ($remaining_days === 3) {
            $subtitle = 'আপনার প্যাকেজের মেয়াদ আর মাত্র <strong>৩ দিন</strong> আছে। বিরামহীন ট্র্যাকিং উপভোগ করতে এখনই রিনিউ করুন।';
        } else {
            $subtitle = 'আপনার প্যাকেজের মেয়াদ আর মাত্র <strong>১ দিন</strong> আছে! এখনই রিনিউ করুন।';
        }

        ?>
        <style>
            .ofls-renewal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            .ofls-renewal-overlay.active {
                opacity: 1;
                visibility: visible;
            }
            .ofls-renewal-popup {
                background: #ffffff;
                width: 100%;
                max-width: 550px;
                border-radius: 6px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                position: relative;
                overflow: hidden;
                transform: translateY(20px);
                transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                font-family: 'Poppins', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                border: 1px solid #f14c4c;
            
            }
            .ofls-renewal-overlay.active .ofls-renewal-popup {
                transform: translateY(0);
            }
            .ofls-renewal-header {
                background: #f14c4c;
                padding: 15px 24px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .ofls-renewal-title {
                margin: 0;
                font-size: 19px;
                font-weight: 500;
                color: #ffffff;
                line-height: 1;
                font-family: 'Poppins', sans-serif;
            }
            .ofls-renewal-close {
                background: transparent;
                border: 1px solid rgba(255, 255, 255, 0.6);
                color: #ffffff;
                cursor: pointer;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                transition: background 0.2s ease, border-color 0.2s ease;
            }
            .ofls-renewal-close:hover {
                background: rgba(255, 255, 255, 0.1);
                border-color: #ffffff;
            }
            .ofls-renewal-close svg {
                width: 18px;
                height: 18px;
                stroke-width: 1.5;
            }
            .ofls-renewal-body {
                padding: 30px 30px 20px 30px;
                text-align: center;
            }
            .ofls-renewal-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #f14c4c;
                margin-bottom: 24px;
            }
            .ofls-renewal-icon svg {
                width: 72px;
                height: 72px;
                stroke-width: 2.5;
            }
            .ofls-renewal-heading {
                font-size: 21px;
                font-weight: 600;
                color: #1a202c;
                margin: 0 0 16px 0 !important;
            }
            .ofls-renewal-subtitle {
                font-size: 20px;
                color: #4a5568;
                line-height: 1.6;
                margin: 0 0 32px 0;
            }
            .ofls-renewal-subtitle strong {
                color: #e53e3e;
                font-weight: 700;
            }
            .ofls-renewal-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #197278;
                color: #ffffff;
                text-decoration: none;
                padding: 16px 45px;
                border-radius: 4px;
                font-size: 20px;
                font-weight: 500;
                transition: background 0.2s ease, transform 0.1s ease;
                border: none;
                cursor: pointer;
                margin-bottom: 10px;
            }
            .ofls-renewal-btn:hover {
                background: #145b60;
                color: #ffffff;
            }
            .ofls-renewal-btn:active {
                transform: scale(0.98);
            }
            .ofls-renewal-footer {
                text-align: center;
                padding-bottom: 24px;
                color: #9ca3af;
                font-size: 13px;
                font-weight: 400;
            }
        </style>

        <div class="ofls-renewal-overlay" id="ofls-renewal-modal">
            <div class="ofls-renewal-popup">
                <div class="ofls-renewal-header">
                    <h2 class="ofls-renewal-title">FMB Engine Package Alert</h2>
                    <button class="ofls-renewal-close" id="ofls-renewal-close-btn" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="ofls-renewal-body">
                    <div class="ofls-renewal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <h3 class="ofls-renewal-heading">আপনার প্যাকেজের মেয়াদ শেষ হতে চলেছে!</h3>
                    <p class="ofls-renewal-subtitle"><?php echo $subtitle; ?></p>
                    <a href="<?php echo esc_url($renew_link); ?>" target="_blank" class="ofls-renewal-btn">
                        এখনই রিনিউ করুন
                    </a>
                </div>
                <div class="ofls-renewal-footer">
                    FMB Engine - Your Fake Order Solution
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('ofls-renewal-modal');
                var closeBtn = document.getElementById('ofls-renewal-close-btn');
                
                if (!modal) return;

                // Show modal with a slight delay for smooth entry
                setTimeout(function() {
                    modal.classList.add('active');
                }, 500);

                // Handle Dismissal
                closeBtn.addEventListener('click', function() {
                    // Hide immediately
                    modal.classList.remove('active');
                    
                    // Send AJAX request
                    var data = new FormData();
                    data.append('action', 'ofls_dismiss_renewal_popup');
                    data.append('nonce', '<?php echo esc_js($nonce); ?>');
                    data.append('days', '<?php echo esc_js($remaining_days); ?>');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: data
                    }).then(response => response.json())
                      .catch(error => console.error('Error dismissing popup:', error));
                });
            });
        </script>
        <?php
    }
}
