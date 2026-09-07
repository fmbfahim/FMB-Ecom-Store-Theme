<?php
namespace fmb_engine\SMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Smart_SMS {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if ( ! get_option( 'ofls_sms_enabled', false ) ) {
            return;
        }

        // OTP Checkout logic
        if ( get_option( 'ofls_sms_otp_enabled', false ) ) {
            add_action( 'woocommerce_after_checkout_validation', [ $this, 'intercept_checkout_for_otp' ], 10, 2 );
            add_action( 'wp_ajax_nopriv_orderflow_verify_otp', [ $this, 'ajax_verify_otp' ] );
            add_action( 'wp_ajax_orderflow_verify_otp', [ $this, 'ajax_verify_otp' ] );
            add_action( 'wp_ajax_nopriv_orderflow_send_otp', [ $this, 'ajax_send_otp' ] );
            add_action( 'wp_ajax_orderflow_send_otp', [ $this, 'ajax_send_otp' ] );
            add_action( 'wp_footer', [ $this, 'inject_otp_modal' ] );
        }

        // Clear temporary OTP session when an order is successfully processed
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'clear_otp_session_after_checkout' ], 10, 3 );
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'send_order_placed_sms' ], 10, 3 );
        add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_changed_sms' ], 10, 4 );

        // Test SMS
        add_action( 'wp_ajax_orderflow_test_sms', [ $this, 'ajax_test_sms' ] );
    }

    /**
     * Helper method to send SMS via the selected API provider
     */
    public function send_sms( $phone, $message, $is_otp = false ) {
        if ( empty( $phone ) || empty( $message ) ) {
            return false;
        }

        // Decode HTML entities (e.g. &#2547; to ৳) and normalize non-breaking spaces
        $message = html_entity_decode( $message, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $message = str_replace( [ "\xC2\xA0", '&nbsp;' ], ' ', $message );

        $provider = get_option('ofls_sms_provider', 'bulksmsbd');

        // Normalize phone for Bangladesh to 11 digits (e.g., 017...)
        $phone = preg_replace( '/[^\d]/', '', $phone );
        if ( strpos( $phone, '880' ) === 0 ) {
            $phone = substr( $phone, 3 );
        } elseif ( strpos( $phone, '+880' ) === 0 ) {
            $phone = substr( $phone, 4 );
        }

        if ($provider === 'bdbulksms') {
            $token = trim(get_option('ofls_bdbulksms_token', ''));
            if (empty($token)) {
                return new \WP_Error('empty_token', 'API Token is empty.');
            }

            $url = 'https://api.bdbulksms.net/api.php';
            $query_args = [
                'token' => $token,
                'to' => '880' . ltrim($phone, '0'),
                'message' => $message
            ];

            $response = wp_remote_post( $url, [
                'timeout' => 10,
                'body' => $query_args
            ]);

            if ( is_wp_error( $response ) ) {
                error_log( 'FMB Engine Bdbulksms Error: ' . $response->get_error_message() );
                return $response;
            }

            $body = wp_remote_retrieve_body( $response );
            // Bdbulksms returns plain text per line, success usually starts with "Ok: " or similar depending on the docs
            // Let's just check if it contains "Error" to decide
            if ( stripos( $body, 'Error' ) !== false || stripos( $body, 'Invalid' ) !== false ) {
                error_log( 'FMB Engine Bdbulksms Failed: ' . print_r( $body, true ) );
                return new \WP_Error('api_error', sanitize_text_field($body));
            }

            $this->log_successful_sms( $phone, $message, $is_otp );
            return true;

        } elseif ($provider === 'bulksmsbd') {
            $api_key = trim(get_option('ofls_bulksmsbd_api_key', ''));
            $senderid = trim(get_option('ofls_bulksmsbd_senderid', ''));
            
            if (empty($api_key) || empty($senderid)) {
                return new \WP_Error('empty_credentials', 'API Key or Sender ID is empty.');
            }

            $url = 'https://bulksmsbd.net/api/smsapi';
            
            // Format phone to 880xxxxxxxxxx as per docs
            $formatted_phone = '880' . ltrim($phone, '0');
            
            $body = [
                'api_key' => $api_key,
                'senderid' => $senderid,
                'number' => $formatted_phone,
                'message' => $message
            ];

            $response = wp_remote_post( $url, [
                'timeout' => 10,
                'body' => $body
            ]);

            if ( is_wp_error( $response ) ) {
                error_log( 'FMB Engine Bulksmsbd HTTP Error: ' . $response->get_error_message() );
                return $response;
            }

            $response_body = wp_remote_retrieve_body( $response );
            $data = json_decode( $response_body, true );

            if ( ! empty( $data['error_message'] ) ) {
                error_log( 'FMB Engine Bulksmsbd API Error: ' . print_r( $response_body, true ) );
                return new \WP_Error( 'api_error', sanitize_text_field( $data['error_message'] ) );
            }

            $this->log_successful_sms( $phone, $message, $is_otp );
            return true;

        } elseif ($provider === 'mimsms') {
            $api_key = trim(get_option('ofls_mimsms_api_key', ''));
            $secret_key = trim(get_option('ofls_mimsms_secret_key', ''));
            $caller_id = trim(get_option('ofls_mimsms_caller_id', ''));
            
            if (empty($api_key) || empty($secret_key) || empty($caller_id)) {
                return new \WP_Error('empty_credentials', 'API Key, Secret Key, or Caller ID is empty.');
            }

            $url = 'http://103.17.150.82:2779/sendtext';
            $formatted_phone = '880' . ltrim($phone, '0');
            
            $body = [
                'apikey' => $api_key,
                'secretkey' => $secret_key,
                'callerID' => $caller_id,
                'toUser' => $formatted_phone,
                'messageContent' => $message
            ];

            $response = wp_remote_post( $url, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($body)
            ]);

            if ( is_wp_error( $response ) ) {
                error_log( 'FMB Engine MimSMS HTTP Error: ' . $response->get_error_message() );
                return $response;
            }

            $this->log_successful_sms( $phone, $message, $is_otp );
            return true;

        } else {
            return false;
        }
    }

    private function log_successful_sms( $phone, $message, $is_otp ) {
        // Increment total outgoing
        $total_outgoing = (int) get_option( 'ofls_total_outgoing_sms', 0 );
        update_option( 'ofls_total_outgoing_sms', $total_outgoing + 1 );

        if ( $is_otp ) {
            $total_otp = (int) get_option( 'ofls_total_otp_sent', 0 );
            update_option( 'ofls_total_otp_sent', $total_otp + 1 );
            
            // Log for Live Activity Feed
            if ( class_exists( '\fmb_engine\Core' ) && method_exists( '\fmb_engine\Core', 'log_activity' ) ) {
                \fmb_engine\Core\Base::log_activity( "{$phone} নম্বরে সফলভাবে ওটিপি (OTP) পাঠানো হয়েছে।", '💬' );
            }
        }

        // Add to recent logs
        $logs = get_option( 'ofls_recent_sms_logs', [] );
        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        $log_entry = [
            'phone'   => $phone,
            'message' => $message,
            'date'    => current_time( 'mysql' ),
            'status'  => 'Delivered'
        ];

        array_unshift( $logs, $log_entry );

        if ( count( $logs ) > 500 ) {
            $logs = array_slice( $logs, 0, 500 );
        }

        update_option( 'ofls_recent_sms_logs', $logs );
    }

    public function get_sms_balance() {
        $cached_balance = get_transient( 'ofls_sms_balance_v2' );
        if ( $cached_balance !== false ) {
            return $cached_balance;
        }

        $provider = get_option('ofls_sms_provider', 'bulksmsbd');
        $balance = 'N/A';

        if ( $provider === 'bdbulksms' ) {
            $token = trim(get_option('ofls_bdbulksms_token', ''));
            if ( ! empty( $token ) ) {
                $url = 'https://api.bdbulksms.net/api.php?token=' . urlencode($token) . '&balance=1';
                $response = wp_remote_get( $url, ['timeout' => 5] );
                if ( ! is_wp_error( $response ) ) {
                    $body = wp_remote_retrieve_body( $response );
                    $balance = trim(strip_tags($body));
                }
            }
        } elseif ( $provider === 'bulksmsbd' ) {
            $api_key = trim(get_option('ofls_bulksmsbd_api_key', ''));
            if ( ! empty( $api_key ) ) {
                $url = 'http://bulksmsbd.net/api/getBalanceApi?api_key=' . urlencode($api_key);
                $response = wp_remote_get( $url, ['timeout' => 5] );
                if ( ! is_wp_error( $response ) ) {
                    $body = wp_remote_retrieve_body( $response );
                    $data = json_decode( $body, true );
                    if ( isset( $data['balance'] ) ) {
                        $balance = $data['balance'];
                    } else {
                        $balance = trim(strip_tags($body));
                    }
                }
            }
        } else {
            $balance = 'N/A';
        }

        if ( $balance !== 'N/A' ) {
            set_transient( 'ofls_sms_balance_v2', $balance, 5 * MINUTE_IN_SECONDS );
        }
        return $balance;
    }

    /**
     * AJAX Endpoint to test SMS sending
     */
    public function ajax_test_sms() {
        check_ajax_referer('orderflow_test_sms_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if (empty($phone)) {
            wp_send_json_error(['message' => 'Phone number is required.']);
        }

        $message = "Test message from FMB Engine Settings.";
        $result = $this->send_sms($phone, $message);

        if ($result === true) {
            wp_send_json_success(['message' => 'Test message sent successfully!']);
        } else {
            $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Check your credentials or logs.';
            wp_send_json_error(['message' => 'Failed: ' . $error_msg]);
        }
    }

    /**
     * Intercept WooCommerce checkout to force OTP validation
     */
    public function intercept_checkout_for_otp( $data, $errors ) {
        // Only run if there are no other major checkout errors (we want them to fix those first)
        if ( $errors->get_error_codes() ) {
            return;
        }

        $phone = isset( $data['billing_phone'] ) ? sanitize_text_field( $data['billing_phone'] ) : '';
        if ( empty( $phone ) ) {
            return;
        }

        // Check if phone is already verified in this session
        $session_key = 'ofls_otp_verified_' . md5( $phone . \WC_Geolocation::get_ip_address() );
        if ( get_transient( $session_key ) === 'verified' ) {
            return; // Already verified, allow checkout to proceed
        }

        // Check global phone cooldown verification
        $cooldown_key = 'ofls_verified_time_' . md5( $phone );
        if ( get_transient( $cooldown_key ) === 'verified' ) {
            return; // Phone was recently verified within cooldown window
        }

        $condition = get_option( 'ofls_sms_otp_condition', 'all' );
        $requires_otp = false;

        if ( $condition === 'all' ) {
            $requires_otp = true;
        } elseif ( $condition === 'ratio' ) {
            $threshold = (float) get_option( 'ofls_sms_otp_threshold', 80 );
            $new_zero_enabled = get_option( 'ofls_sms_otp_new_zero_enabled', true );

            $courier_checker = new class() {
                use \fmb_engine\Traits\Courier;
            };
            $courier_ratio = $courier_checker->get_courier_rate_by_phone( $phone );
            
            if ( $courier_ratio !== null ) {
                if ( ! $new_zero_enabled && in_array( $courier_ratio, [-1, 0, 0.0], true ) ) {
                    $requires_otp = false;
                } elseif ( $courier_ratio < $threshold ) {
                    $requires_otp = true;
                }
            }
        }

        if ( $requires_otp ) {
            // Throw silent error with marker to trigger modal
            $errors->add( 'ads_require_otp', '<span class="ads-otp-marker" data-phone="' . esc_attr( $phone ) . '" style="display:none;"></span>Please verify your phone number to complete the order.' );
        }
    }

    /**
     * AJAX Endpoint to send the OTP code
     */
    public function ajax_send_otp() {
        if ( ! isset( $_POST['phone'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid request' ] );
        }

        $phone = sanitize_text_field( $_POST['phone'] );
        $otp_code = wp_rand( 1000, 9999 );
        $otp_transient_key = 'ofls_otp_code_' . md5( $phone . \WC_Geolocation::get_ip_address() );
        
        // Check resend limits
        $resend_limit = (int) get_option( 'ofls_sms_otp_resend_limit', 3 );
        $resend_count_key = 'ofls_otp_resend_count_' . md5( $phone );
        $resend_count = (int) get_transient( $resend_count_key );

        if ( $resend_limit > 0 && $resend_count >= $resend_limit ) {
            wp_send_json_error( [ 'message' => 'আপনি ওটিপি পাঠানোর সর্বোচ্চ সীমা অতিক্রম করেছেন। দয়া করে কিছুক্ষণ পর আবার চেষ্টা করুন।' ] );
        }

        // Check if recently sent to prevent spamming
        $last_sent = get_transient( $otp_transient_key . '_time' );
        if ( $last_sent ) {
            wp_send_json_error( [ 'message' => 'দয়া করে একটু অপেক্ষা করুন, কিছুক্ষণ আগেই একটি কোড পাঠানো হয়েছে।' ] );
        }

        $countdown_minutes = (int) get_option( 'ofls_sms_otp_countdown', 5 );
        if ( $countdown_minutes < 1 ) $countdown_minutes = 5;

        set_transient( $otp_transient_key, $otp_code, $countdown_minutes * MINUTE_IN_SECONDS );
        set_transient( $otp_transient_key . '_time', time(), 60 ); // 60s cooldown
        
        // increment resend count (block lasts for 1 hour)
        set_transient( $resend_count_key, $resend_count + 1, HOUR_IN_SECONDS );

        $template = get_option( 'ofls_sms_otp_template', 'Your FMB Engine OTP is {otp_code}. Valid for {otp_countdown} minutes.' );
        $message = str_replace( '{otp_code}', $otp_code, $template );
        $message = str_replace( '{otp_countdown}', $countdown_minutes, $message );
        
        $this->send_sms( $phone, $message, true );
        
        wp_send_json_success( [ 'message' => 'OTP sent successfully' ] );
    }

    /**
     * AJAX Endpoint to verify the OTP code
     */
    public function ajax_verify_otp() {
        if ( ! isset( $_POST['phone'] ) || ! isset( $_POST['otp'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid request' ] );
        }

        $phone = sanitize_text_field( $_POST['phone'] );
        $otp = sanitize_text_field( $_POST['otp'] );
        $ip = \WC_Geolocation::get_ip_address();

        $otp_transient_key = 'ofls_otp_code_' . md5( $phone . $ip );
        $saved_otp = get_transient( $otp_transient_key );

        if ( $saved_otp && (string) $saved_otp === (string) $otp ) {
            // Mark as verified for session
            $session_key = 'ofls_otp_verified_' . md5( $phone . $ip );
            set_transient( $session_key, 'verified', 2 * HOUR_IN_SECONDS );
            
            // Mark as verified globally for the phone for Cooldown duration
            $cooldown_hours = (int) get_option( 'ofls_sms_otp_cooldown', 24 );
            if ( $cooldown_hours > 72 ) {
                $cooldown_hours = 72;
            }
            if ( $cooldown_hours > 0 ) {
                $cooldown_key = 'ofls_verified_time_' . md5( $phone );
                set_transient( $cooldown_key, 'verified', $cooldown_hours * HOUR_IN_SECONDS );
            }
            
            // Delete OTP code
            delete_transient( $otp_transient_key );
            
            wp_send_json_success( [ 'message' => 'Verified successfully' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Invalid or expired OTP code' ] );
        }
    }

    /**
     * Inject OTP Modal and JS into footer on checkout
     */
    public function inject_otp_modal() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
            return;
        }
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap');
        
        #fmb-engine-otp-modal {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.65); z-index: 999999;
            align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        #fmb-engine-otp-modal.otp-modal-visible {
            display: flex; opacity: 1; animation: otpModalFadeIn 0.3s forwards;
        }
        @keyframes otpModalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .otp-modal-container {
            background: #ffffff; border-radius: 6px; width: 90%; max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;
            transform: translateY(-20px) scale(0.95); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-family: 'Poppins', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 0; text-align: center;
        }
        #fmb-engine-otp-modal.otp-modal-visible .otp-modal-container { transform: translateY(0) scale(1); }
        .otp-modal-header {
            background: linear-gradient(135deg, #197278 0%, #145b60 100%);
            padding: 15px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .otp-modal-close {
            background: transparent !important;
            border: 1px solid rgba(255,255,255,0.4) !important;
            border-radius: 4px !important;
            color: #ffffff !important;
            cursor: pointer !important;
            padding: 4px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
            min-height: 0 !important;
            min-width: 0 !important;
            height: auto !important;
            width: auto !important;
            margin: 0 !important;
            line-height: 1 !important;
        }
        .otp-modal-close:hover {
            background: rgba(255,255,255,0.1);
            border-color: #ffffff;
        }
        .otp-modal-close svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }
        .otp-modal-header-title {
            margin: 0;
            font-size: 19px;
            font-weight: 500;
            color: #ffffff;
            line-height: 1;
            font-family: 'Poppins', sans-serif;
        }
        .otp-modal-body {
            padding: 30px 30px 40px 30px;
        }
        .otp-modal-icon { display: flex; justify-content: center; margin-bottom: 20px; }
        .otp-modal-icon svg { width: 48px; height: 48px; color: #197278; }
        .otp-modal-title { font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 8px; margin-top: 0; font-family: 'Hind Siliguri', sans-serif; }
        .otp-modal-desc { font-size: 15px; color: #6b7280; margin-bottom: 20px; }
        .otp-input-group { display: flex; gap: 10px; justify-content: center; margin-bottom: 24px; direction: ltr; }
        .otp-input-group input {
            width: 45px; height: 50px; font-size: 24px; text-align: center; font-weight: 600;
            border: 2px solid #e5e7eb; border-radius: 8px; outline: none; transition: border-color 0.2s;
            -moz-appearance: textfield;
        }
        .otp-input-group input::-webkit-outer-spin-button, .otp-input-group input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .otp-input-group input:focus { border-color: #197278; }
        .otp-btn {
            background: #197278; color: #ffffff !important; border: none; width: 100%; padding: 14px;
            border-radius: 4px; font-weight: 600; font-size: 16px; cursor: pointer; transition: background 0.2s, color 0.2s;
        }
        .otp-btn:hover { background: #145b60; color: #ffffff !important; }
        .otp-btn:disabled { background: #9ca3af; cursor: not-allowed; }
        #otp-error-msg { color: #ef4444; font-size: 13px; margin-top: -15px; margin-bottom: 15px; display: none; }
        </style>

        <div id="fmb-engine-otp-modal">
            <div class="otp-modal-container">
                <div class="otp-modal-header">
                    <h2 class="otp-modal-header-title" style="font-family: 'Poppins', sans-serif;">FMB Engine - OTP Verification</h2>
                    <button class="otp-modal-close" id="otp-modal-close-btn" aria-label="Close" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="otp-modal-body">
                    <!-- Step 1 Container -->
                    <div id="otp-step-1-container">
                        <div class="otp-modal-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h3 class="otp-modal-title">অর্ডার ভেরিফিকেশন</h3>
                        <p class="otp-modal-desc" style="line-height: 1.5;">আপনার অর্ডারটি সফলভাবে সম্পন্ন করতে মোবাইল নম্বরটি ভেরিফাই করা প্রয়োজন। নিচের বাটনে ক্লিক করলে আপনার <strong id="otp-target-phone-step1"></strong> নম্বরে একটি ৪-ডিজিটের ওটিপি কোড পাঠানো হবে।</p>
                        <button type="button" class="otp-btn" id="trigger-send-otp-btn">ওটিপি কোড পাঠান</button>
                        <button type="button" id="cancel-otp-btn" style="background: transparent; color: #6b7280; border: none; width: 100%; padding: 10px; font-weight: 500; font-size: 14px; cursor: pointer; margin-top: 10px; transition: color 0.2s;">অর্ডার বাতিল করুন</button>
                    </div>

                    <!-- Step 2 Container -->
                    <div id="otp-step-2-container" style="display: none;">
                        <h3 class="otp-modal-title">আপনার মোবাইল নম্বরটি ভেরিফাই করুন</h3>
                        <p class="otp-modal-desc">আমরা আপনার <strong id="otp-target-phone"></strong> নম্বরে একটি ৪-ডিজিটের কোড পাঠিয়েছি। অর্ডারটি সফলভাবে সম্পন্ন করতে নিচে কোডটি লিখুন।</p>
                        
                        <div id="otp-countdown-container" style="margin-bottom: 15px; margin-top: -10px; font-size: 14px; font-weight: 500; color: #6b7280;">
                            <?php $countdown_minutes = (int) get_option( 'ofls_sms_otp_countdown', 5 ); ?>
                            কোডের মেয়াদ বাকি আছে: <span id="otp-countdown-timer" style="color: #f04c4b; font-weight: 600;"><?php echo str_pad($countdown_minutes, 2, '0', STR_PAD_LEFT); ?>:00</span>
                        </div>
                        
                        <div class="otp-input-group">
                            <input type="number" maxlength="1" class="otp-digit" autofocus>
                            <input type="number" maxlength="1" class="otp-digit">
                            <input type="number" maxlength="1" class="otp-digit">
                            <input type="number" maxlength="1" class="otp-digit">
                        </div>
                        
                        <div id="otp-error-msg"></div>
                        <button type="button" class="otp-btn" id="verify-otp-btn">ওটিপি ভেরিফাই করে অর্ডার সম্পন্ন করুন</button>
                        <button type="button" class="otp-resend-btn" id="resend-otp-btn" style="display: none; background: transparent; color: #197278; border: 1px solid #197278; width: 100%; padding: 14px; border-radius: 4px; font-weight: 600; font-size: 16px; cursor: pointer; margin-top: 10px; transition: background 0.2s, color 0.2s;">পুনরায় ওটিপি পাঠান</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document.body).on('checkout_error', function() {
            var marker = jQuery('.woocommerce-error .ads-otp-marker, .woocommerce-NoticeGroup-checkout .ads-otp-marker');
            if (marker.length > 0) {
                var phone = marker.attr('data-phone') || '';
                
                // Hide only our specific error line if there are other errors
                var parentLi = marker.closest('li');
                if (parentLi.length) {
                    var parentUl = parentLi.parent();
                    parentLi.hide();
                    if (parentUl.children('li:visible').length === 0) {
                        parentUl.closest('.woocommerce-NoticeGroup, .woocommerce-error').hide();
                    }
                } else {
                    marker.closest('.woocommerce-NoticeGroup, .woocommerce-error').hide();
                }
                
                jQuery('#otp-target-phone').text(phone);
                jQuery('#otp-target-phone-step1').text(phone);
                
                // Show modal with animation
                var modal = document.getElementById('fmb-engine-otp-modal');
                modal.style.display = 'flex';
                void modal.offsetWidth; // Trigger reflow
                modal.classList.add('otp-modal-visible');
                
                // Reset UI to Step 1
                jQuery('#otp-step-1-container').show();
                jQuery('#otp-step-2-container').hide();
                jQuery('#trigger-send-otp-btn').prop('disabled', false).text('ওটিপি কোড পাঠান');
                
                // Reset UI for Step 2
                jQuery('#verify-otp-btn').prop('disabled', false).text('ওটিপি ভেরিফাই করে অর্ডার সম্পন্ন করুন').show();
                jQuery('#resend-otp-btn').prop('disabled', false).text('পুনরায় ওটিপি পাঠান').hide();
                jQuery('#otp-error-msg').hide();
                jQuery('.otp-digit').val('');
                
                if (window.otpCountdownInterval) {
                    clearInterval(window.otpCountdownInterval);
                }
            }
        });

            // Handle "Send OTP" button click
            jQuery(document.body).on('click', '#trigger-send-otp-btn', function() {
                var btn = jQuery(this);
                btn.prop('disabled', true).text('পাঠানো হচ্ছে...');
                var phone = jQuery('#otp-target-phone-step1').text();
                
                jQuery.ajax({
                    url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                    type: 'POST',
                    data: {
                        action: 'orderflow_send_otp',
                        phone: phone
                    },
                    success: function(response) {
                        if (response.success) {
                            jQuery('#otp-step-1-container').hide();
                            jQuery('#otp-step-2-container').fadeIn();
                            startOtpCountdown();
                            jQuery('.otp-digit').first().focus();
                        } else {
                            btn.prop('disabled', false).text('ওটিপি কোড পাঠান');
                            alert(response.data.message || 'Error sending OTP');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('ওটিপি কোড পাঠান');
                        alert('Server error, please try again.');
                    }
                });
            });

            function startOtpCountdown() {
                var timerElement = document.getElementById('otp-countdown-timer');
                var countdownMinutes = <?php echo (int) get_option( 'ofls_sms_otp_countdown', 5 ); ?>;
                var duration = countdownMinutes * 60;
                var initialMinutes = ("0" + countdownMinutes).slice(-2);
                timerElement.textContent = initialMinutes + ":00";
                
                if (window.otpCountdownInterval) {
                    clearInterval(window.otpCountdownInterval);
                }
                
                window.otpCountdownInterval = setInterval(function() {
                    duration--;
                    var minutes = Math.floor(duration / 60);
                    var seconds = duration % 60;
                    timerElement.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                    
                    if (duration <= 0) {
                        clearInterval(window.otpCountdownInterval);
                        timerElement.textContent = "00:00 (মেয়াদ শেষ)";
                        jQuery('#verify-otp-btn').hide();
                        jQuery('#resend-otp-btn').show();
                    }
                }, 1000);
            }

            // Close button logic
            document.getElementById('otp-modal-close-btn').addEventListener('click', function() {
                var modal = document.getElementById('fmb-engine-otp-modal');
                modal.classList.remove('otp-modal-visible');
                setTimeout(function() {
                    modal.style.display = 'none';
                }, 300);
            });
            
            // Cancel button logic
            document.getElementById('cancel-otp-btn').addEventListener('click', function() {
                var modal = document.getElementById('fmb-engine-otp-modal');
                modal.classList.remove('otp-modal-visible');
                setTimeout(function() {
                    modal.style.display = 'none';
                }, 300);
            });

            // Resend button logic
            document.getElementById('resend-otp-btn').onclick = function() {
                var btn = jQuery(this);
                btn.prop('disabled', true).text('পাঠানো হচ্ছে...');
                var phone = jQuery('#otp-target-phone').text();
                
                jQuery.ajax({
                    url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                    type: 'POST',
                    data: {
                        action: 'orderflow_send_otp',
                        phone: phone
                    },
                    success: function(response) {
                        if (response.success) {
                            jQuery('#otp-error-msg').hide();
                            jQuery('.otp-digit').val('');
                            jQuery('#resend-otp-btn').hide();
                            jQuery('#verify-otp-btn').prop('disabled', false).text('ওটিপি ভেরিফাই করে অর্ডার সম্পন্ন করুন').show();
                            startOtpCountdown();
                            jQuery('.otp-digit').first().focus();
                        } else {
                            btn.prop('disabled', false).text('পুনরায় ওটিপি পাঠান');
                            jQuery('#otp-error-msg').text(response.data.message || 'Error sending OTP').show();
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('পুনরায় ওটিপি পাঠান');
                        jQuery('#otp-error-msg').text('Server error, please try again.').show();
                    }
                });
            };

                // Set up OTP input logic
                const inputs = document.querySelectorAll('.otp-digit');
                inputs.forEach((input, index) => {
                    input.addEventListener('input', (e) => {
                        if (e.target.value.length > 1) {
                            e.target.value = e.target.value.slice(0, 1);
                        }
                        if (e.target.value.length === 1 && index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    });
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && !e.target.value && index > 0) {
                            inputs[index - 1].focus();
                        }
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            jQuery('#verify-otp-btn').click();
                        }
                    });
                });

        jQuery('#verify-otp-btn').on('click', function() {
            var $btn = jQuery(this);
            var phone = jQuery('#otp-target-phone').text();
            var inputs = document.querySelectorAll('.otp-digit');
            var otp = '';
            inputs.forEach(input => otp += input.value);

            if (otp.length !== 4) {
                jQuery('#otp-error-msg').text('দয়া করে ৪-ডিজিটের কোডটি লিখুন।').show();
                return;
            }

            $btn.prop('disabled', true).text('ভেরিফাই করা হচ্ছে...');
            jQuery('#otp-error-msg').hide();

            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'orderflow_verify_otp',
                    phone: phone,
                    otp: otp
                },
                success: function(response) {
                    if (response.success) {
                        // Modal close and submit checkout
                        document.getElementById('fmb-engine-otp-modal').classList.remove('otp-modal-visible');
                        setTimeout(function(){ 
                            document.getElementById('fmb-engine-otp-modal').style.display='none'; 
                            jQuery('form.checkout').submit(); // Resubmit checkout
                        }, 300);
                    } else {
                        jQuery('#otp-error-msg').text(response.data.message).show();
                        $btn.prop('disabled', false).text('ওটিপি ভেরিফাই করে অর্ডার সম্পন্ন করুন');
                    }
                },
                error: function() {
                    jQuery('#otp-error-msg').text('নেটওয়ার্ক সমস্যা। দয়া করে আবার চেষ্টা করুন।').show();
                    $btn.prop('disabled', false).text('ওটিপি ভেরিফাই করে অর্ডার সম্পন্ন করুন');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Send Order Placed SMS
     */
    public function send_order_placed_sms( $order_id, $posted_data, $order ) {
        if ( ! get_option( 'ofls_sms_notifications_enabled', true ) ) {
            return;
        }

        $template = get_option( 'ofls_sms_placed_template', '' );
        if ( empty( trim( $template ) ) ) {
            return;
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        $message = str_replace( 
            [ '{name}', '{order_id}', '{total}' ], 
            [ $order->get_billing_first_name(), $order->get_order_number(), $order->get_total() . ' টাকা' ], 
            $template 
        );

        $this->send_sms( $phone, $message );
    }

    /**
     * Clear the temporary 2-hour OTP session after a successful checkout
     * This ensures the global Cooldown Timing strictly dictates future OTP bypasses
     */
    public function clear_otp_session_after_checkout( $order_id, $posted_data, $order ) {
        $phone = $order->get_billing_phone();
        if ( ! empty( $phone ) ) {
            $ip = \WC_Geolocation::get_ip_address();
            $session_key = 'ofls_otp_verified_' . md5( $phone . $ip );
            delete_transient( $session_key );
        }
    }

    /**
     * Handle Order Status Changed SMS for all configured statuses
     */
    public function handle_order_status_changed_sms( $order_id, $old_status, $new_status, $order ) {
        if ( ! get_option( 'ofls_sms_notifications_enabled', true ) ) {
            return;
        }

        $template = '';

        switch ( $new_status ) {
            case 'processing':
                $template = get_option( 'ofls_sms_processing_template', '' );
                break;
            case 'completed':
            case 'confirmed':
            case 'wc-confirmed':
            case 'ads-confirmed':
                $template = get_option( 'ofls_sms_confirmed_template', '' );
                break;
            case 'cancelled':
                $template = get_option( 'ofls_sms_cancelled_template', '' );
                break;
            case 'shipping':
            case 'wc-shipping':
            case 'ads-shipping':
                $template = get_option( 'ofls_sms_shipping_template', '' );
                break;
            case 'shipped':
            case 'dispatched':
            case 'ads-delivered':
                $template = get_option( 'ofls_sms_shipped_template', '' );
                break;
        }

        if ( empty( trim( $template ) ) ) {
            return;
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        // Try to get tracking code from Steadfast or Pathao
        $tracking_code = $order->get_meta( '_steadfast_consignment_id' );
        if ( empty( $tracking_code ) ) {
            $tracking_code = $order->get_meta( '_pathao_consignment_id' );
        }
        if ( empty( $tracking_code ) ) {
            $tracking_code = 'N/A';
        }

        $message = str_replace( 
            [ '{name}', '{order_id}', '{total}', '{tracking_code}' ], 
            [ 
                $order->get_billing_first_name(), 
                $order->get_order_number(), 
                $order->get_total() . ' টাকা',
                $tracking_code
            ], 
            $template 
        );

        $this->send_sms( $phone, $message );
    }

}
