<?php
namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}




require_once __DIR__ . '/FacebookCapiEventHelper.php';

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\EventRequest;

class FacebookServer {

	private static $_instance;

	private $isEnabled;

	private $access_token;

	private $testCode;

	private $isDebug;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		$this->isEnabled = function_exists( __NAMESPACE__ . '\fmb_engine_Facebook' ) && fmb_engine_Facebook()->enabled() && fmb_engine_Facebook()->isServerApiEnabled();
		$this->isDebug   = function_exists( __NAMESPACE__ . '\FacebookRuntime' ) ? FacebookRuntime()->getOption( 'debug_enabled' ) : false;

		if ( ! $this->isEnabled ) {
			return;
		}

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'saveFbTagsInOrder' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'saveFbTagsInOrder' ), 10, 1 );
		add_action( 'wp_ajax_orderflow_api_event', array( $this, 'catchAjaxEvent' ) );
		add_action( 'wp_ajax_nopriv_orderflow_api_event', array( $this, 'catchAjaxEvent' ) );
	}

	

	public function sendEventsNow( $events, $order_id = null ) {
		foreach ( $events as $event ) {
			$ids = $event->payload['pixelIds'];

			$server_event = FacebookCapiEventHelper::mapEventToServerEvent( $event );

			if ( ! $server_event ) {
				continue;
			}

			if ( empty( $order_id ) && isset( $event->payload['woo_order'] ) ) {
				$order_id = $event->payload['woo_order'];
			}

			$this->sendEvent( $ids, $server_event, $order_id );
		}
	}

	

	public function catchAjaxEvent() {
		if ( empty( $_REQUEST['ajax_event'] ) || ! wp_verify_nonce( $_REQUEST['ajax_event'], 'ajax-event-nonce' ) ) {
			wp_die();
		}

		if ( empty( $_POST['event'] ) || ! isset( $_POST['ids'], $_POST['eventID'] ) ) {
			wp_die();
		}

		$data = isset( $_POST['data'] ) ? $_POST['data'] : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$data = $this->mergePageTitleForAjax( $data );

		$single = $this->dataToSingleEvent(
			$_POST['event'],
			$data,
			$_POST['eventID'],
			$_POST['ids'],
			isset( $_POST['woo_order'] ) ? $_POST['woo_order'] : null
		);

		$this->sendEventsNow( array( $single ) );
		wp_die();
	}

	

	private function dataToSingleEvent( $eventName, $params, $eventID, $ids, $wooOrder ) {
		$single = new SingleEvent( '', '' );
		$single->addParams( $params );
		$single->addPayload(
			array(
				'name'      => $eventName,
				'eventID'   => $eventID,
				'woo_order' => $wooOrder,
				'pixelIds'  => $ids,
			)
		);
		return $single;
	}

	

	public function sendEvent( $pixel_Ids, $event, $order_id = null ) {
		if ( apply_filters( 'orderflow_disable_server_event_filter', false ) ) {
			return;
		}

		if ( ! $event ) {
			return;
		}

		$this->ensureApiCredentials();

		foreach ( $pixel_Ids as $pixel_Id ) {
			if ( empty( $this->access_token[ $pixel_Id ] ) ) {
				continue;
			}

			$event->setEventId( $event->getEventId() );

			$api = Api::init( null, null, $this->access_token[ $pixel_Id ], false );
			$this->applyConnectTimeout( $api, 30 );

			$event = apply_filters( 'orderflow_before_send_fb_server_event', $event, $pixel_Id, $event->getEventId() );

			$request = ( new EventRequest( $pixel_Id ) )->setEvents( array( $event ) );
			$request->setPartnerAgent( 'dvorderflow' );

			if ( ! empty( $this->testCode[ $pixel_Id ] ) ) {
				$request->setTestEventCode( $this->testCode[ $pixel_Id ] );
			}

			try {
				$response = $request->execute();
				$current_count = (int) get_option('of_fb_capi_success_count', 0);
				update_option('of_fb_capi_success_count', $current_count + 1, false);
				$this->save_purchase_event_results( $event, $pixel_Id, $response, $order_id );
			} catch ( \Exception $e ) {
			}
		}
	}

	public function saveFbTagsInOrder( $order_param ) {
		$orderflow_fb_cookie_data = array(
			'fbc' => FacebookCapiEventHelper::getFbc(),
			'fbp' => FacebookCapiEventHelper::getFbp(),
		);
		$external_id = $this->get_order_external_id_for_meta();

		if ( isWooCommerceVersionGte( '3.0.0' ) ) {
			$order = wc_get_order( $order_param );
			if ( empty( $order ) ) {
				return;
			}
			$order->update_meta_data( 'orderflow_fb_cookie', $orderflow_fb_cookie_data );
			if ( $external_id !== '' ) {
				$order->update_meta_data( 'external_id', $external_id );
			}
			$order->save();
			return;
		}

		if ( empty( $order_param ) ) {
			return;
		}

		update_post_meta( $order_param, 'orderflow_fb_cookie', $orderflow_fb_cookie_data );
		if ( $external_id !== '' ) {
			update_post_meta( $order_param, 'external_id', $external_id );
		}
	}

	private function get_order_external_id_for_meta() {
		if ( ! EventsManager::isTrackExternalId() ) {
			return '';
		}
		if ( ! empty( $_COOKIE['orderflow_id'] ) ) {
			return sanitize_text_field( $_COOKIE['orderflow_id'] );
		}
		$pbid = FacebookRuntime()->get_of_id();
		return $pbid ? $pbid : '';
	}

	private function mergePageTitleForAjax( array $data ) {
		if ( ! empty( $data['page_title'] ) ) {
			return $data;
		}
		if ( ! empty( $_POST['page_title'] ) ) {
			$data['page_title'] = sanitize_text_field( wp_unslash( $_POST['page_title'] ) );
			return $data;
		}
		if ( ! FacebookRuntime()->getOption( 'enable_page_title_param' ) ) {
			return $data;
		}
		$standard = getStandardParams();
		if ( ! empty( $standard['page_title'] ) ) {
			$data['page_title'] = $standard['page_title'];
		}
		return $data;
	}

	private function ensureApiCredentials() {
		if ( $this->access_token ) {
			return;
		}
		$this->access_token = fmb_engine_Facebook()->getApiToken();
		$this->testCode     = fmb_engine_Facebook()->getApiTestCode();
	}

	private function applyConnectTimeout( Api $api, $seconds ) {
		$opts = $api->getHttpClient()->getAdapter()->getOpts();
		if ( $opts instanceof \ArrayObject && $opts->offsetExists( CURLOPT_CONNECTTIMEOUT ) ) {
			$opts->offsetSet( CURLOPT_CONNECTTIMEOUT, $seconds );
			$api->getHttpClient()->getAdapter()->setOpts( $opts );
		}
	}

	

	private function save_purchase_event_results( $event, $pixel_Id, $response, $order_id = null ) {
		if ( $event->getEventName() !== 'Purchase' ) {
			return;
		}

		$order_id = $this->resolvePurchaseOrderId( $event, $order_id );
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$results = $order->get_meta( 'ads_purchase_event_results', true );
		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$idx = $this->findPurchaseResultIndex( $results, $pixel_Id );
		$status = $response ? 'success' : 'failed';
		$now    = time();

		if ( $idx >= 0 ) {
			$results[ $idx ]['sent_count'] = isset( $results[ $idx ]['sent_count'] ) ? $results[ $idx ]['sent_count'] + 1 : 1;
			$results[ $idx ]['sent_at']    = $now;
			$results[ $idx ]['response']   = $status;
		} else {
			$results[] = array(
				'event_name' => 'Purchase',
				'pixel_id'   => $pixel_Id,
				'event_id'   => $event->getEventId(),
				'sent_at'    => $now,
				'response'   => $status,
				'sent_count' => 1,
			);
		}

		if ( isWooCommerceVersionGte( '3.0.0' ) ) {
			$order->update_meta_data( 'ads_purchase_event_results', $results );
			$order->save();
			return;
		}
		update_post_meta( $order_id, 'ads_purchase_event_results', $results );
	}

	private function resolvePurchaseOrderId( $event, $order_id ) {
		if ( ! empty( $order_id ) ) {
			return $order_id;
		}
		$custom = $event->getCustomData();
		if ( ! $custom || ! method_exists( $custom, 'getCustomProperties' ) ) {
			return null;
		}
		$props = $custom->getCustomProperties();
		return ( $props && isset( $props['woo_order'] ) ) ? $props['woo_order'] : null;
	}

	private function findPurchaseResultIndex( array $results, $pixel_Id ) {
		foreach ( $results as $i => $row ) {
			if ( isset( $row['pixel_id'] ) && $row['pixel_id'] === $pixel_Id ) {
				return $i;
			}
		}
		return -1;
	}
}



function FacebookServer() {
	return FacebookServer::instance();
}

FacebookServer();
