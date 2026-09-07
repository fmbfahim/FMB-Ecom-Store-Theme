<?php
namespace fmb_engine\Google;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFLS_Google_Server_Handler {

    private static $instance = null;
    private $google_enabled = false;
    private $immediate_purchase = false;
    private $courier_threshold = 0;

    private $ga_enabled = false;
    private $ga_measurement_id = '';
    private $ga_api_secret = '';

    private $api_url = 'https://www.google-analytics.com/mp/collect';

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->google_enabled = get_option('ofls_google_enabled', false);
        $this->immediate_purchase = get_option('ofls_google_immediate_purchase', false);
        $this->courier_threshold = get_option('ofls_google_courier_threshold', 0);

        $this->ga_enabled = get_option('ofls_ga_enabled', false);
        $this->ga_measurement_id = get_option('ofls_ga_measurement_id', '');
        $this->ga_api_secret = get_option('ofls_ga_api_secret', '');

        // Save Click ID (GCLID / _gcl_aw) during checkout or initialization
        add_action( 'init', array( $this, 'capture_click_id' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_click_id_to_order' ), 10, 1 );

        if ( ! $this->ga_enabled || empty( $this->ga_measurement_id ) || empty( $this->ga_api_secret ) ) {
            return;
        }

        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order_checkout' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'process_order_checkout' ), 10, 1 );
        // add_action( 'woocommerce_order_status_ads-purchase', array( $this, 'fire_held_events' ), 10, 1 ); // Handled centrally by PurchaseEventManager
        // add_action( 'woocommerce_order_status_processing', array( $this, 'fire_held_events' ), 10, 1 ); // Removed: handled centrally
    }

    /**
     * Capture GCLID/Click ID and store in session cookie if present in URL query
     */
    public function capture_click_id() {
        if ( isset( $_GET['gclid'] ) ) {
            setcookie( 'ofls_gclid', sanitize_text_field( $_GET['gclid'] ), time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
        }
    }

    /**
     * Save Captured GCLID to Order Meta on checkout
     */
    public function save_click_id_to_order( $order_id ) {
        $gclid = '';
        if ( isset( $_GET['gclid'] ) ) {
            $gclid = sanitize_text_field( $_GET['gclid'] );
        } elseif ( isset( $_COOKIE['ofls_gclid'] ) ) {
            $gclid = sanitize_text_field( $_COOKIE['ofls_gclid'] );
        } elseif ( isset( $_COOKIE['_gcl_aw'] ) ) {
            // _gcl_aw cookie format: GCL.timestamp.gclid_value
            $parts = explode( '.', $_COOKIE['_gcl_aw'] );
            if ( count( $parts ) >= 3 ) {
                $gclid = $parts[2];
            }
        }

        if ( ! empty( $gclid ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_ofls_gclid', $gclid );
                $order->save();
            }
        }
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
        if ( $order->get_meta( '_ofls_google_server_processed' ) === 'yes' ) {
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
            $this->send_to_google_api( $payload );
        } else {
            // Save for manual firing later
            $order->update_meta_data( '_ofls_google_held_purchase_event', $payload );
        }

        $order->update_meta_data( '_ofls_google_server_processed', 'yes' );
        $order->save();
    }

    public function fire_held_events( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $payload = $order->get_meta( '_ofls_google_held_purchase_event' );
        if ( ! empty( $payload ) ) {
            $this->send_to_google_api( $payload );
            // Remove the meta so it isn't fired again
            $order->delete_meta_data( '_ofls_google_held_purchase_event' );
            $order->save();
        }
    }

    private function get_ga_client_id() {
        if ( isset( $_COOKIE['_ga'] ) ) {
            $parts = explode( '.', $_COOKIE['_ga'] );
            if ( count( $parts ) >= 4 ) {
                return $parts[2] . '.' . $parts[3];
            }
        }
        // Fallback Client ID
        return sprintf( '%04x%04x.%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    private function build_event_payload( $order ) {
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = array(
                'item_id' => (string) ($product ? $product->get_id() : $item->get_product_id()),
                'item_name' => $item->get_name(),
                'price' => (float) $order->get_item_total( $item, true, true ),
                'quantity' => (int) $item->get_quantity()
            );
        }

        $client_id = $this->get_ga_client_id();

        // Safe client user properties
        $email = strtolower( trim( $order->get_billing_email() ) );
        $phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );
        
        $user_data = array();
        if ( ! empty( $email ) ) {
            $user_data['sha256_email_address'] = hash( 'sha256', $email );
        }
        if ( ! empty( $phone ) ) {
            $user_data['sha256_phone_number'] = hash( 'sha256', $phone );
        }

        $gclid = $order->get_meta( '_ofls_gclid' );

        $event_params = array(
            'transaction_id' => (string) $order->get_id(),
            'value' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'items' => $items
        );

        if ( ! empty( $gclid ) ) {
            $event_params['gclid'] = $gclid;
        }

        $payload = array(
            'client_id' => $client_id,
            'events' => array(
                array(
                    'name' => 'purchase',
                    'params' => $event_params
                )
            )
        );

        if ( ! empty( $user_data ) ) {
            $payload['user_data'] = $user_data;
        }

        return $payload;
    }

    private function send_to_google_api( $event_payload ) {
        $endpoint = add_query_arg(
            array(
                'measurement_id' => $this->ga_measurement_id,
                'api_secret'     => $this->ga_api_secret,
            ),
            $this->api_url
        );

        $args = array(
            'body'        => json_encode( $event_payload ),
            'headers'     => array(
                'Content-Type' => 'application/json'
            ),
            'timeout'     => 15,
            'blocking'    => true
        );

        wp_remote_post( $endpoint, $args );
    }
}
