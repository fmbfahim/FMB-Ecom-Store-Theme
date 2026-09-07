<?php
namespace fmb_engine\TikTok;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFLS_TikTok_Server_Handler {

    private static $instance = null;
    private $enabled = false;
    private $pixel_configs = array();
    private $immediate_purchase = false;
    private $courier_threshold = 0;
    private $api_url = 'https://business-api.tiktok.com/open_api/v1.3/pixel/track/';

    private static $event_ids = array();

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_event_id( $event_name ) {
        if ( ! isset( self::$event_ids[ $event_name ] ) ) {
            // Generate a unique ID for the request lifetime for this event type
            self::$event_ids[ $event_name ] = strtolower( $event_name ) . '_' . time() . '_' . wp_rand( 10000, 99999 );
        }
        return self::$event_ids[ $event_name ];
    }

    public function __construct() {
        $this->enabled = get_option('ofls_tiktok_enabled', false);
        $this->immediate_purchase = get_option('ofls_tiktok_immediate_purchase', false);
        $this->courier_threshold = get_option('ofls_tiktok_courier_threshold', 0); // TikTok specific threshold

        $use_server_api = get_option('ofls_tiktok_use_server_api', false);

        $pixel_ids = get_option('ofls_tiktok_pixel_ids', array());
        $access_tokens = get_option('ofls_tiktok_access_tokens', array());
        $test_event_codes = get_option('ofls_tiktok_test_event_codes', array());

        if ( ! is_array( $pixel_ids ) ) {
            $pixel_ids = array();
        }
        if ( ! is_array( $access_tokens ) ) {
            $access_tokens = array();
        }
        if ( ! is_array( $test_event_codes ) ) {
            $test_event_codes = array();
        }

        // Fallback for single pixel ID
        if ( empty( $pixel_ids ) ) {
            $single_pixel = get_option('ofls_tiktok_pixel_id', '');
            if ( ! empty( $single_pixel ) ) {
                $pixel_ids = array( $single_pixel );
                $single_token = get_option('ofls_tiktok_access_token', '');
                $access_tokens = array( $single_token );
                $single_test = get_option('ofls_tiktok_test_event_code', '');
                $test_event_codes = array( $single_test );
            }
        }

        $this->pixel_configs = array();
        foreach ( $pixel_ids as $index => $pixel_id ) {
            $pixel_id = trim( $pixel_id );
            if ( empty( $pixel_id ) ) {
                continue;
            }
            $token = isset( $access_tokens[ $index ] ) ? trim( $access_tokens[ $index ] ) : '';
            $test_code = isset( $test_event_codes[ $index ] ) ? trim( $test_event_codes[ $index ] ) : '';
            
            $this->pixel_configs[] = array(
                'pixel_id' => $pixel_id,
                'access_token' => $token,
                'test_event_code' => $test_code
            );
        }

        if ( ! $this->enabled || ! $use_server_api || empty( $this->pixel_configs ) ) {
            return;
        }

        // Purchase Hooks
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order_checkout' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'process_order_checkout' ), 10, 1 );
        add_action( 'woocommerce_order_status_ads-purchase', array( $this, 'fire_held_events' ), 10, 1 ); // Handled centrally by PurchaseEventManager
        // add_action( 'woocommerce_order_status_processing', array( $this, 'fire_held_events' ), 10, 1 ); // Removed: handled centrally
        // add_action( 'woocommerce_order_status_completed', array( $this, 'fire_held_events' ), 10, 1 ); // Removed: handled centrally

        // Frontend Events Hooks
        // Frontend Events Hooks (AJAX)
        add_action( 'wp_ajax_orderflow_tiktok_server_event', array( $this, 'process_ajax_event' ) );
        add_action( 'wp_ajax_nopriv_orderflow_tiktok_server_event', array( $this, 'process_ajax_event' ) );
    }

