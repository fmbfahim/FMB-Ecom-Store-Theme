<?php
namespace fmb_engine\Google;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFLS_Google_Browser_Handler {

    private static $instance = null;
    private $google_enabled = false;
    private $google_conversion_id = '';
    private $google_purchase_label = '';
    private $google_immediate_purchase = false;
    private $google_courier_threshold = 0;

    private $ga_enabled = false;
    private $ga_measurement_id = '';

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->google_enabled = get_option('ofls_google_enabled', false);
        $this->google_conversion_id = get_option('ofls_google_conversion_id', '');
        $this->google_purchase_label = get_option('ofls_google_purchase_label', '');
        $this->google_immediate_purchase = get_option('ofls_google_immediate_purchase', false);
        $this->google_courier_threshold = get_option('ofls_google_courier_threshold', 0);

        $this->ga_enabled = get_option('ofls_ga_enabled', false);
        $this->ga_measurement_id = get_option('ofls_ga_measurement_id', '');

        // If neither is enabled, do nothing
        if ( ( ! $this->google_enabled || empty( $this->google_conversion_id ) ) &&
             ( ! $this->ga_enabled || empty( $this->ga_measurement_id ) ) ) {
            return;
        }

        add_action( 'wp_head', array( $this, 'inject_base_gtag' ) );
        add_action( 'wp_footer', array( $this, 'inject_browser_events' ) );
    }

    public function inject_base_gtag() {
        ?>
        <!-- Global site tag (gtag.js) - Google Ads/Analytics Added by FMB Engine -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( !empty($this->google_conversion_id) ? $this->google_conversion_id : $this->ga_measurement_id ); ?>"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        <?php if ( $this->google_enabled && ! empty( $this->google_conversion_id ) ) : ?>
        gtag('config', '<?php echo esc_js( $this->google_conversion_id ); ?>', {
            'allow_enhanced_conversions': true
        });
        <?php endif; ?>

        <?php if ( $this->ga_enabled && ! empty( $this->ga_measurement_id ) ) : ?>
        gtag('config', '<?php echo esc_js( $this->ga_measurement_id ); ?>');
        <?php endif; ?>
        </script>
        <!-- End Google Tag -->
        <?php
    }

    public function inject_browser_events() {
        if ( is_product() ) {
            $this->track_view_item();
        } elseif ( is_checkout() && ! is_wc_endpoint_url() ) {
            $this->track_begin_checkout();
        } elseif ( is_wc_endpoint_url( 'order-received' ) ) {
            $this->track_purchase();
        }
    }

    private function track_view_item() {
        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_the_ID() );
        }
        if ( ! $product ) return;

        $categories = get_the_terms( $product->get_id(), 'product_cat' );
        $category_name = '';
        if ( $categories && ! is_wp_error( $categories ) ) {
            $category_name = $categories[0]->name;
        }

        $item = array(
            'item_id' => (string) $product->get_id(),
            'item_name' => $product->get_name(),
            'price' => (float) ($product->get_price() ? $product->get_price() : 0),
            'quantity' => 1
        );
        if ( ! empty( $category_name ) ) {
            $item['item_category'] = $category_name;
        }

        ?>
        <script>
        if (typeof gtag !== 'undefined') {
            gtag('event', 'view_item', {
                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>',
                value: <?php echo esc_js( $item['price'] ); ?>,
                items: [<?php echo json_encode( $item ); ?>]
            });
        }
        </script>
        <?php
    }

    private function track_begin_checkout() {
        if ( ! WC()->cart ) return;

        $items = array();
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $items[] = array(
                'item_id' => (string) $_product->get_id(),
                'item_name' => $_product->get_name(),
                'price' => (float) $_product->get_price(),
                'quantity' => (int) $cart_item['quantity']
            );
        }

        $total_value = (float) WC()->cart->get_totals()['total'];

        ?>
        <script>
        if (typeof gtag !== 'undefined') {
            gtag('event', 'begin_checkout', {
                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>',
                value: <?php echo esc_js( $total_value ); ?>,
                items: <?php echo json_encode( $items ); ?>
            });
        }
        </script>
        <?php
    }

    private function track_purchase() {
        $order_id = absint( get_query_var( 'order-received' ) );
        if ( empty( $order_id ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if event was already fired
        if ( $order->get_meta( '_ofls_google_purchase_fired_browser' ) === 'yes' ) {
            return;
        }

        // Apply immediate purchase & threshold check for Google Ads
        $should_fire_ads = $this->google_enabled && ! empty( $this->google_conversion_id );
        if ( $should_fire_ads ) {
            if ( ! $this->google_immediate_purchase ) {
                $should_fire_ads = false; // Held for server-side firing
            } else {
                $threshold = $this->google_courier_threshold ? (float) $this->google_courier_threshold : 0;
                if ( $threshold > 0 ) {
                    $phone = $order->get_billing_phone();
                    if ( ! empty( $phone ) ) {
                        $courier_checker = new class() {
                            use \fmb_engine\Traits\Courier;
                        };
                        $courier_ratio = $courier_checker->get_courier_rate_by_phone( $phone );
                        if ( $courier_ratio < $threshold ) {
                            $should_fire_ads = false; // Ratio too low, hold for manual ads-purchase
                        }
                    }
                }
            }
        }

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

        $total_value = (float) $order->get_total();
        $currency = $order->get_currency();

        // Retrieve customer info for Enhanced Conversions
        $email = strtolower( trim( $order->get_billing_email() ) );
        $phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );
        $first_name = trim( $order->get_billing_first_name() );
        $last_name = trim( $order->get_billing_last_name() );
        $city = trim( $order->get_billing_city() );
        $state = trim( $order->get_billing_state() );
        $postcode = trim( $order->get_billing_postcode() );
        $country = trim( $order->get_billing_country() );

        // Hash values safely server-side for privacy
        $hashed_email = ! empty( $email ) ? hash( 'sha256', $email ) : '';
        $hashed_phone = ! empty( $phone ) ? hash( 'sha256', $phone ) : '';
        $hashed_first_name = ! empty( $first_name ) ? hash( 'sha256', strtolower( $first_name ) ) : '';
        $hashed_last_name = ! empty( $last_name ) ? hash( 'sha256', strtolower( $last_name ) ) : '';
        $hashed_street = ! empty( $order->get_billing_address_1() ) ? hash( 'sha256', strtolower( trim( $order->get_billing_address_1() ) ) ) : '';

        ?>
        <script>
        if (typeof gtag !== 'undefined') {
            // Send Enhanced Conversions user data
            var userData = {};
            <?php if ( ! empty( $hashed_email ) ) : ?>
            userData.sha256_email_address = '<?php echo esc_js( $hashed_email ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $hashed_phone ) ) : ?>
            userData.sha256_phone_number = '<?php echo esc_js( $hashed_phone ); ?>';
            <?php endif; ?>
            
            var addressData = {};
            <?php if ( ! empty( $hashed_first_name ) ) : ?>
            addressData.sha256_first_name = '<?php echo esc_js( $hashed_first_name ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $hashed_last_name ) ) : ?>
            addressData.sha256_last_name = '<?php echo esc_js( $hashed_last_name ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $hashed_street ) ) : ?>
            addressData.sha256_street = '<?php echo esc_js( $hashed_street ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $city ) ) : ?>
            addressData.city = '<?php echo esc_js( $city ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $state ) ) : ?>
            addressData.region = '<?php echo esc_js( $state ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $postcode ) ) : ?>
            addressData.postal_code = '<?php echo esc_js( $postcode ); ?>';
            <?php endif; ?>
            <?php if ( ! empty( $country ) ) : ?>
            addressData.country = '<?php echo esc_js( $country ); ?>';
            <?php endif; ?>

            if (Object.keys(addressData).length > 0) {
                userData.address = addressData;
            }

            if (Object.keys(userData).length > 0) {
                gtag('set', 'user_data', userData);
            }

            // GA4 Purchase Event
            <?php if ( $this->ga_enabled && ! empty( $this->ga_measurement_id ) ) : ?>
            gtag('event', 'purchase', {
                transaction_id: '<?php echo esc_js( $order_id ); ?>',
                value: <?php echo esc_js( $total_value ); ?>,
                currency: '<?php echo esc_js( $currency ); ?>',
                items: <?php echo json_encode( $items ); ?>
            });
            <?php endif; ?>

            // Google Ads Conversion Event
            <?php if ( $should_fire_ads ) : ?>
            gtag('event', 'conversion', {
                'send_to': '<?php echo esc_js( $this->google_conversion_id ); ?>/<?php echo esc_js( $this->google_purchase_label ); ?>',
                'value': <?php echo esc_js( $total_value ); ?>,
                'currency': '<?php echo esc_js( $currency ); ?>',
                'transaction_id': '<?php echo esc_js( $order_id ); ?>'
            });
            <?php endif; ?>
        }
        </script>
        <?php

        // Mark as fired browser-side
        $order->update_meta_data( '_ofls_google_purchase_fired_browser', 'yes' );
        $order->save();
    }
}
