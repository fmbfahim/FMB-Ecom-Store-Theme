<?php

namespace fmb_engine\Facebook;

use fmb_engine\Traits\Courier;
use fmb_engine\Facebook\EventTypes;
use fmb_engine\Facebook\SingleEvent;
use fmb_engine\Facebook\EventIdGenerator;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Api;
use FacebookAds\Http\Exception\RequestException;

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class PurchaseEventManager {
    use Courier;

    private static $_instance;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_purchase_event_data' ), 20, 1 );
        add_action( 'woocommerce_new_order', array( $this, 'save_purchase_event_data' ), 20, 1 );

        
        add_action( 'woocommerce_order_status_changed', array( $this, 'send_purchase_event_on_status_change' ), 10, 3 );

    }

    public function save_purchase_event_data( $order_id ) {

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $this->generate_and_save_purchase_event_data( $order );
    }

    private function generate_and_save_purchase_event_data( $order ) {
        
        $original_key = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] : null;
        $_REQUEST['key'] = $order->get_order_key();

        $of_order_id_filter = static function () use ( $order ) {
            return $order->get_id();
        };
        add_filter( 'orderflow_woo_checkout_order_id', $of_order_id_filter, 10, 0 );

        $single_event = new SingleEvent( 'woo_purchase', EventTypes::$STATIC, 'woo' );

        $generated_events = fmb_engine_Facebook()->generateEvents( $single_event );

        remove_filter( 'orderflow_woo_checkout_order_id', $of_order_id_filter, 10 );

        if ( $original_key !== null ) {
            $_REQUEST['key'] = $original_key;
        } else {
            unset( $_REQUEST['key'] );
        }

        $server_event_data = null;
        $pixel_ids = null;
        $event_id = null;

        if ( ! empty( $generated_events ) ) {
            $generated_event = $generated_events[0];

            if ( isset( $_COOKIE['orderflow_landing_page'] ) ) {
                $generated_event->addParams( array( 'landing_page' => $_COOKIE['orderflow_landing_page'] ) );
            }

            $server_event = \fmb_engine\Facebook\FacebookCapiEventHelper::mapEventToServerEvent( $generated_event );
            if ( $server_event ) {
                $server_event_data = $this->extract_server_event_data( $server_event, $order );
                $pixel_ids = $generated_event->payload['pixelIds'];
                $event_id = isset( $generated_event->payload['eventID'] ) ? $generated_event->payload['eventID'] : EventIdGenerator::guidv4();
            }
        }

        // Fallback for manual orders or when generateEvents fails (e.g. AJAX requests)
        if ( empty( $server_event_data ) ) {
            $pixel_ids = (array) fmb_engine_Facebook()->getOption( 'orderflow_pixel_id' );
            $pixel_ids = array_filter( array_unique( $pixel_ids ) );
            
            if ( empty( $pixel_ids ) ) {
                return;
            }

            $raw_phone = $order->get_billing_phone();
            $norm_phone = preg_replace('/[^\d]/', '', (string)$raw_phone);
            if (strlen($norm_phone) === 11 && strpos($norm_phone, '01') === 0) {
                $norm_phone = '88' . $norm_phone;
            } elseif (strlen($norm_phone) === 10 && strpos($norm_phone, '1') === 0) {
                $norm_phone = '880' . $norm_phone;
            }

            $raw_fn = trim((string)$order->get_billing_first_name());
            $raw_ln = trim((string)$order->get_billing_last_name());
            if (empty($raw_ln) && !empty($raw_fn)) {
                $parts = array_filter(explode(' ', $raw_fn));
                $first_name = array_shift($parts);
                $last_name = !empty($parts) ? implode(' ', $parts) : $first_name;
            } else {
                $first_name = $raw_fn;
                $last_name = $raw_ln ?: $raw_fn;
            }

            $user_ip = function_exists('fmb_get_client_ip_address') ? fmb_get_client_ip_address(true) : $order->get_customer_ip_address();
            if (empty($user_ip)) {
                $user_ip = $order->get_customer_ip_address() ?: (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '');
            }

            $user_data = array_filter(array(
                'email'             => $order->get_billing_email(),
                'phone'             => $norm_phone,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'city'              => $order->get_billing_city() ?: 'Dhaka',
                'state'             => $order->get_billing_state() ?: 'Dhaka',
                'zip_code'          => $order->get_billing_postcode() ?: '1200',
                'country_code'      => strtolower($order->get_billing_country() ?: 'bd'),
                'client_ip_address' => $user_ip,
                'client_user_agent' => $order->get_customer_user_agent() ?: ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'fbp'               => $order->get_meta('_fbp') ?: ($order->get_meta('_customer_fbp') ?: ($_COOKIE['_fbp'] ?? null)),
                'fbc'               => $order->get_meta('_fbc') ?: ($order->get_meta('_customer_fbc') ?: ($_COOKIE['_fbc'] ?? null)),
                'external_id'       => $order->get_meta('external_id') ?: ($_COOKIE['_fmb_external_id'] ?? ($_COOKIE['orderflow_id'] ?? null)),
            ));

            $contents = array();
            $content_ids = array();
            $num_items = 0;
            
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                if ( $product ) {
                    $pid = (string) $product->get_id();
                    $qty = (int) $item->get_quantity();
                    $contents[] = array(
                        'product_id' => $pid,
                        'quantity'   => $qty,
                        'item_price' => $order->get_item_total( $item, false, false ),
                    );
                    $content_ids[] = $pid;
                    $num_items += $qty;
                }
            }

            $custom_data = array(
                'value'             => (float) $order->get_total(),
                'currency'          => strtoupper( $order->get_currency() ),
                'content_type'      => 'product',
                'content_ids'       => $content_ids,
                'num_items'         => $num_items,
                'contents'          => $contents,
                'order_id'          => (string) $order->get_id()
            );

            $server_event_data = array(
                'event_name'       => 'Purchase',
                'event_time'       => time(),
                'action_source'    => 'website',
                'event_source_url' => site_url(),
                'user_data'        => $user_data,
                'custom_data'      => $custom_data,
            );
            
            $event_id = EventIdGenerator::guidv4();
        }

        $purchase_event_data = array(
            'event_id' => $event_id,
            'server_event_data' => $server_event_data, 
            'pixel_ids' => $pixel_ids, 
            'woo_order' => $order->get_id(),
            'event_time' => time(),
        );

        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $order->update_meta_data( '_ads_purchase_event_data', $purchase_event_data );
            $order->save();
        } else {
            update_post_meta( $order->get_id(), '_ads_purchase_event_data', $purchase_event_data );
        }
    }

    

    private function extract_server_event_data( $server_event, $order ) {
        $data = array();

        
        $data['event_name'] = $server_event->getEventName();
        $data['event_time'] = $server_event->getEventTime();
        $data['event_id'] = $server_event->getEventId();
        $data['event_source_url'] = $server_event->getEventSourceUrl();
        $data['action_source'] = $server_event->getActionSource();

        
        $user_data = $server_event->getUserData();
        if ( $user_data ) {
            $user_data_array = array();

            
            $user_data_array['fbp'] = method_exists( $user_data, 'getFbp' ) ? $user_data->getFbp() : null;
            $user_data_array['fbc'] = method_exists( $user_data, 'getFbc' ) ? $user_data->getFbc() : null;
            $user_data_array['client_ip_address'] = method_exists( $user_data, 'getClientIpAddress' ) ? $user_data->getClientIpAddress() : null;
            $user_data_array['client_user_agent'] = method_exists( $user_data, 'getClientUserAgent' ) ? $user_data->getClientUserAgent() : null;
            $user_data_array['email'] = method_exists( $user_data, 'getEmail' ) ? $user_data->getEmail() : null;
            $user_data_array['phone'] = method_exists( $user_data, 'getPhone' ) ? $user_data->getPhone() : null;
            $user_data_array['first_name'] = method_exists( $user_data, 'getFirstName' ) ? $user_data->getFirstName() : null;
            $user_data_array['last_name'] = method_exists( $user_data, 'getLastName' ) ? $user_data->getLastName() : null;
            $user_data_array['city'] = method_exists( $user_data, 'getCity' ) ? $user_data->getCity() : null;
            $user_data_array['state'] = method_exists( $user_data, 'getState' ) ? $user_data->getState() : null;
            $user_data_array['zip_code'] = method_exists( $user_data, 'getZipCode' ) ? $user_data->getZipCode() : null;
            $user_data_array['country_code'] = method_exists( $user_data, 'getCountryCode' ) ? $user_data->getCountryCode() : null;
            $user_data_array['external_id'] = method_exists( $user_data, 'getExternalId' ) ? $user_data->getExternalId() : null;
            $user_data_array['fb_login_id'] = method_exists( $user_data, 'getFbLoginId' ) ? $user_data->getFbLoginId() : null;

            $data['user_data'] = $user_data_array;
        }

        
        $custom_data = $server_event->getCustomData();
        if ( $custom_data ) {
            $custom_data_array = array();

            
            $custom_data_array['value'] = method_exists( $custom_data, 'getValue' ) ? $custom_data->getValue() : null;
            $custom_data_array['currency'] = method_exists( $custom_data, 'getCurrency' ) ? $custom_data->getCurrency() : null;
            $custom_data_array['content_type'] = method_exists( $custom_data, 'getContentType' ) ? $custom_data->getContentType() : null;
            $custom_data_array['content_ids'] = method_exists( $custom_data, 'getContentIds' ) ? $custom_data->getContentIds() : null;
            $custom_data_array['content_name'] = method_exists( $custom_data, 'getContentName' ) ? $custom_data->getContentName() : null;
            $custom_data_array['content_category'] = method_exists( $custom_data, 'getContentCategory' ) ? $custom_data->getContentCategory() : null;
            $custom_data_array['num_items'] = method_exists( $custom_data, 'getNumItems' ) ? $custom_data->getNumItems() : null;
            $custom_data_array['order_id'] = method_exists( $custom_data, 'getOrderId' ) ? $custom_data->getOrderId() : null;

            
            $contents = method_exists( $custom_data, 'getContents' ) ? $custom_data->getContents() : null;
            if ( $contents && is_array( $contents ) ) {
                $contents_array = array();
                foreach ( $contents as $content ) {
                    if ( is_object( $content ) ) {
                        $contents_array[] = array(
                            'product_id' => method_exists( $content, 'getProductId' ) ? $content->getProductId() : null,
                            'quantity' => method_exists( $content, 'getQuantity' ) ? $content->getQuantity() : null,
                            'item_price' => method_exists( $content, 'getItemPrice' ) ? $content->getItemPrice() : null,
                        );
                    }
                }
                $custom_data_array['contents'] = $contents_array;
            }

            
            $custom_properties = method_exists( $custom_data, 'getCustomProperties' ) ? $custom_data->getCustomProperties() : null;
            if ( $custom_properties ) {
                $custom_data_array['custom_properties'] = $custom_properties;
            }

            $data['custom_data'] = $custom_data_array;
        }

        
        $data_processing_options = method_exists( $server_event, 'getDataProcessingOptions' ) ? $server_event->getDataProcessingOptions() : null;
        if ( $data_processing_options ) {
            $data['data_processing_options'] = $data_processing_options;
            $data['data_processing_options_country'] = method_exists( $server_event, 'getDataProcessingOptionsCountry' ) ? $server_event->getDataProcessingOptionsCountry() : null;
            $data['data_processing_options_state'] = method_exists( $server_event, 'getDataProcessingOptionsState' ) ? $server_event->getDataProcessingOptionsState() : null;
        }

        return $data;
    }

    

    private function recreate_server_event_from_saved_data( $server_event_data ) {
        if ( empty( $server_event_data ) ) {
            return null;
        }

        
        $event = new \FacebookAds\Object\ServerSide\Event();

        
        if ( isset( $server_event_data['event_name'] ) ) {
            $event->setEventName( $server_event_data['event_name'] );
        } else {
            $event->setEventName( 'Purchase' );
        }
        if ( isset( $server_event_data['event_time'] ) ) {
            $event->setEventTime( $server_event_data['event_time'] );
        } else {
            $event->setEventTime( time() );
        }
        if ( isset( $server_event_data['event_id'] ) ) {
            $event->setEventId( $server_event_data['event_id'] );
        }
        if ( isset( $server_event_data['event_source_url'] ) ) {
            $event->setEventSourceUrl( $server_event_data['event_source_url'] );
        }
        if ( isset( $server_event_data['action_source'] ) ) {
            $event->setActionSource( $server_event_data['action_source'] );
        }

        
        if ( isset( $server_event_data['user_data'] ) && is_array( $server_event_data['user_data'] ) ) {
            $user_data = new \FacebookAds\Object\ServerSide\UserData();

            $ud = $server_event_data['user_data'];
            if ( isset( $ud['fbp'] ) && ! empty( $ud['fbp'] ) ) {
                $user_data->setFbp( $ud['fbp'] );
            }
            if ( isset( $ud['fbc'] ) && ! empty( $ud['fbc'] ) ) {
                $user_data->setFbc( $ud['fbc'] );
            }
            if ( isset( $ud['client_ip_address'] ) && ! empty( $ud['client_ip_address'] ) ) {
                $user_data->setClientIpAddress( $ud['client_ip_address'] );
            }
            if ( isset( $ud['client_user_agent'] ) && ! empty( $ud['client_user_agent'] ) ) {
                $user_data->setClientUserAgent( $ud['client_user_agent'] );
            }
            if ( isset( $ud['email'] ) && ! empty( $ud['email'] ) ) {
                $user_data->setEmail( $ud['email'] );
            }
            if ( isset( $ud['phone'] ) && ! empty( $ud['phone'] ) ) {
                $user_data->setPhone( $ud['phone'] );
            }
            if ( isset( $ud['first_name'] ) && ! empty( $ud['first_name'] ) ) {
                $user_data->setFirstName( $ud['first_name'] );
            }
            if ( isset( $ud['last_name'] ) && ! empty( $ud['last_name'] ) ) {
                $user_data->setLastName( $ud['last_name'] );
            }
            if ( isset( $ud['city'] ) && ! empty( $ud['city'] ) ) {
                $user_data->setCity( $ud['city'] );
            }
            if ( isset( $ud['state'] ) && ! empty( $ud['state'] ) ) {
                $user_data->setState( $ud['state'] );
            }
            if ( isset( $ud['zip_code'] ) && ! empty( $ud['zip_code'] ) ) {
                $user_data->setZipCode( $ud['zip_code'] );
            }
            if ( isset( $ud['country_code'] ) && ! empty( $ud['country_code'] ) ) {
                $user_data->setCountryCode( $ud['country_code'] );
            }
            if ( isset( $ud['external_id'] ) && ! empty( $ud['external_id'] ) ) {
                $user_data->setExternalId( $ud['external_id'] );
            }
            if ( isset( $ud['fb_login_id'] ) && ! empty( $ud['fb_login_id'] ) ) {
                $user_data->setFbLoginId( $ud['fb_login_id'] );
            }

            $event->setUserData( $user_data );
        }

        
        if ( isset( $server_event_data['custom_data'] ) && is_array( $server_event_data['custom_data'] ) ) {
            $custom_data = new \FacebookAds\Object\ServerSide\CustomData();

            $cd = $server_event_data['custom_data'];
            if ( isset( $cd['value'] ) ) {
                $custom_data->setValue( $cd['value'] );
            }
            if ( isset( $cd['currency'] ) ) {
                $custom_data->setCurrency( strtoupper($cd['currency']) );
            }
            if ( isset( $cd['content_type'] ) ) {
                $custom_data->setContentType( $cd['content_type'] );
            }
            if ( isset( $cd['content_ids'] ) && is_array( $cd['content_ids'] ) ) {
                $custom_data->setContentIds( $cd['content_ids'] );
            }
            if ( isset( $cd['content_name'] ) ) {
                $custom_data->setContentName( $cd['content_name'] );
            }
            if ( isset( $cd['content_category'] ) ) {
                $custom_data->setContentCategory( $cd['content_category'] );
            }
            if ( isset( $cd['num_items'] ) ) {
                $custom_data->setNumItems( $cd['num_items'] );
            }

            
            if ( isset( $cd['contents'] ) && is_array( $cd['contents'] ) ) {
                $contents = array();
                foreach ( $cd['contents'] as $content_data ) {
                    $content = new \FacebookAds\Object\ServerSide\Content();
                    if ( isset( $content_data['product_id'] ) ) {
                        $content->setProductId( $content_data['product_id'] );
                    }
                    if ( isset( $content_data['quantity'] ) ) {
                        $content->setQuantity( $content_data['quantity'] );
                    }
                    if ( isset( $content_data['item_price'] ) ) {
                        $content->setItemPrice( $content_data['item_price'] );
                    }
                    $contents[] = $content;
                }
                $custom_data->setContents( $contents );
            }

            
            if ( isset( $cd['custom_properties'] ) && is_array( $cd['custom_properties'] ) ) {
                $custom_data->setCustomProperties( $cd['custom_properties'] );
            }

            $event->setCustomData( $custom_data );
        }

        
        if ( isset( $server_event_data['data_processing_options'] ) ) {
            $event->setDataProcessingOptions( $server_event_data['data_processing_options'] );
            if ( isset( $server_event_data['data_processing_options_country'] ) ) {
                $event->setDataProcessingOptionsCountry( $server_event_data['data_processing_options_country'] );
            }
            if ( isset( $server_event_data['data_processing_options_state'] ) ) {
                $event->setDataProcessingOptionsState( $server_event_data['data_processing_options_state'] );
            }
        }

        return $event;
    }

    

    public function send_purchase_event_on_status_change( $order_id, $old_status, $new_status ) {
        $new_status = str_replace( 'wc-', '', $new_status );
        
        if ( $new_status !== 'ads-purchase' ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // ==========================================
        // INTELLIGENT DYNAMIC ROUTING LOGIC
        // ==========================================
        $is_fb_order = false;
        $is_tiktok_order = false;
        $is_ga4_order = false;
        $has_source_data = false;

        // 1. Check Facebook Meta
        $fb_meta = null;
        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $fb_meta = $order->get_meta( '_ads_purchase_event_data', true );
        } else {
            $fb_meta = get_post_meta( $order_id, '_ads_purchase_event_data', true );
        }
        if ( ! empty( $fb_meta ) ) {
            $is_fb_order = true;
            $has_source_data = true;
        }

        // 2. Check TikTok Meta
        $ttp = $order->get_meta( '_ttp', true );
        $ttclid = $order->get_meta( 'ttclid', true );
        if ( empty($ttclid) ) $ttclid = $order->get_meta( '_ttclid', true );
        if ( ! empty( $ttp ) || ! empty( $ttclid ) ) {
            $is_tiktok_order = true;
            $has_source_data = true;
        }

        // 3. Check GA4 Meta
        $gclid = $order->get_meta( 'gclid', true );
        if ( empty($gclid) ) $gclid = $order->get_meta( '_gclid', true );
        $gl = $order->get_meta( '_gl', true );
        if ( ! empty( $gclid ) || ! empty( $gl ) ) {
            $is_ga4_order = true;
            $has_source_data = true;
        }

        // 4. Backup Check: Native WooCommerce Attribution & UTMs
        $wc_source = strtolower( $order->get_meta( '_wc_order_attribution_source', true ) );
        $wc_utm_source = strtolower( $order->get_meta( '_wc_order_attribution_utm_source', true ) );
        $utm_source = strtolower( $order->get_meta( 'utm_source', true ) );
        if ( empty($utm_source) ) $utm_source = strtolower( $order->get_meta( '_utm_source', true ) );

        $backup_sources = array_filter( array( $wc_source, $wc_utm_source, $utm_source ) );

        if ( ! empty( $backup_sources ) ) {
            $has_source_data = true;
            foreach ( $backup_sources as $src ) {
                if ( strpos( $src, 'tiktok' ) !== false ) {
                    $is_tiktok_order = true;
                }
                if ( strpos( $src, 'facebook' ) !== false || strpos( $src, 'ig' ) !== false || strpos( $src, 'instagram' ) !== false || strpos( $src, 'fb' ) !== false ) {
                    $is_fb_order = true;
                }
                if ( strpos( $src, 'google' ) !== false || strpos( $src, 'ga' ) !== false || strpos( $src, 'youtube' ) !== false ) {
                    $is_ga4_order = true;
                }
            }
        }

        // Routing Decisions
        $should_fire_tiktok = false;
        $should_fire_ga4 = false;
        $should_fire_fb = false;

        if ( $has_source_data ) {
            // If source metadata exists, ONLY fire for the matched platforms
            $should_fire_tiktok = $is_tiktok_order;
            $should_fire_ga4 = $is_ga4_order;
            $should_fire_fb = $is_fb_order;
        } else {
            // Fallback: If NO source metadata exists, fire ALL enabled platforms
            $should_fire_tiktok = true;
            $should_fire_ga4 = true;
            $should_fire_fb = true;
        }

        // ==========================================
        // TIKTOK SERVER PURCHASE EVENT
        // ==========================================
        if ( $should_fire_tiktok && get_option( 'ofls_tiktok_enabled', false ) ) {
            $pixel_ids_raw = get_option('ofls_tiktok_pixel_ids', array());
            $access_tokens_raw = get_option('ofls_tiktok_access_tokens', array());
            $test_event_codes_raw = get_option('ofls_tiktok_test_event_codes', array());
            
            if ( ! is_array( $pixel_ids_raw ) ) {
                $single_pixel = get_option('ofls_tiktok_pixel_id', '');
                $pixel_ids_raw = ! empty( $single_pixel ) ? array( $single_pixel ) : array();
                $single_token = get_option('ofls_tiktok_access_token', '');
                $access_tokens_raw = ! empty( $single_token ) ? array( $single_token ) : array();
            }

            $tt_contents = array();
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $tt_contents[] = array(
                    'content_id' => (string) ($product ? $product->get_id() : $item->get_product_id()),
                    'content_type' => 'product',
                    'content_name' => $item->get_name(),
                    'quantity' => (int) $item->get_quantity(),
                    'price' => (float) $order->get_item_total( $item, true, true )
                );
            }

            $tt_user = array();
            $email = strtolower( trim( $order->get_billing_email() ) );
            if ( ! empty( $email ) ) $tt_user['external_id'] = hash( 'sha256', $email );
            $phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );
            if ( ! empty( $phone ) ) $tt_user['phone_number'] = hash( 'sha256', $phone );
            unset( $tt_user['ip'] );
            unset( $tt_user['user_agent'] );
            
            $tt_payload = array(
                'event' => 'CompletePayment',
                'event_id' => 'purchase_' . $order->get_id(),
                'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ),
                'context' => array(
                    'page' => array(
                        'url' => $order->get_checkout_order_received_url()
                    ),
                    'user' => $tt_user,
                    'ip' => $order->get_customer_ip_address(),
                    'user_agent' => $order->get_customer_user_agent(),
                ),
                'properties' => array(
                    'contents' => $tt_contents,
                    'value' => (float) $order->get_total(),
                    'currency' => strtoupper( $order->get_currency() )
                )
            );

            foreach ( $pixel_ids_raw as $index => $pixel_id ) {
                $pixel_id = trim( $pixel_id );
                if ( empty( $pixel_id ) ) continue;
                $token = isset( $access_tokens_raw[ $index ] ) ? trim( $access_tokens_raw[ $index ] ) : '';
                if ( empty( $token ) ) continue;
                $test_code = isset( $test_event_codes_raw[ $index ] ) ? trim( $test_event_codes_raw[ $index ] ) : '';

                $req_payload = array(
                    'pixel_code' => $pixel_id,
                    'data'       => array( $tt_payload )
                );

                if ( ! empty( $test_code ) ) {
                    $req_payload['test_event_code'] = $test_code;
                }

                $response = wp_remote_post( 'https://business-api.tiktok.com/open_api/v1.3/pixel/track/', array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Access-Token' => $token,
                    ),
                    'body' => wp_json_encode( $req_payload ),
                    'timeout' => 5,
                    'blocking' => true
                ) );

                if ( ! is_wp_error( $response ) ) {
                    $status_code = wp_remote_retrieve_response_code( $response );
                    $body = wp_remote_retrieve_body( $response );
                    $body_data = json_decode( $body, true );

                    if ( $status_code === 200 && isset( $body_data['code'] ) && $body_data['code'] === 0 ) {
                        update_post_meta( $order_id, '_tiktok_capi_status', 'success' );
                        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
                            $order->update_meta_data( '_tiktok_capi_status', 'success' );
                            $order->save();
                        }
                    }
                } else {
                    $status_code = 'error';
                    $body = $response->get_error_message();
                }

                // Debug Logging
                if ( get_option('ofls_tiktok_debug_mode', true) ) {
                    $log_data = array(
                        'pixel_id' => $pixel_id,
                        'request' => $req_payload,
                        'response' => is_wp_error( $response ) ? $body : json_decode( $body, true ),
                        'status_code' => $status_code
                    );
                    if ( function_exists('wc_get_logger') ) {
                        $logger = wc_get_logger();
                        $logger->debug( 'TikTok Manual Server API Request: ' . print_r( $log_data, true ), array( 'source' => 'tiktok-server-api' ) );
                    }
                }
            }
        }

        // ==========================================
        // GA4 MEASUREMENT PROTOCOL PURCHASE EVENT
        // ==========================================
        if ( $should_fire_ga4 && get_option( 'ofls_ga_enabled', false ) ) {
            $ga_measurement_id = get_option( 'ofls_ga_measurement_id', '' );
            $ga_api_secret = get_option( 'ofls_ga_api_secret', '' );

            if ( ! empty( $ga_measurement_id ) && ! empty( $ga_api_secret ) ) {
                $ga_items = array();
                foreach ( $order->get_items() as $item ) {
                    $product = $item->get_product();
                    $ga_items[] = array(
                        'item_id' => (string) ($product ? $product->get_id() : $item->get_product_id()),
                        'item_name' => $item->get_name(),
                        'price' => (float) $order->get_item_total( $item, true, true ),
                        'quantity' => (int) $item->get_quantity()
                    );
                }

                $ga_user = array();
                $email = strtolower( trim( $order->get_billing_email() ) );
                if ( ! empty( $email ) ) $ga_user['sha256_email_address'] = hash( 'sha256', $email );
                $phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );
                if ( ! empty( $phone ) ) $ga_user['sha256_phone_number'] = hash( 'sha256', $phone );

                $client_id = sprintf( '%04x%04x.%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );

                $ga_payload = array(
                    'client_id' => $client_id,
                    'events' => array(
                        array(
                            'name' => 'purchase',
                            'params' => array(
                                'transaction_id' => (string) $order->get_id(),
                                'value' => (float) $order->get_total(),
                                'currency' => strtoupper( $order->get_currency() ),
                                'items' => $ga_items
                            )
                        )
                    )
                );

                if ( ! empty( $ga_user ) ) {
                    $ga_payload['user_data'] = $ga_user;
                }

                $endpoint = add_query_arg( array(
                    'measurement_id' => $ga_measurement_id,
                    'api_secret'     => $ga_api_secret,
                ), 'https://www.google-analytics.com/mp/collect' );

                wp_remote_post( $endpoint, array(
                    'body' => wp_json_encode( $ga_payload ),
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'timeout' => 5,
                    'blocking' => true
                ) );
            }
        }

        // ==========================================
        // FACEBOOK CAPI PURCHASE EVENT
        // ==========================================
        // Verify that Facebook tracking is globally enabled
        if ( ! fmb_engine_Facebook()->enabled() ) {
            return;
        }

        // Intelligent routing blocker removed to keep FB native logic 100% untouched.

        $existing_results = null;
        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $existing_results = $order->get_meta( 'ads_purchase_event_results', true );
        } else {
            $existing_results = get_post_meta( $order_id, 'ads_purchase_event_results', true );
        }
        
        if ( is_array( $existing_results ) && ! empty( $existing_results ) ) {
            $first_result = $existing_results[0];
            if ( isset( $first_result['response'] ) && $first_result['response'] === 'success' ) {
                return;
            }
        }

        $purchase_event_data = null;
        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $purchase_event_data = $order->get_meta( '_ads_purchase_event_data', true );
        } else {
            $purchase_event_data = get_post_meta( $order_id, '_ads_purchase_event_data', true );
        }

        if ( ! $purchase_event_data || empty( $purchase_event_data ) ) {
            return;
        }

        if ( ! isset( $purchase_event_data['server_event_data'] ) || empty( $purchase_event_data['server_event_data'] ) ) {
            return;
        }

        $server_event = $this->recreate_server_event_from_saved_data( $purchase_event_data['server_event_data'] );

        if ( ! $server_event ) {
            return;
        }

        // AUTO-HEAL: Ensure Event ID is attached for Deduplication
        if ( ! $server_event->getEventId() && ! empty( $purchase_event_data['event_id'] ) ) {
            $server_event->setEventId( $purchase_event_data['event_id'] );
        } else if ( ! $server_event->getEventId() ) {
            $server_event->setEventId( EventIdGenerator::guidv4() );
        }

        $pixel_ids = isset( $purchase_event_data['pixel_ids'] ) ? $purchase_event_data['pixel_ids'] : array();

        if ( empty( $pixel_ids ) ) {
            return;
        }

        $saved_user_data = isset( $purchase_event_data['server_event_data']['user_data'] ) ? $purchase_event_data['server_event_data']['user_data'] : null;

        if ( fmb_engine_Facebook()->isServerApiEnabled() ) {
            $this->send_event_directly_to_api( $pixel_ids, $server_event, $saved_user_data, $order_id );
        } else {
            return;
        }

        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $order->update_meta_data( '_orderflow_purchase_event_fired', true );
            $order->save();
        } else {
            update_post_meta( $order_id, '_orderflow_purchase_event_fired', true );
        }
    }

    

    private function save_purchase_event_results_for_order( $pixel_id, $event, $response, $order_id = null ) {
        
        if ( empty( $order_id ) ) {
            $custom_data = $event->getCustomData();
            if ( $custom_data ) {
                $custom_properties = method_exists( $custom_data, 'getCustomProperties' ) ? $custom_data->getCustomProperties() : null;
                if ( $custom_properties && isset( $custom_properties['woo_order'] ) ) {
                    $order_id = $custom_properties['woo_order'];
                }
            }
        }

        if ( empty( $order_id ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        
        $existing_results = null;
        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $existing_results = $order->get_meta( 'ads_purchase_event_results', true );
        } else {
            $existing_results = get_post_meta( $order_id, 'ads_purchase_event_results', true );
        }

        if ( ! is_array( $existing_results ) ) {
            $existing_results = [];
        }

        
        $result_index = -1;
        foreach ( $existing_results as $index => $result ) {
            if ( isset( $result['pixel_id'] ) && $result['pixel_id'] === $pixel_id ) {
                $result_index = $index;
                break;
            }
        }

        
        $result_data = array(
            'event_name' => 'Purchase',
            'pixel_id' => $pixel_id,
            'event_id' => $event->getEventId(),
            'sent_at' => time(),
            'response' => $response ? 'success' : 'failed',
        );

        
        if ( $result_index >= 0 ) {
            $existing_results[ $result_index ]['sent_count'] = isset( $existing_results[ $result_index ]['sent_count'] ) 
                ? $existing_results[ $result_index ]['sent_count'] + 1 
                : 1;
            $existing_results[ $result_index ]['sent_at'] = time();
            $existing_results[ $result_index ]['response'] = $response ? 'success' : 'failed';
        } else {
            $result_data['sent_count'] = 1;
            $existing_results[] = $result_data;
        }

        
        if ( isWooCommerceVersionGte( '3.0.0' ) ) {
            $order->update_meta_data( 'ads_purchase_event_results', $existing_results );
            $order->save();
        } else {
            update_post_meta( $order_id, 'ads_purchase_event_results', $existing_results );
        }

        if ( $response ) {
            if ( class_exists( '\fmb_engine\Core' ) && method_exists( '\fmb_engine\Core', 'log_activity' ) ) {
                \fmb_engine\Core\Base::log_activity("অর্ডার #{$order_id}-এর ফেসবুক পারচেজ ইভেন্ট (CAPI) সফলভাবে পাঠানো হয়েছে।", '✅');
            }
        }

        
    }

    

    private function send_event_directly_to_api( $pixel_ids, $event, $saved_user_data = null, $order_id = null ) {
        if ( ! $event || apply_filters( 'orderflow_disable_server_event_filter', false ) ) {
            return;
        }

        
        $access_token = fmb_engine_Facebook()->getApiToken();
        $test_code = fmb_engine_Facebook()->getApiTestCode();

        foreach ( $pixel_ids as $pixel_id ) {
            if ( empty( $access_token[ $pixel_id ] ) ) {
                continue;
            }

            $event->setEventId( $event->getEventId() );

            $api = Api::init( null, null, $access_token[ $pixel_id ], false );
            $opts = $api->getHttpClient()->getAdapter()->getOpts();
            if ( $opts instanceof \ArrayObject && $opts->offsetExists( CURLOPT_CONNECTTIMEOUT ) ) {
                $opts->offsetSet( CURLOPT_CONNECTTIMEOUT, 30 );
                $api->getHttpClient()->getAdapter()->setOpts( $opts );
            }

            
            
            
            if ( $saved_user_data && is_array( $saved_user_data ) ) {
                $user_data = $event->getUserData();
                if ( ! $user_data ) {
                    $user_data = new \FacebookAds\Object\ServerSide\UserData();
                }

                
                if ( ! empty( $saved_user_data['client_ip_address'] ) ) {
                    $user_data->setClientIpAddress( $saved_user_data['client_ip_address'] );
                    
                }

                
                if ( ! empty( $saved_user_data['client_user_agent'] ) ) {
                    $user_data->setClientUserAgent( $saved_user_data['client_user_agent'] );
                    
                }

                
                if ( ! empty( $saved_user_data['fbp'] ) ) {
                    $user_data->setFbp( $saved_user_data['fbp'] );
                    
                }

                
                if ( ! empty( $saved_user_data['fbc'] ) ) {
                    $user_data->setFbc( $saved_user_data['fbc'] );
                    
                }

                
                $event->setUserData( $user_data );
            }

            $request = ( new EventRequest( $pixel_id ) )->setEvents( [ $event ] );
            $request->setPartnerAgent( "dvorderflow" );
            if ( ! empty( $test_code[ $pixel_id ] ) ) {
                $request->setTestEventCode( $test_code[ $pixel_id ] );
            }

            try {
                $response = $request->execute();
                $this->save_purchase_event_results_for_order( $pixel_id, $event, $response, $order_id );
            } catch ( \Exception $e ) {
            }
        }
    }


}



function PurchaseEventManager() {
    return PurchaseEventManager::instance();
}


PurchaseEventManager();

