<?php
namespace fmb_engine\TikTok;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFLS_TikTok_Browser_Handler {

    private static $instance = null;
    private $enabled = false;
    private $pixel_ids = array();
    private $immediate_purchase = false;
    private $courier_threshold = 0;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->enabled = get_option('ofls_tiktok_enabled', false);
        $this->immediate_purchase = get_option('ofls_tiktok_immediate_purchase', false);
        $this->courier_threshold = get_option('ofls_tiktok_courier_threshold', 0); // TikTok specific threshold

        $pixel_ids = get_option('ofls_tiktok_pixel_ids', array());
        if ( ! is_array( $pixel_ids ) ) {
            $pixel_ids = array();
        }

        // Fallback for single pixel ID
        if ( empty( $pixel_ids ) ) {
            $single_pixel = get_option('ofls_tiktok_pixel_id', '');
            if ( ! empty( $single_pixel ) ) {
                $pixel_ids = array( $single_pixel );
            }
        }

        $this->pixel_ids = array_filter( array_map( 'trim', $pixel_ids ) );

        if ( ! $this->enabled || empty( $this->pixel_ids ) ) {
            return;
        }

        add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
        add_action( 'wp_footer', array( $this, 'inject_browser_events' ) );
        add_action( 'woocommerce_add_to_cart', array( $this, 'queue_add_to_cart' ), 10, 6 );
        add_action( 'init', array( $this, 'extend_tiktok_cookies' ) );
    }

    public function extend_tiktok_cookies() {
        if ( ! headers_sent() ) {
            $expire = 2147483647; // Year 2038
            $domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
            
            if ( isset( $_GET['ttclid'] ) ) {
                setcookie( 'ttclid', sanitize_text_field( wp_unslash( $_GET['ttclid'] ) ), $expire, '/', $domain );
            } elseif ( isset( $_COOKIE['ttclid'] ) ) {
                setcookie( 'ttclid', sanitize_text_field( wp_unslash( $_COOKIE['ttclid'] ) ), $expire, '/', $domain );
            }
            
            if ( isset( $_COOKIE['_ttp'] ) ) {
                setcookie( '_ttp', sanitize_text_field( wp_unslash( $_COOKIE['_ttp'] ) ), $expire, '/', $domain );
            }
        }
    }

    public function queue_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return; // AJAX handles its own JS event
        }

        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $pending = WC()->session->get( 'ofls_tiktok_pending_add_to_cart', array() );
        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( $product ) {
            $pending[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price() ? (float) $product->get_price() : 0.0,
                'quantity' => (int) $quantity,
                'type' => $product->is_type( 'variable' ) ? 'product_group' : 'product',
                'event_id' => class_exists( '\fmb_engine\TikTok\OFLS_TikTok_Server_Handler' ) ? \fmb_engine\TikTok\OFLS_TikTok_Server_Handler::get_event_id('AddToCart') : 'add_to_cart_' . $product->get_id() . '_' . time()
            );
            WC()->session->set( 'ofls_tiktok_pending_add_to_cart', $pending );
        }
    }

    public function inject_base_pixel() {
        $pageview_event_id = class_exists( '\fmb_engine\TikTok\OFLS_TikTok_Server_Handler' ) ? \fmb_engine\TikTok\OFLS_TikTok_Server_Handler::get_event_id('Pageview') : 'pageview_' . time();
        ?>
        <!-- TikTok Pixel Code Added by FMB Engine -->
        <script>
        !function (w, d, t) {
          w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
          
          <?php foreach ( $this->pixel_ids as $pixel_id ) : ?>
          ttq.load('<?php echo esc_js( $pixel_id ); ?>');
          <?php endforeach; ?>
        }(window, document, 'ttq');
        </script>
        <?php $this->render_advanced_matching(); ?>
        <script>
        if (typeof ttq !== 'undefined') {
            ttq.page(); // Standard TikTok PageView (Browser Only)
        }
        </script>
        <!-- End TikTok Pixel Code -->
        <script>
        function orderflowSendTikTokServerEvent(eventName, eventId, properties) {
            if (typeof jQuery === 'undefined') return;
            properties = properties || {};
            var data = {
                action: 'orderflow_tiktok_server_event',
                event_name: eventName,
                event_id: eventId,
                url: window.location.href,
                contents: properties.contents || [],
                value: properties.value || 0
            };
            var match = document.cookie.match(new RegExp('(^| )ttclid=([^;]+)'));
            if (match) data.ttclid = match[2];
            var ttpMatch = document.cookie.match(new RegExp('(^| )_ttp=([^;]+)'));
            if (ttpMatch) data.ttp = ttpMatch[2];

            jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                // console.log('TikTok CAPI Server Response:', response);
            });
        }
        </script>
        <?php
    }

    private function render_advanced_matching() {
        if ( ! get_option('ofls_tiktok_advanced_matching_enabled', false) ) {
            return;
        }

        $identities = array();
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( $user->user_email ) {
                $identities['email'] = hash('sha256', strtolower(trim($user->user_email)));
                $identities['external_id'] = $identities['email'];
            }
            $phone = get_user_meta( $user->ID, 'billing_phone', true );
            if ( $phone ) {
                $identities['phone_number'] = hash('sha256', preg_replace('/[^0-9]/', '', $phone));
            }
            $fn = get_user_meta( $user->ID, 'billing_first_name', true ) ?: $user->first_name;
            if ( $fn ) {
                $identities['fn'] = hash('sha256', strtolower(trim($fn)));
            }
            $ln = get_user_meta( $user->ID, 'billing_last_name', true ) ?: $user->last_name;
            if ( $ln ) {
                $identities['ln'] = hash('sha256', strtolower(trim($ln)));
            }
            $city = get_user_meta( $user->ID, 'billing_city', true );
            if ( $city ) {
                $identities['ct'] = hash('sha256', strtolower(trim($city)));
            }
            $state = get_user_meta( $user->ID, 'billing_state', true );
            if ( $state ) {
                $identities['st'] = hash('sha256', strtolower(trim($state)));
            }
            $postcode = get_user_meta( $user->ID, 'billing_postcode', true );
            if ( $postcode ) {
                $identities['zp'] = hash('sha256', strtolower(trim($postcode)));
            }
            $country = get_user_meta( $user->ID, 'billing_country', true );
            if ( $country ) {
                $identities['country'] = hash('sha256', strtolower(trim($country)));
            }
        }

        if ( empty( $identities ) ) {
            return;
        }

        ?>
        <script>
        if (typeof ttq !== 'undefined') {
            ttq.identify(<?php echo json_encode($identities); ?>);
        }
        </script>
        <?php
    }

    public function inject_browser_events() {
        // Output dynamic scripts for Add to Cart
        $this->inject_add_to_cart_scripts();

        if ( is_product() ) {
            $this->track_view_content();
        } elseif ( is_cart() ) {
            // Track add to cart on cart page if needed, but normally handled via ajax or button clicks
        } elseif ( is_checkout() && ! is_wc_endpoint_url() ) {
            $this->track_initiate_checkout();
        // নিচে CartFlows-এর wcf-order কন্ডিশনটি যুক্ত করা হলো
        } elseif ( is_wc_endpoint_url( 'order-received' ) || isset( $_GET['wcf-order'] ) ) {
            $this->track_complete_payment();
        }
    }

    private function inject_add_to_cart_scripts() {
        // 1. Process pending non-AJAX add-to-carts from session
        if ( function_exists( 'WC' ) && WC()->session ) {
            $pending = WC()->session->get( 'ofls_tiktok_pending_add_to_cart' );
            if ( ! empty( $pending ) && is_array( $pending ) ) {
                ?>
                <script>
                if (typeof ttq !== 'undefined') {
                    <?php foreach ( $pending as $item ) : 
                        $event_id = isset( $item['event_id'] ) ? $item['event_id'] : 'add_to_cart_' . $item['id'] . '_' . time();
                    ?>
                    var atcProps_<?php echo $item['id']; ?> = {
                        contents: [{
                            content_id: '<?php echo esc_js( $item['id'] ); ?>',
                            content_type: '<?php echo esc_js( $item['type'] ); ?>',
                            content_name: '<?php echo esc_js( $item['name'] ); ?>',
                            quantity: <?php echo esc_js( $item['quantity'] ); ?>,
                            price: <?php echo esc_js( $item['price'] ); ?>
                        }],
                        value: <?php echo esc_js( $item['price'] * $item['quantity'] ); ?>,
                        currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                    };
                    ttq.track('AddToCart', atcProps_<?php echo $item['id']; ?>, {
                        event_id: '<?php echo esc_js( $event_id ); ?>'
                    });
                    <?php if ( get_option('ofls_tiktok_use_server_api', false) ) : ?>
                    if (typeof orderflowSendTikTokServerEvent === 'function') {
                        var atcServerProps_<?php echo $item['id']; ?> = {
                            contents: [
                                {
                                    content_id: '<?php echo esc_js( $item['id'] ); ?>',
                                    content_type: '<?php echo esc_js( $item['type'] ); ?>',
                                    content_name: '<?php echo esc_js( $item['name'] ); ?>',
                                    quantity: <?php echo esc_js( $item['quantity'] ); ?>,
                                    price: <?php echo esc_js( $item['price'] ); ?>
                                }
                            ],
                            value: <?php echo esc_js( $item['price'] * $item['quantity'] ); ?>,
                            currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                        };
                        orderflowSendTikTokServerEvent('AddToCart', '<?php echo esc_js( $event_id ); ?>', atcServerProps_<?php echo $item['id']; ?>);
                    }
                    <?php endif; ?>
                    <?php endforeach; ?>
                }
                </script>
                <?php
                WC()->session->set( 'ofls_tiktok_pending_add_to_cart', null );
            }
        }

        // 2. AJAX WooCommerce add to cart event listener
        ?>
        <script>
        jQuery(document).ready(function($) {
            // AJAX Add to Cart handler
            $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
                if (typeof ttq !== 'undefined' && $button) {
                    var product_id = $button.data('product_id');
                    var quantity = $button.data('product_qty') || 1;
                    var price = $button.data('price') || 0; // Requires data-price attribute, otherwise defaults to 0
                    if (product_id) {
                        var atcAjaxProps = {
                            contents: [{
                                content_id: String(product_id),
                                content_type: 'product',
                                quantity: parseInt(quantity),
                                price: parseFloat(price)
                            }],
                            value: parseFloat(price) * parseInt(quantity),
                            currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                        };
                        var eventId = 'add_to_cart_' + product_id + '_' + new Date().getTime();
                        ttq.track('AddToCart', atcAjaxProps, {
                            event_id: eventId
                        });
                        <?php if ( get_option('ofls_tiktok_use_server_api', false) ) : ?>
                        if (typeof orderflowSendTikTokServerEvent === 'function') {
                            var atcAjaxServerProps = {
                                contents: [
                                    {
                                        content_id: String(product_id),
                                        content_type: 'product',
                                        quantity: parseInt(quantity),
                                        price: parseFloat(price)
                                    }
                                ],
                                value: parseFloat(price) * parseInt(quantity),
                                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                            };
                            orderflowSendTikTokServerEvent('AddToCart', eventId, atcAjaxServerProps);
                        }
                        <?php endif; ?>
                    }
                }
            });

            // Single Product Page Add to Cart form submit handler
            <?php if ( is_product() ) : 
                global $product;
                if ( ! is_a( $product, 'WC_Product' ) ) {
                    $product = wc_get_product( get_the_ID() );
                }
                if ( $product ) :
            ?>
            $('form.cart').on('submit', function() {
                if (typeof ttq !== 'undefined') {
                    var qty = parseInt($(this).find('input.qty').val()) || 1;
                    var atcFormProps = {
                        contents: [{
                            content_id: '<?php echo esc_js( $product->get_id() ); ?>',
                            content_type: '<?php echo esc_js( $product->is_type( 'variable' ) ? 'product_group' : 'product' ); ?>',
                            content_name: '<?php echo esc_js( $product->get_name() ); ?>',
                            quantity: qty,
                            price: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>
                        }],
                        value: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?> * qty,
                        currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                    };
                    var eventId = 'add_to_cart_<?php echo esc_js( $product->get_id() ); ?>_' + new Date().getTime();
                    ttq.track('AddToCart', atcFormProps, {
                        event_id: eventId
                    });
                    <?php if ( get_option('ofls_tiktok_use_server_api', false) ) : ?>
                    if (typeof orderflowSendTikTokServerEvent === 'function') {
                        var atcFormServerProps = {
                            contents: [
                                {
                                    content_id: '<?php echo esc_js( $product->get_id() ); ?>',
                                    content_type: '<?php echo esc_js( $product->is_type( 'variable' ) ? 'product_group' : 'product' ); ?>',
                                    content_name: '<?php echo esc_js( $product->get_name() ); ?>',
                                    quantity: qty,
                                    price: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>
                                }
                            ],
                            value: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?> * qty,
                            currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                        };
                        orderflowSendTikTokServerEvent('AddToCart', eventId, atcFormServerProps);
                    }
                    <?php endif; ?>
                }
            });
            <?php endif; endif; ?>
        });
        </script>
        <?php
    }

    private function track_view_content() {
        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_the_ID() );
        }
        if ( ! $product ) return;

        $content_id = $product->get_id();
        if ( $product->is_type( 'variable' ) ) {
            $content_type = 'product_group';
        } else {
            $content_type = 'product';
        }

        $categories = get_the_terms( $product->get_id(), 'product_cat' );
        $category_name = '';
        if ( $categories && ! is_wp_error( $categories ) ) {
            $category_name = $categories[0]->name;
        }

        $event_id = class_exists( '\fmb_engine\TikTok\OFLS_TikTok_Server_Handler' ) ? \fmb_engine\TikTok\OFLS_TikTok_Server_Handler::get_event_id('ViewContent') : 'view_' . $content_id . '_' . time();

        ?>
        <script>
        if (typeof ttq !== 'undefined') {
            var viewContentProps = {
                contents: [{
                    content_id: '<?php echo esc_js( $content_id ); ?>',
                    content_type: '<?php echo esc_js( $content_type ); ?>',
                    content_name: '<?php echo esc_js( $product->get_name() ); ?>',
                    <?php if ( $category_name ) : ?>
                    content_category: '<?php echo esc_js( $category_name ); ?>',
                    <?php endif; ?>
                    quantity: 1,
                    price: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>
                }],
                value: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>,
                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
            };
            
            ttq.track('ViewContent', viewContentProps, {
                event_id: '<?php echo esc_js( $event_id ); ?>'
            });

            <?php if ( get_option('ofls_tiktok_use_server_api', false) ) : ?>
            if (typeof orderflowSendTikTokServerEvent === 'function') {
                var serverProps = {
                    contents: [
                        {
                            content_id: '<?php echo esc_js( $content_id ); ?>',
                            content_type: '<?php echo esc_js( $content_type ); ?>',
                            content_name: '<?php echo esc_js( $product->get_name() ); ?>',
                            quantity: 1,
                            price: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>
                        }
                    ],
                    value: <?php echo esc_js( $product->get_price() ? $product->get_price() : 0 ); ?>,
                    currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
                };
                orderflowSendTikTokServerEvent('ViewContent', '<?php echo esc_js( $event_id ); ?>', serverProps);
            }
            <?php endif; ?>
        }
        </script>
        <?php
    }

    private function track_initiate_checkout() {
        if ( ! WC()->cart ) return;

        $contents = array();
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $contents[] = array(
                'content_id' => $_product->get_id(),
                'content_type' => 'product',
                'content_name' => $_product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $_product->get_price()
            );
        }

        $event_id = class_exists( '\fmb_engine\TikTok\OFLS_TikTok_Server_Handler' ) ? \fmb_engine\TikTok\OFLS_TikTok_Server_Handler::get_event_id('InitiateCheckout') : 'init_checkout_' . time();

        ?>
        <script>
        if (typeof ttq !== 'undefined') {
            var checkoutProps = {
                contents: <?php echo json_encode( $contents ); ?>,
                value: <?php echo esc_js( WC()->cart->get_totals()['total'] ); ?>,
                currency: '<?php echo esc_js( get_woocommerce_currency() ); ?>'
            };
            ttq.track('InitiateCheckout', checkoutProps, {
                event_id: '<?php echo esc_js( $event_id ); ?>'
            });
            <?php if ( get_option('ofls_tiktok_use_server_api', false) ) : ?>
            if (typeof orderflowSendTikTokServerEvent === 'function') {
                orderflowSendTikTokServerEvent('InitiateCheckout', '<?php echo esc_js( $event_id ); ?>', checkoutProps);
            }
            <?php endif; ?>
        }
        </script>
        <?php
    }

    private function track_complete_payment() {
        $order_id = absint( get_query_var( 'order-received' ) );
        
        // CartFlows URL থেকে অর্ডার আইডি ধরার লজিক যুক্ত করা হলো
        if ( empty( $order_id ) && isset( $_GET['wcf-order'] ) ) {
            $order_id = absint( $_GET['wcf-order'] );
        }

        if ( empty( $order_id ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        if ( $order->get_meta( '_ofls_tiktok_purchase_fired_browser' ) === 'yes' ) return;

        // Apply advanced ratio logic
        if ( ! $this->immediate_purchase ) {
            return; // Held for server-side firing only
        }

        $threshold = $this->courier_threshold ? (float) $this->courier_threshold : 0;
        if ( $threshold > 0 ) {
            $phone = $order->get_billing_phone();
            if ( ! empty( $phone ) ) {
                $courier_checker = new class() {
                    use \fmb_engine\Traits\Courier;
                };
                $courier_ratio = $courier_checker->get_courier_rate_by_phone( $phone );

                if ( $courier_ratio < $threshold ) {
                    return; // Ratio too low, hold for manual ads-purchase
                }
            }
        }

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

        $event_id = 'purchase_' . $order->get_id();
        ?>
        <script>
        if (typeof ttq !== 'undefined') {
            var purchaseProps = {
                contents: <?php echo json_encode( $contents ); ?>,
                value: <?php echo esc_js( $order->get_total() ); ?>,
                currency: '<?php echo esc_js( $order->get_currency() ); ?>'
            };
            ttq.track('CompletePayment', purchaseProps, {
                event_id: '<?php echo esc_js( $event_id ); ?>'
            });
        }
        </script>
        <?php
        
        $order->update_meta_data( '_ofls_tiktok_purchase_fired_browser', 'yes' );
        $order->save();
    }
}