    public function process_ajax_event() {
        if ( ! isset( $_POST['event_name'] ) || ! isset( $_POST['event_id'] ) ) {
            wp_die();
        }

        $event_name = sanitize_text_field( wp_unslash( $_POST['event_name'] ) );
        $event_id = sanitize_text_field( wp_unslash( $_POST['event_id'] ) );
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        // Add user data
        $contents = isset( $_POST['contents'] ) ? wp_unslash( $_POST['contents'] ) : array();
        $value = isset( $_POST['value'] ) ? (float) wp_unslash( $_POST['value'] ) : 0;

        $payload = $this->build_frontend_payload( $event_name, $contents, $value, $event_id, $url );
        $api_response = $this->send_to_tiktok_api( $payload, false ); // ASYNCHRONOUS NON-BLOCKING

        wp_send_json_success( $api_response );
    }

    private function build_frontend_payload( $event_name, $contents = array(), $value = 0, $event_id = '', $url = '' ) {
        $user_data = array();
        $page_data = array();

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( ! empty( $ip_address ) ) {
            $user_data['ip'] = filter_var( $ip_address, FILTER_VALIDATE_IP );
        }
        if ( ! empty( $user_agent ) ) {
            $user_data['user_agent'] = $user_agent;
        }

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( ! empty( $current_user->user_email ) ) {
                $user_data['email'] = hash( 'sha256', strtolower( trim( $current_user->user_email ) ) );
                $user_data['external_id'] = hash( 'sha256', strtolower( trim( $current_user->user_email ) ) );
            }
            if ( ! empty( $current_user->billing_phone ) ) {
                $clean_phone = preg_replace( '/[^0-9]/', '', $current_user->billing_phone );
                if ( ! empty( $clean_phone ) ) {
                    $user_data['phone_number'] = hash( 'sha256', $clean_phone );
                }
            }
        }

        if ( isset( $_COOKIE['ttclid'] ) ) {
            $user_data['ttclid'] = sanitize_text_field( wp_unslash( $_COOKIE['ttclid'] ) );
        } elseif ( isset( $_POST['ttclid'] ) && ! empty( $_POST['ttclid'] ) ) {
            $user_data['ttclid'] = sanitize_text_field( wp_unslash( $_POST['ttclid'] ) );
        }

        if ( isset( $_COOKIE['_ttp'] ) ) {
            $user_data['ttp'] = sanitize_text_field( wp_unslash( $_COOKIE['_ttp'] ) );
        } elseif ( isset( $_POST['ttp'] ) && ! empty( $_POST['ttp'] ) ) {
            $user_data['ttp'] = sanitize_text_field( wp_unslash( $_POST['ttp'] ) );
        }

        $properties = array();
        if ( ! empty( $contents ) && is_array( $contents ) ) {
            foreach ( $contents as &$item ) {
                if ( isset( $item['quantity'] ) ) {
                    $item['quantity'] = (int) $item['quantity'];
                }
                if ( isset( $item['price'] ) ) {
                    $item['price'] = (float) $item['price'];
                }
            }
            $properties['contents'] = $contents;
        }
        if ( $value > 0 ) {
            $properties['value'] = (float) $value;
            $properties['currency'] = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        }

        if ( empty( $event_id ) ) {
            $event_id = self::get_event_id( $event_name );
        }
        
        if ( empty( $url ) ) {
            $url = home_url( $_SERVER['REQUEST_URI'] ?? '' );
        }

        $page_data['url'] = $url;

        // Extract IP and UA from user_data to put them at context level for pixel/track API
        $ip = isset($user_data['ip']) ? $user_data['ip'] : '';
        $user_agent = isset($user_data['user_agent']) ? $user_data['user_agent'] : '';
        unset($user_data['ip']);
        unset($user_data['user_agent']);

        $context = array(
            'page' => $page_data
        );

        if ( ! empty( $ip ) ) {
            $context['ip'] = $ip;
        }
        if ( ! empty( $user_agent ) ) {
            $context['user_agent'] = $user_agent;
        }
        if ( ! empty( $user_data ) ) {
            $context['user'] = $user_data;
        }
        
