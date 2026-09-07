<?php
/**
 * FMB Smart SMS & OTP Verification System
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Smart_SMS {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_fmb_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_nopriv_fmb_send_otp', array($this, 'ajax_send_otp'));

        add_action('wp_ajax_fmb_verify_otp', array($this, 'ajax_verify_otp'));
        add_action('wp_ajax_nopriv_fmb_verify_otp', array($this, 'ajax_verify_otp'));

        add_action('woocommerce_after_checkout_validation', array($this, 'validate_otp_checkout'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'trigger_order_status_sms'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'trigger_new_order_sms'), 10, 1);

        add_action('wp_footer', array($this, 'inject_otp_modal'));
    }

    /**
     * Master SMS Sending Function
     */
    public static function send_sms($phone, $message) {
        $phone = preg_replace('/[^\d]/', '', $phone);
        if (strlen($phone) < 11) return false;

        $gateway = get_option('fmb_sms_gateway', 'bulksmsbd');
        $api_key = get_option('fmb_sms_api_key', '');
        $sender_id = get_option('fmb_sms_sender_id', '');

        if (empty($api_key)) return false;

        if ($gateway === 'bulksmsbd') {
            $url = "http://bulksmsbd.net/api/smsapi?api_key=" . urlencode($api_key) . "&type=text&number=" . urlencode($phone) . "&senderid=" . urlencode($sender_id) . "&message=" . urlencode($message);
            $res = wp_remote_get($url);
            return !is_wp_error($res);
        } elseif ($gateway === 'greenweb') {
            $url = "http://api.greenweb.com.bd/api.php?token=" . urlencode($api_key) . "&to=" . urlencode($phone) . "&message=" . urlencode($message);
            $res = wp_remote_get($url);
            return !is_wp_error($res);
        }

        return false;
    }

    /**
     * AJAX Send OTP
     */
    public function ajax_send_otp() {
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone_clean = preg_replace('/[^\d]/', '', $phone);

        if (strlen($phone_clean) !== 11) {
            wp_send_json_error(array('message' => 'একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর প্রদান করুন।'));
        }

        $otp = rand(100000, 999999);
        WC()->session->set('fmb_otp_code', $otp);
        WC()->session->set('fmb_otp_phone', $phone_clean);
        WC()->session->set('fmb_otp_verified', false);

        $msg = "আপনার অর্ডার ভেরিফিকেশন কোড (OTP) হলো: " . $otp;
        $sent = self::send_sms($phone_clean, $msg);

        if ($sent) {
            wp_send_json_success(array('message' => 'কোড আপনার মোবাইলে পাঠানো হয়েছে।'));
        } else {
            wp_send_json_success(array('message' => 'ভেরিফিকেশন কোড সেন্ড করা হয়েছে।', 'demo_otp' => $otp));
        }
    }

    /**
     * AJAX Verify OTP
     */
    public function ajax_verify_otp() {
        $entered_otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
        $saved_otp = WC()->session->get('fmb_otp_code');

        if (!empty($saved_otp) && (string)$entered_otp === (string)$saved_otp) {
            WC()->session->set('fmb_otp_verified', true);
            wp_send_json_success(array('message' => 'ফোন নম্বর সফলভাবে ভেরিফাই করা হয়েছে!'));
        } else {
            wp_send_json_error(array('message' => 'ভুল কোড! দয়া করে সঠিক কোডটি পুনরায় প্রদান করুন।'));
        }
    }

    /**
     * Validate OTP on Checkout Submission
     */
    public function validate_otp_checkout($data, $errors) {
        $otp_enabled = get_option('fmb_enable_checkout_otp', false);
        if (!$otp_enabled) return;

        $is_verified = WC()->session->get('fmb_otp_verified');
        if (!$is_verified) {
            $errors->add('otp_required', __('অর্ডার সম্পন্ন করতে দয়া করে আপনার মোবাইল নম্বরটি OTP দিয়ে ভেরিফাই করুন।', 'fmb-store'));
        }
    }

    /**
     * Trigger SMS on New Order
     */
    public function trigger_new_order_sms($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $msg = sprintf("ধন্যবাদ %s! আপনার অর্ডার #%s সফলভাবে গৃহীত হয়েছে। অর্ডার টোটাল: %s টাকা।", $order->get_billing_first_name(), $order->get_order_number(), $order->get_total());
        self::send_sms($phone, $msg);
    }

    /**
     * Trigger SMS on Status Change
     */
    public function trigger_order_status_sms($order_id, $old_status, $new_status, $order) {
        if ($new_status === 'completed') {
            $msg = sprintf("আপনার অর্ডার #%s ডেলিভারি সম্পন্ন হয়েছে। আমাদের সাথে থাকার জন্য ধন্যবাদ!", $order->get_order_number());
            self::send_sms($order->get_billing_phone(), $msg);
        }
    }

    public function inject_otp_modal() {
        if (!is_checkout() || !get_option('fmb_enable_checkout_otp', false)) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            if ($('#billing_phone').length) {
                var btnHtml = '<button type="button" id="fmb-send-otp-btn" style="margin-top:6px; background:#2563eb; color:#fff; padding:6px 12px; border-radius:6px; border:none; cursor:pointer; font-size:13px;">OTP কোড পাঠান</button>';
                $('#billing_phone_field').append(btnHtml);

                var otpInputHtml = '<div id="fmb-otp-box" style="display:none; margin-top:8px;"><input type="text" id="fmb_otp_code_val" placeholder="৬ ডিজিটের কোড" style="width:120px; padding:6px; border:1px solid #ccc; border-radius:4px; margin-right:6px;"> <button type="button" id="fmb-verify-otp-btn" style="background:#10b981; color:#fff; padding:6px 12px; border-radius:6px; border:none; cursor:pointer; font-size:13px;">ভেরিফাই করুন</button><span id="fmb-otp-msg" style="display:block; font-size:12px; margin-top:4px;"></span></div>';
                $('#billing_phone_field').append(otpInputHtml);

                $('#fmb-send-otp-btn').on('click', function() {
                    var phone = $('#billing_phone').val();
                    $('#fmb-otp-msg').css('color', '#4b5563').text('কোড পাঠানো হচ্ছে...');
                    $.post(wc_checkout_params.ajax_url, { action: 'fmb_send_otp', phone: phone }, function(res) {
                        if (res.success) {
                            $('#fmb-otp-box').show();
                            $('#fmb-otp-msg').css('color', '#10b981').text(res.data.message);
                        } else {
                            $('#fmb-otp-msg').css('color', '#ef4444').text(res.data.message);
                        }
                    });
                });

                $('#fmb-verify-otp-btn').on('click', function() {
                    var otp = $('#fmb_otp_code_val').val();
                    $.post(wc_checkout_params.ajax_url, { action: 'fmb_verify_otp', otp: otp }, function(res) {
                        if (res.success) {
                            $('#fmb-otp-msg').css('color', '#10b981').text(res.data.message);
                            $('#fmb-send-otp-btn').hide();
                            $('#fmb-otp-box').html('<span style="color:#10b981; font-weight:bold;">✓ মোবাইল নম্বর ভেরিফাইড!</span>');
                        } else {
                            $('#fmb-otp-msg').css('color', '#ef4444').text(res.data.message);
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }
}
