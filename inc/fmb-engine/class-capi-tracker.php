<?php
/**
 * FMB CAPI Analytics & Multi-Platform Event Tracker
 * Supports Meta/Facebook CAPI, TikTok Server API & Google Analytics 4 / Ads GTAG.
 * Includes local Pixel Event Logging.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_CAPI_Tracker {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->create_log_table();

        add_action('wp_head', array($this, 'inject_browser_pixels'), 1);
        add_action('wp_head', array($this, 'inject_pixel_logger'), 999);

        add_action('woocommerce_thankyou', array($this, 'track_purchase_event'), 10, 1);

        add_action('wp_ajax_fmb_log_pixel_event', array($this, 'ajax_log_pixel_event'));
        add_action('wp_ajax_nopriv_fmb_log_pixel_event', array($this, 'ajax_log_pixel_event'));
    }

    /**
     * Create Pixel Logs DB Table
     */
    private function create_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_pixel_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            event_name varchar(255) NOT NULL,
            event_data text NOT NULL,
            url text NOT NULL,
            ip_address varchar(100) NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }
    }

    /**
     * AJAX Handler for Local Pixel Logging
     */
    public function ajax_log_pixel_event() {
        if (!isset($_POST['event_name'])) {
            wp_send_json_error();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_pixel_logs';
        
        $event_name = sanitize_text_field($_POST['event_name']);
        $event_data = isset($_POST['event_data']) ? wp_unslash($_POST['event_data']) : '{}';
        $url        = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $wpdb->insert(
            $table_name,
            array(
                'time'       => current_time('mysql'),
                'event_name' => $event_name,
                'event_data' => $event_data,
                'url'        => $url,
                'ip_address' => $ip
            )
        );

        // Keep last 500 logs to save DB space
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        if ($count > 500) {
            $wpdb->query("DELETE FROM {$table_name} ORDER BY id ASC LIMIT " . ($count - 500));
        }

        wp_send_json_success();
    }

    /**
     * Inject Client-Side Pixel Snippets for Facebook, TikTok & Google
     */
    public function inject_browser_pixels() {
        $fb_pixel_id = get_option('fmb_fb_pixel_id', '');
        $tt_pixel_id = get_option('fmb_tiktok_pixel_id', '');
        $ga_id       = get_option('fmb_ga4_measurement_id', '');

        if (!empty($fb_pixel_id)) {
            $user_match = array('country' => 'bd');
            if (is_user_logged_in()) {
                $u = wp_get_current_user();
                if (!empty($u->user_email)) $user_match['em'] = strtolower(trim($u->user_email));
                if (!empty($u->first_name)) $user_match['fn'] = strtolower(trim($u->first_name));
                if (!empty($u->last_name))  $user_match['ln'] = strtolower(trim($u->last_name));
                $u_phone = get_user_meta($u->ID, 'billing_phone', true);
                if (!empty($u_phone)) {
                    $u_phone = preg_replace('/[^\d]/', '', $u_phone);
                    if (strlen($u_phone) === 11 && strpos($u_phone, '01') === 0) $u_phone = '88' . $u_phone;
                    $user_match['ph'] = $u_phone;
                }
                $user_match['external_id'] = (string) $u->ID;
            } elseif (!empty($_COOKIE['_fmb_external_id'])) {
                $user_match['external_id'] = sanitize_text_field($_COOKIE['_fmb_external_id']);
            }
            ?>
            <!-- Meta Pixel Code -->
            <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo esc_js($fb_pixel_id); ?>', <?php echo wp_json_encode(array_filter($user_match)); ?>);
            fbq('track', 'PageView');
            </script>
            <?php
        }

        if (!empty($tt_pixel_id)) {
            ?>
            <!-- TikTok Pixel Code -->
            <script>
            !function (w, d, t) {
              w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
              ttq.load('<?php echo esc_js($tt_pixel_id); ?>');
              ttq.page();
            }(window, document, 'ttq');
            </script>
            <?php
        }

        if (!empty($ga_id)) {
            ?>
            <!-- Google Tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '<?php echo esc_js($ga_id); ?>');
            </script>
            <?php
        }
    }

    /**
     * Inject JS Interceptor to Log Pixel Events Locally
     */
    public function inject_pixel_logger() {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var fmbLoggedEvents = {};
            setInterval(function() {
                if (typeof window.fbq === 'function' && !window.fbq.isFmbLogged) {
                    var originalFbq = window.fbq;
                    window.fbq = function() {
                        var args = Array.prototype.slice.call(arguments);
                        originalFbq.apply(this, args);
                        if (args[0] === 'track' || args[0] === 'trackCustom') {
                            var eventName = args[1];
                            var eventData = args[2] || {};
                            var eventKey = eventName + JSON.stringify(eventData);
                            var now = Date.now();
                            if (fmbLoggedEvents[eventKey] && (now - fmbLoggedEvents[eventKey] < 2000)) return;
                            fmbLoggedEvents[eventKey] = now;

                            var formData = new FormData();
                            formData.append('action', 'fmb_log_pixel_event');
                            formData.append('event_name', eventName);
                            formData.append('event_data', JSON.stringify(eventData));
                            formData.append('url', window.location.href);

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData });
                        }
                    };
                    window.fbq.isFmbLogged = true;
                }
            }, 1000);
        });
        </script>
        <?php
    }

    /**
     * Send Server-Side Purchase Event to Facebook CAPI with Max Event Match Quality
     */
    public function track_purchase_event($order_id) {
        if (!$order_id) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        if (get_post_meta($order_id, '_fmb_capi_purchase_sent', true)) {
            return;
        }

        $access_token = get_option('fmb_fb_capi_access_token', '');
        $pixel_id     = get_option('fmb_fb_pixel_id', '');

        if (empty($access_token) || empty($pixel_id)) return;

        $event_id = 'purchase_' . $order_id;
        
        // Resolve IPv6 Priority
        $user_ip = function_exists('fmb_get_client_ip_address') ? fmb_get_client_ip_address(true) : '';
        if (empty($user_ip) && method_exists($order, 'get_customer_ip_address')) {
            $user_ip = $order->get_customer_ip_address();
        }
        if (empty($user_ip)) {
            $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        }

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ($order->get_customer_user_agent() ?: '');

        // Phone normalized to E.164 (8801XXXXXXXXX)
        $phone = preg_replace('/[^\d]/', '', (string)$order->get_billing_phone());
        if (strlen($phone) === 11 && strpos($phone, '01') === 0) {
            $phone = '88' . $phone;
        } elseif (strlen($phone) === 10 && strpos($phone, '1') === 0) {
            $phone = '880' . $phone;
        }

        // Email
        $email = strtolower(trim((string)$order->get_billing_email()));

        // Names (First Name & Last Name)
        $billing_fn = trim((string)$order->get_billing_first_name());
        $billing_ln = trim((string)$order->get_billing_last_name());
        if (empty($billing_ln) && !empty($billing_fn)) {
            $parts = array_filter(explode(' ', $billing_fn));
            $fn = array_shift($parts);
            $ln = !empty($parts) ? implode(' ', $parts) : $fn;
        } else {
            $fn = $billing_fn;
            $ln = $billing_ln ?: $billing_fn;
        }

        // Location parameters
        $city = strtolower(trim((string)($order->get_billing_city() ?: 'dhaka')));
        $state = strtolower(trim((string)($order->get_billing_state() ?: 'dhaka')));
        $postcode = strtolower(trim((string)($order->get_billing_postcode() ?: '1200')));
        $country = strtolower(trim((string)($order->get_billing_country() ?: 'bd')));

        // Meta Cookies & External ID
        $fbp = $order->get_meta('_fbp') ?: ($order->get_meta('_customer_fbp') ?: ($_COOKIE['_fbp'] ?? null));
        $fbc = $order->get_meta('_fbc') ?: ($order->get_meta('_customer_fbc') ?: ($_COOKIE['_fbc'] ?? null));
        $ext_id = $order->get_meta('external_id') ?: ($_COOKIE['_fmb_external_id'] ?? ($_COOKIE['orderflow_id'] ?? null));
        if (!$ext_id && !empty($phone)) {
            $ext_id = hash('sha256', 'fmb_' . $phone);
        }

        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'id'       => (string) $item->get_product_id(),
                'quantity' => (int) $item->get_quantity(),
                'item_price' => (float) $item->get_subtotal()
            );
        }

        $user_data = array(
            'client_ip_address' => $user_ip,
            'client_user_agent'=> $user_agent,
            'ph'                => !empty($phone) ? hash('sha256', $phone) : null,
            'fn'                => !empty($fn) ? hash('sha256', strtolower($fn)) : null,
            'ln'                => !empty($ln) ? hash('sha256', strtolower($ln)) : null,
            'country'           => hash('sha256', $country),
            'ct'                => hash('sha256', $city),
            'st'                => hash('sha256', $state),
            'zp'                => hash('sha256', $postcode)
        );

        if (!empty($email)) {
            $user_data['em'] = hash('sha256', $email);
        }
        if (!empty($fbp)) {
            $user_data['fbp'] = $fbp;
        }
        if (!empty($fbc)) {
            $user_data['fbc'] = $fbc;
        }
        if (!empty($ext_id)) {
            $user_data['external_id'] = $ext_id;
        }

        $payload = array(
            'data' => array(
                array(
                    'event_name'       => 'Purchase',
                    'event_time'       => time(),
                    'event_id'         => $event_id,
                    'event_source_url' => wc_get_endpoint_url('order-received', $order_id, wc_get_page_permalink('checkout')),
                    'action_source'    => 'website',
                    'user_data'        => array_filter($user_data),
                    'custom_data'      => array(
                        'currency'     => $order->get_currency(),
                        'value'        => (float) $order->get_total(),
                        'content_type' => 'product',
                        'contents'     => $items,
                        'order_id'     => (string) $order_id
                    )
                )
            )
        );

        $url = "https://graph.facebook.com/v19.0/{$pixel_id}/events?access_token={$access_token}";

        $res = wp_remote_post($url, array(
            'timeout'   => 10,
            'headers'   => array('Content-Type' => 'application/json'),
            'body'      => wp_json_encode($payload)
        ));

        if (!is_wp_error($res)) {
            update_post_meta($order_id, '_fmb_capi_purchase_sent', '1');
        }
    }
}
