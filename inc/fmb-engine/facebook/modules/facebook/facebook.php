<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


require_once __DIR__ . '/function-helpers.php';

use fmb_engine\Settings;
use fmb_engine\Facebook\Helpers;
use fmb_engine\Facebook\EventIdGenerator;

class Facebook extends Settings {

	private static $_instance;

	private $configured;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		parent::__construct( 'orderflow_facebook' );

		 
		$this->locateOptionsFromArrays(
			array(
				'orderflow_enabled'                           => 'checkbox',
				'orderflow_use_server_api'                    => 'checkbox',
				'orderflow_advanced_matching_enabled'         => 'checkbox',
				'orderflow_pixel_id'                          => 'array',
				'orderflow_server_access_api_token'           => 'array',
				'orderflow_test_api_event_code'               => 'array',
				'orderflow_test_api_event_code_expiration_at' => 'array',
				'verify_meta_tag'                      => 'array_textarea',
				'woo_purchase_immediately_send'        => 'checkbox',
				'woo_purchase_courier_ratio_threshold' => 'number',
			),
			array(
				'orderflow_enabled'                           => true,
				'orderflow_use_server_api'                    => false,
				'orderflow_advanced_matching_enabled'         => false,
				'orderflow_pixel_id'                          => '',
				'orderflow_server_access_api_token'         => '',
				'orderflow_test_api_event_code'               => '',
				'orderflow_test_api_event_code_expiration_at' => '',
				'verify_meta_tag'                      => '',
				'woo_purchase_immediately_send'        => false,
				'woo_purchase_courier_ratio_threshold' => 0,
				 
				'woo_wpml_unified_id'                => false,
				'woo_content_id'                     => 'product_id',
				'woo_content_id_prefix'              => '',
				'woo_content_id_suffix'              => '',
				'woo_content_id_logic'               => 'default',
				'woo_variable_as_simple'             => false,
				 
				'woo_order_purchase_disabled_status' => array(),
			)
		);