        // Add ad tracking data if ttclid exists
        if ( isset( $user_data['ttclid'] ) ) {
            $context['ad'] = array( 'callback' => $user_data['ttclid'] );
        }

        $payload = array(
            'event' => $event_name,
            'event_id' => $event_id,
            'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'context' => $context
        );

        if ( ! empty( $properties ) ) {
            $payload['properties'] = $properties;
        }

        return $payload;
    }

    public function process_order_checkout( $order_id ) {
        if ( empty( $order_id ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent duplicate processing
        if ( $order->get_meta( '_ofls_tiktok_server_processed' ) === 'yes' ) {
            return;
        }

        $payload = $this->build_event_payload( $order );

        $should_send_immediate = $this->immediate_purchase;

        // Apply advanced courier ratio logic
        if ( $should_send_immediate && $this->courier_threshold > 0 ) {
            $phone = $order->get_billing_phone();
            if ( ! empty( $phone ) ) {
                $courier_checker = new class() {
                    use \fmb_engine\Traits\Courier;
                };
                $courier_ratio = $courier_checker->get_courier_rate_by_phone( $phone );
                if ( $courier_ratio < $this->courier_threshold ) {
                    $should_send_immediate = false;
                }
            }
        }

        if ( $should_send_immediate ) {
            $this->send_to_tiktok_api( $payload, true, $order_id ); // Purchase is blocking for reliability
        } else {
            // Save for manual firing later
            $order->update_meta_data( '_ofls_tiktok_held_purchase_event', $payload );
        }

        $order->update_meta_data( '_ofls_tiktok_server_processed', 'yes' );
        $order->save();
    }

    public function fire_held_events( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $payload = $order->get_meta( '_ofls_tiktok_held_purchase_event' );
        if ( ! empty( $payload ) ) {
            $this->send_to_tiktok_api( $payload, true, $order_id );
            // Remove the meta so it isn't fired again
            $order->delete_meta_data( '_ofls_tiktok_held_purchase_event' );
            $order->save();
        }
    }

    private function build_event_payload( $order ) {
        $contents = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $contents[] = array(
                'content_id' => (string) ($product ? $product->get_id() : $item->get_product_id()),
                'content_type' => 'product',
                'content_name' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'price' => (float) $order->get_item_total( $item, true, true )
            );
        }

        $user_data = array();
        $email = strtolower( trim( $order->get_billing_email() ) );
        if ( ! empty( $email ) ) {
            $user_data['external_id'] = hash( 'sha256', $email );
            $user_data['email'] = hash( 'sha256', $email );
        }

        $phone = $order->get_billing_phone();
        if ( ! empty( $phone ) ) {
            $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
            if ( ! empty( $clean_phone ) ) {
                $user_data['phone_number'] = hash( 'sha256', $clean_phone );
            }
        }

        $first_name = strtolower( trim( $order->get_billing_first_name() ) );
        if ( ! empty( $first_name ) ) {
            $user_data['fn'] = hash( 'sha256', $first_name );
        }

        $last_name = strtolower( trim( $order->get_billing_last_name() ) );
        if ( ! empty( $last_name ) ) {
            $user_data['ln'] = hash( 'sha256', $last_name );
        }

        $city = strtolower( trim( $order->get_billing_city() ) );
        if ( ! empty( $city ) ) {
            $user_data['ct'] = hash( 'sha256', $city );
        }

        $state = strtolower( trim( $order->get_billing_state() ) );
        if ( ! empty( $state ) ) {
            $user_data['st'] = hash( 'sha256', $state );
        }

        $postcode = strtolower( trim( $order->get_billing_postcode() ) );
        if ( ! empty( $postcode ) ) {
            $user_data['zp'] = hash( 'sha256', $postcode );
        }

        $country = strtolower( trim( $order->get_billing_country() ) );
        if ( ! empty( $country ) ) {
            $user_data['country'] = hash( 'sha256', $country );
        }

        $ip_address = $order->get_customer_ip_address();
        $user_agent = $order->get_customer_user_agent();
        
        if ( ! empty( $ip_address ) ) {
            $user_data['ip'] = filter_var( $ip_address, FILTER_VALIDATE_IP );
        }
        if ( ! empty( $user_agent ) ) {
            $user_data['user_agent'] = $user_agent;
        }

        if ( isset( $_COOKIE['ttclid'] ) ) {
            $user_data['ttclid'] = sanitize_text_field( wp_unslash( $_COOKIE['ttclid'] ) );
        }
        if ( isset( $_COOKIE['_ttp'] ) ) {
            $user_data['ttp'] = sanitize_text_field( wp_unslash( $_COOKIE['_ttp'] ) );
        }

        $event_id = 'purchase_' . $order->get_id(); // Must match browser handler
        
        // Extract IP and UA from user_data to put them at context level for pixel/track API
        $ip = isset($user_data['ip']) ? $user_data['ip'] : '';
        $user_agent = isset($user_data['user_agent']) ? $user_data['user_agent'] : '';
        unset($user_data['ip']);
        unset($user_data['user_agent']);

        $context = array(
            'page' => array(
                'url' => home_url( $_SERVER['REQUEST_URI'] ?? '' )
            )
        );

        if ( ! empty( $ip ) ) {
            $context['ip'] = $ip;
        }
        if ( ! empty( $user_agent ) ) {
            $context['user_agent'] = $user_agent;
        }
        if ( ! empty( $user_data ) ) {
            $context['user'] = $user_data;
        }
        
        // Add ad tracking data if ttclid exists
        if ( isset( $user_data['ttclid'] ) ) {
            $context['ad'] = array( 'callback' => $user_data['ttclid'] );
        }

        $payload = array(
            'event' => 'CompletePayment',
            'event_id' => $event_id,
            'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'context' => $context,
            'properties' => array(
                'contents' => $contents,
                'value' => (float) $order->get_total(),
                'currency' => $order->get_currency()
            )
        );

        return $payload;
    }

    private function send_to_tiktok_api( $event_payload, $is_blocking = true, $order_id = null ) {
        if ( empty( $this->pixel_configs ) ) {
            return;
        }

        foreach ( $this->pixel_configs as $config ) {
            $pixel_id = $config['pixel_id'];
            $access_token = $config['access_token'];
            $test_event_code = $config['test_event_code'];

            if ( empty( $access_token ) ) {
                continue; // Skip if no CAPI access token is configured for this pixel
            }

            // TikTok CAPI কোনো 'data' অ্যারে ব্যবহার করে না, সবকিছু রুট লেভেলে থাকে
            $body = $event_payload;
            $body['pixel_code'] = $pixel_id;

            if ( ! empty( $test_event_code ) ) {
                $body['test_event_code'] = $test_event_code;
            }

            $args = array(
                'body' => wp_json_encode( $body ),
                'headers' => array(
                    'Access-Token' => $access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => $is_blocking ? 15 : 2,
                'blocking' => $is_blocking
            );

            $response = wp_remote_post( $this->api_url, $args );

            $response_body = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
            
            // Auto update order UI status on success
            if ( ! is_wp_error( $response ) && $order_id ) {
                $status_code = wp_remote_retrieve_response_code( $response );
                $body_data = json_decode( $response_body, true );

                if ( $status_code === 200 && isset( $body_data['code'] ) && $body_data['code'] === 0 ) {
                    update_post_meta( $order_id, '_tiktok_capi_status', 'success' );
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        if ( \fmb_engine\Facebook\isWooCommerceVersionGte( '3.0.0' ) ) {
                            $order->update_meta_data( '_tiktok_capi_status', 'success' );
                            $order->save();
                        }
                    }
                }
            }

            // Debug Logging
            if ( get_option('ofls_tiktok_debug_mode', true) ) {
                $log_data = array(
                    'time'     => current_time('mysql'),
                    'endpoint' => $this->api_url,
                    'payload'  => $body,
                    'response' => $response_body
                );
                error_log( 'TikTok CAPI Debug: ' . wp_json_encode( $log_data ) );
            }
            
            return $response_body;
        }
    }
}