		add_filter( 'orderflow_facebook_settings_sanitize_verify_meta_tag_field', array( $this, 'sanitize_verify_meta_tag_field' ) );
		add_action( 'wp_head', array( $this, 'output_meta_tag' ) );
	}

	protected function normalizeLoadedValues() {
		$map = array(
			'of_enabled'                           => 'orderflow_enabled',
			'of_use_server_api'                    => 'orderflow_use_server_api',
			'of_advanced_matching_enabled'         => 'orderflow_advanced_matching_enabled',
			'of_pixel_id'                          => 'orderflow_pixel_id',
			'of_server_access_api_token'           => 'orderflow_server_access_api_token',
			'of_test_api_event_code'               => 'orderflow_test_api_event_code',
			'of_test_api_event_code_expiration_at' => 'orderflow_test_api_event_code_expiration_at',
		);
		foreach ( $map as $old => $new ) {
			if ( array_key_exists( $old, $this->values ) ) {
				if ( ! array_key_exists( $new, $this->values ) ) {
					$this->values[ $new ] = $this->values[ $old ];
				}
				unset( $this->values[ $old ] );
			}
		}
	}

	public function sanitize_number_field( $value ) {
		if ( '' === $value || null === $value ) {
			return 0;
		}
		return is_numeric( $value ) ? $value + 0 : 0;
	}

	public function enabled() {
		return (bool) $this->getOption( 'orderflow_enabled' );
	}

	public function configured() {
		if ( $this->configured !== null ) {
			return $this->configured;
		}
		$ids = $this->getPixelIDs();
		$this->configured = $this->enabled() && is_array( $ids ) && count( $ids ) > 0;
		return $this->configured;
	}

	public function getPixelIDs() {
		$ids = (array) $this->getOption( 'orderflow_pixel_id' );
		$valid_ids = array();
		foreach ($ids as $id) {
			if ($id !== '' && $id !== null) {
				$valid_ids[] = $id;
			}
		}
		return apply_filters( 'orderflow_facebook_ids', $valid_ids );
	}

	public function getPixelOptions() {
		return array(
			'pixelIds'                   => $this->getPixelIDs(),
			'advancedMatching'           => $this->getOption( 'orderflow_advanced_matching_enabled' ) ? Helpers\getAdvancedMatchingParams() : array(),
			'advancedMatchingEnabled'    => $this->getOption( 'orderflow_advanced_matching_enabled' ),
			'removeMetadata'             => true,
			'wooVariableAsSimple'        => false,
			'serverApiEnabled'           => $this->isServerApiEnabled() && count( $this->getApiToken() ) > 0,
			'send_external_id'           => false,
			'enabled_medical'            => false,
			'do_not_track_medical_param' => array(),
			'meta_ldu'                   => $this->getLDUMode(),
		);
	}

	public function updateOptions( $values = null ) {
		$slug = $this->getSlug();
		if ( isset( $_POST['fmb-engine'][ $slug ]['orderflow_test_api_event_code'] ) ) {
			$_POST['fmb-engine'][ $slug ]['orderflow_test_api_event_code_expiration_at'] = $this->buildTestCodeExpirationArray(
				(array) $_POST['fmb-engine'][ $slug ]['orderflow_test_api_event_code']
			);
		}
		parent::updateOptions( $values );
	}

	

	public function generateEvents( $event ) {
		if ( ! $this->configured() ) {
			return array();
		}
		$pixel_ids = $this->getPixelIDs();
		if ( empty( $pixel_ids ) ) {
			return array();
		}

		$pixel_event = clone $event;
		if ( ! $this->addParamsToEvent( $pixel_event ) ) {
			return array();
		}

		$pixel_event->addPayload(
			array(
				'pixelIds' => $pixel_ids,
				'eventID'  => EventIdGenerator::guidv4(),
			)
		);

		return array( $pixel_event );
	}

	private function applyEventData( array $eventData, &$event ) {
		$params = $eventData['data'];
		unset( $eventData['data'] );
		$event->addParams( $params );
		$event->addPayload( $eventData );
	}

	

	public function addParamsToEvent( &$event ) {
		switch ( $event->getId() ) {

			case 'init_event':
				$event->addPayload( array( 'ajaxFire' => false ) );
				$this->applyEventData( $this->pageViewPayload(), $event );
				return true;

			case 'woo_view_content':
				$payload = $this->viewContentPayload();
				if ( ! $payload ) {
					return false;
				}
				$this->applyEventData( $payload, $event );
				return true;

			case 'woo_add_to_cart_on_cart_page':
				if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! $this->wooCartHasLineItems() ) {
					return false;
				}
				$this->applyEventData(
					array(
						'name' => 'AddToCart',
						'data' => Helpers\getWooCartParamsForCartAddToCart(),
					),
					$event
				);
				return true;

			case 'woo_add_to_cart_on_checkout_page':
				if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() || ! $this->wooCartHasLineItems() ) {
					return false;
				}
				$this->applyEventData(
					array(
						'name' => 'AddToCart',
						'data' => Helpers\getWooCartParamsForCartAddToCart(),
					),
					$event
				);
				return true;

			case 'woo_initiate_checkout':
				if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
					return false;
				}
				$this->applyEventData(
					array(
						'name' => 'InitiateCheckout',
						'data' => Helpers\getWooCartParamsForInitiateCheckout(),
					),
					$event
				);
				return true;

			case 'woo_purchase':
				if ( ! $this->shouldRegisterWooPurchase() ) {
					return false;
				}
				$payload = $this->getWooPurchaseEventParams();
				if ( ! $payload ) {
					return false;
				}
				$this->markWooPurchaseEventFiredFromRequest();
				$this->applyEventData( $payload, $event );
				return true;

			case 'woo_add_to_cart_on_button_click':
				if ( ! empty( $event->args['productId'] ) ) {
					$extra = $this->addToCartButtonPayload( $event->args );
					if ( $extra && isset( $extra['params'] ) ) {
						$event->addParams( $extra['params'] );
						unset( $extra['params'] );
						if ( $extra ) {
							$event->addPayload( $extra );
						}
					}
				}
				$event->addPayload( array( 'name' => 'AddToCart' ) );
				return true;

			default:
				return false;
		}
	}

	public function getEventData( $eventType, $args = null ) {
		return false;
	}

	public function outputNoScriptEvents() {
		if ( ! $this->configured() ) {
			return;
		}

		$ldu    = $this->getLDUMode();
		$static = FacebookRuntime()->getEventsManager()->getStaticEvents( 'orderflow_facebook' );

		foreach ( $static as $events ) {
			foreach ( $events as $ev ) {
				foreach ( $this->getPixelIDs() as $pixel_id ) {
					echo $this->noscriptPixelImg( $ev, $pixel_id, $ldu ) . "\r\n";
				}
			}
		}
	}

	private function noscriptPixelImg( array $ev, $pixel_id, $ldu ) {
		$args = array(
			'id'       => $pixel_id,
			'ev'       => urlencode( $ev['name'] ),
			'noscript' => 1,
		);
		$params = $ev['params'];
		if ( $ldu ) {
			$params = array_merge( $params, array( 'vdpo' => 'LDU', 'dpoco' => 0, 'dpost' => 0 ) );
		}
		foreach ( $params as $k => $v ) {
			$args[ 'cd[' . $k . ']' ] = urlencode( is_array( $v ) ? json_encode( $v ) : $v );
		}
		$src = add_query_arg( $args, 'https://www.facebook.com/tr' );
		$src = str_replace( array( '[', ']' ), array( '%5B', '%5D' ), $src );
		return sprintf(
			'<noscript><img height="1" width="1" style="display: none;" src="%s" alt=""></noscript>',
			$src
		);
	}

	private function pageViewPayload() {
		return array(
			'name' => 'PageView',
			'data' => array(),
		);
	}

	private function viewContentPayload() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		global $post;
		if ( empty( $post->ID ) ) {
			return false;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return false;
		}

		$content_ids = Helpers\getFacebookWooProductContentId( $post->ID );
		$variable    = wooProductIsType( $product, 'variable' );
		 
		$content_type = $variable ? 'product_group' : 'product';

		$rt           = FacebookRuntime();
		$value_option = $rt->getOption( 'woo_view_content_value_option' );
		$global_value = $rt->getOption( 'woo_view_content_value_global', 0 );

		$params = array(
			'content_ids'   => $content_ids,
			'content_type'  => $content_type,
			'content_name'  => $product->get_name(),
			'value'         => getWooEventValue( $value_option, $global_value, 100, $post->ID, 1 ),
			'currency'      => strtoupper( get_woocommerce_currency() ),
			'product_price' => getWooProductPriceToDisplay( $post->ID ),
		);

		if ( Helpers\isDefaultWooContentIdLogic() ) {
			$params['contents'] = array(
				array(
					'id'       => (string) reset( $content_ids ),
					'quantity' => 1,
				),
			);
		}

		return array(
			'name'  => 'ViewContent',
			'data'  => $params,
			'delay' => (int) $rt->getOption( 'woo_view_content_delay' ),
		);
	}

	private function addToCartButtonPayload( $args ) {
		$product_id = $args['productId'];
		$quantity   = $args['quantity'];
		$out        = array( 'params' => Helpers\getWooSingleAddToCartParams( $product_id, $quantity ) );

		$product = wc_get_product( $product_id );
		if ( ! $product || $product->get_type() !== 'grouped' ) {
			return $out;
		}

		$grouped = array();
		foreach ( $product->get_children() as $child_id ) {
			$cid                  = Helpers\getFacebookWooProductContentId( $child_id );
			$grouped[ $child_id ] = array(
				'content_id' => (string) reset( $cid ),
				'price'      => getWooProductPriceToDisplay( $child_id ),
			);
		}
		$out['grouped'] = $grouped;
		return $out;
	}

	public function getWooPurchaseEventParams() {
		$key      = isset( $_REQUEST['key'] ) ? sanitize_key( wp_unslash( $_REQUEST['key'] ) ) : '';
		$order_id = orderflow_fb_resolve_order_id_from_order_key( $key );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return array(
			'name'      => 'Purchase',
			'data'      => $this->purchaseDataFromOrder( $order, $order_id ),
			'woo_order' => $order_id,
		);
	}

	private function wooCartHasLineItems(): bool {
		return function_exists( 'WC' ) && WC()->cart && count( WC()->cart->get_cart() ) > 0;
	}

	private function shouldRegisterWooPurchase(): bool {
		if ( ! FacebookRuntime()->woo_is_order_received_page()
			|| empty( $_REQUEST['key'] )
			|| ! empty( $_REQUEST['wc-api'] )
		) {
			return false;
		}

		$order_key = sanitize_key( wp_unslash( $_REQUEST['key'] ) );
		$order_id  = orderflow_fb_resolve_order_id_from_order_key( $order_key );
		$order     = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$status           = 'wc-' . $order->get_status( 'edit' );
		$disabledStatuses = (array) $this->getOption( 'woo_order_purchase_disabled_status' );
		if ( in_array( $status, $disabledStatuses, true ) ) {
			return false;
		}

		if ( ! $this->getOption( 'woo_purchase_immediately_send' ) ) {
			return false;
		}

		$threshold = $this->getOption( 'woo_purchase_courier_ratio_threshold' );
		$threshold = $threshold ? (float) $threshold : 0;
		if ( $threshold == 0 ) {
			return true;
		}

		$phone = $order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			$courier_checker = new class() {
				use \fmb_engine\Traits\Courier;
			};
			$courier_ratio = $courier_checker->get_courier_rate_by_phone( $phone );
			if ( $courier_ratio < $threshold ) {
				return false;
			}
		}

		return true;
	}

	private function markWooPurchaseEventFiredFromRequest(): void {
		$order_key = isset( $_REQUEST['key'] ) ? sanitize_key( wp_unslash( $_REQUEST['key'] ) ) : '';
		$order_id  = orderflow_fb_resolve_order_id_from_order_key( $order_key );
		$order     = wc_get_order( $order_id );

		if ( isWooCommerceVersionGte( '3.0.0' ) ) {
			if ( $order ) {
				$order->update_meta_data( '_orderflow_purchase_event_fired', true );
				$order->save();
			}
		} elseif ( $order_id ) {
			update_post_meta( $order_id, '_orderflow_purchase_event_fired', true );
		}
	}

	private function purchaseDataFromOrder( $order, $order_id ) {
		$content_ids = array();
		$contents    = array();
		$names       = array();
		$num_items   = 0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$pid         = Helpers\getFacebookWooCartItemId( $item );
			$cid_arr     = Helpers\getFacebookWooProductContentId( $pid );
			$content_ids = array_merge( $content_ids, $cid_arr );
			$num_items  += $item['qty'];

			$p       = wc_get_product( $pid );
			$names[] = $p ? $p->get_name() : '';

			$line_pid = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$contents[] = array(
				'id'         => (string) reset( $cid_arr ),
				'quantity'   => $item['qty'],
				'item_price' => getWooProductPriceToDisplay( $line_pid ),
			);
		}

		$rt = FacebookRuntime();

		$params = array(
			'content_type' => 'product',
			'content_ids'  => $content_ids,
			'content_name' => implode( ', ', array_filter( $names ) ),
			'num_items'    => $num_items,
			'value'        => getWooEventValueOrder( $rt->getOption( 'woo_purchase_value_option' ), $order, $rt->getOption( 'woo_purchase_value_global', 0 ) ),
			'currency'     => strtoupper( get_woocommerce_currency() ),
			'order_id'     => $order_id,
		);

		if ( Helpers\isDefaultWooContentIdLogic() ) {
			$params['contents'] = $contents;
		}

		return $params;
	}

	public function getApiToken() {
		$pixelids  = (array) $this->getOption( 'orderflow_pixel_id' );
		$serverids = (array) $this->getOption( 'orderflow_server_access_api_token' );
		$tokens = array();
		foreach ($pixelids as $index => $pixel_id) {
			if ($pixel_id !== '' && $pixel_id !== null && isset($serverids[$index])) {
				$tokens[$pixel_id] = $serverids[$index];
			}
		}
		return $tokens;
	}

	public function getApiTestCode() {
		$pixelids = (array) $this->getOption( 'orderflow_pixel_id' );
		$codes    = (array) $this->getOption( 'orderflow_test_api_event_code' );
		$test_codes = array();
		foreach ($pixelids as $index => $pixel_id) {
			if ($pixel_id !== '' && $pixel_id !== null && isset($codes[$index])) {
				$test_codes[$pixel_id] = $codes[$index];
			}
		}
		return $test_codes;
	}

	public function isServerApiEnabled() {
		return (bool) $this->getOption( 'orderflow_use_server_api' );
	}

	public function output_meta_tag() {
		foreach ( (array) $this->getOption( 'verify_meta_tag' ) as $tag ) {
			echo $tag;
		}
	}

	public function sanitize_verify_meta_tag_field( $values ) {
		$values    = is_array( $values ) ? $values : array();
		$sanitized = array();
		$allowed   = array( 'meta' => array( 'name' => array(), 'content' => array() ) );

		foreach ( $values as $key => $value ) {
			$value     = wp_kses( $value, $allowed );
			$new_value = $this->sanitize_textarea_field( $value );
			if ( $new_value === '' || in_array( $new_value, $sanitized, true ) ) {
				continue;
			}
			$sanitized[ $key ] = $new_value;
		}
		return $sanitized;
	}

	public function getLDUMode() {
		return apply_filters( 'orderflow_meta_ldu_mode', false );
	}

	public function getMainTagId() {
		$id = $this->getPixelIDs();
		return ( is_array( $id ) && isset( $id[0] ) ) ? (string) $id[0] : '';
	}

	public function checkHidePixel() {
		$tag = $this->getMainTagId();
		if ( $tag === '' ) {
			return;
		}

		$list = apply_filters( 'hide_pixels', array() );
		if ( isset( $_COOKIE[ 'hide_tag_' . $tag ] ) ) {
			$list[] = $tag;
		}

		if ( ! $list ) {
			return;
		}

		add_filter(
			'hide_pixels',
			function () use ( $list ) {
				return array_unique( $list );
			}
		);
	}

	private function buildTestCodeExpirationArray( array $posted_codes ) {
		$out        = array();
		$stored_exp = (array) $this->getOption( 'orderflow_test_api_event_code_expiration_at' );
		foreach ( $posted_codes as $key => $test_api ) {
			if ( ! empty( $test_api ) && empty( $stored_exp[ $key ] ) ) {
				$out[] = time() + $this->convertTimeToSeconds();
			} elseif ( ! empty( $stored_exp[ $key ] ) ) {
				$out[] = $stored_exp[ $key ];
			}
		}
		return $out;
	}
}



function fmb_engine_Facebook() {
	return Facebook::instance();
}