<?php

namespace fmb_engine\Facebook;

use fmb_engine\Facebook\EventTypes;
use fmb_engine\Facebook\SingleEvent;
use fmb_engine\Facebook\EventIdGenerator;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Api;
use FacebookAds\Http\Exception\RequestException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}



class Custom_Order_Status_Event_Manager {

	private static $_instance;

	

	private $status_event_map = [
		'ads-recovered'  => 'Recovered',
		'ads-confirmed'  => 'Confirmed',
		'ads-shipping'   => 'Shipping',
		'ads-returned'   => 'Returned',
		'ads-delivered'  => 'Delivered',
		 
	];

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		 
		add_action( 'woocommerce_order_status_changed', array( $this, 'send_custom_status_event' ), 20, 3 );
	}

	

	public function send_custom_status_event( $order_id, $old_status, $new_status ) {
		error_log("FMB Engine Debug: send_custom_status_event triggered - Order ID: {$order_id}, Old Status: {$old_status}, New Status: {$new_status}");

		 
		$new_status = str_replace( 'wc-', '', $new_status );
		
		 
		if ( ! isset( $this->status_event_map[ $new_status ] ) ) {
			error_log("FMB Engine Debug: Exit - Status '{$new_status}' not found in status_event_map. Order ID: {$order_id}");
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			error_log("FMB Engine Debug: Exit - Could not get WooCommerce order object for Order ID: {$order_id}");
			return;
		}

		 
		if ( ! fmb_engine_Facebook()->configured() ) {
			error_log("FMB Engine Debug: Exit - Facebook is not configured. Order ID: {$order_id}");
			return;
		}

		 
		if ( ! fmb_engine_Facebook()->isServerApiEnabled() ) {
			error_log("FMB Engine Debug: Exit - Facebook Server API is not enabled. Order ID: {$order_id}");
			return;
		}

		 
		$event_name = $this->status_event_map[ $new_status ];
		error_log("FMB Engine Debug: Proceeding to generate custom event '{$event_name}' for Order ID: {$order_id}");
		
		 
		$this->generate_and_send_custom_event( $order, $event_name );
	}

	

	private function generate_and_send_custom_event( $order, $event_name ) {
		
		 
		$purchase_event_data = null;
		if ( isWooCommerceVersionGte( '3.0.0' ) ) {
			$purchase_event_data = $order->get_meta( '_ads_purchase_event_data', true );
		} else {
			$purchase_event_data = get_post_meta( $order->get_id(), '_ads_purchase_event_data', true );
		}

		 
		if ( ! $purchase_event_data || empty( $purchase_event_data ) ) {
			error_log("FMB Engine Debug: Exit - No '_ads_purchase_event_data' found for Order ID: " . $order->get_id() . " Event: " . $event_name);
			return;
		}

		
		 
		if ( ! isset( $purchase_event_data['server_event_data'] ) || empty( $purchase_event_data['server_event_data'] ) ) {
			error_log("FMB Engine Debug: Exit - No 'server_event_data' found in purchase meta for Order ID: " . $order->get_id());
			return;
		}

		 
		$pixel_ids = isset( $purchase_event_data['pixel_ids'] ) ? $purchase_event_data['pixel_ids'] : array();

		if ( empty( $pixel_ids ) ) {
			error_log("FMB Engine Debug: Exit - No 'pixel_ids' found in purchase meta for Order ID: " . $order->get_id());
			return;
		}

		 
		$server_event = $this->recreate_server_event_for_custom_event( $purchase_event_data['server_event_data'], $event_name, $order->get_id() );

		if ( ! $server_event ) {
			error_log("FMB Engine Debug: Exit - Failed to recreate Server Event object for event '{$event_name}'. Order ID: " . $order->get_id());
			return;
		}

		 
		$saved_user_data = isset( $purchase_event_data['server_event_data']['user_data'] ) ? $purchase_event_data['server_event_data']['user_data'] : null;

		 
		if ( fmb_engine_Facebook()->isServerApiEnabled() ) {
			error_log("FMB Engine Debug: Initiating send_event_directly_to_api for event '{$event_name}'. Order ID: " . $order->get_id());
			$this->send_event_directly_to_api( $pixel_ids, $server_event, $saved_user_data, $order->get_id() );
			
		} else {
			error_log("FMB Engine Debug: Warning - Server API is not enabled right before sending data. Order ID: " . $order->get_id());
		}
	}

	

	private function recreate_server_event_for_custom_event( $server_event_data, $custom_event_name, $order_id ) {
		if ( empty( $server_event_data ) ) {
			return null;
		}

		 
		$event = new \FacebookAds\Object\ServerSide\Event();

		 
		$event->setEventName( $custom_event_name );
		$event->setEventTime( time() );
		$event->setEventId( EventIdGenerator::guidv4() ); 
		
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
			if ( isset( $cd['order_id'] ) ) {
				$custom_data->setOrderId( $cd['order_id'] );
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
                if ( isset( $cd['custom_properties']['order_id'] ) ) {
                    unset( $cd['custom_properties']['order_id'] );
                }
                if ( ! empty( $cd['custom_properties'] ) ) {
				    $custom_data->setCustomProperties( $cd['custom_properties'] );
                }
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

	

	private function send_event_directly_to_api( $pixel_ids, $event, $saved_user_data = null, $order_id = null ) {
		if ( ! $event || apply_filters( 'orderflow_disable_server_event_filter', false ) ) {
			error_log("FMB Engine Debug: Exit - Event is empty or 'orderflow_disable_server_event_filter' is true. Order ID: {$order_id}");
			return;
		}

		 
		$access_token = fmb_engine_Facebook()->getApiToken();
		$test_code = fmb_engine_Facebook()->getApiTestCode();

		foreach ( $pixel_ids as $pixel_id ) {
			if ( empty( $access_token[ $pixel_id ] ) ) {
				error_log("FMB Engine Debug: Skip - No access token for Pixel ID: {$pixel_id}. Order ID: {$order_id}");
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
				error_log("FMB Engine Debug: Test event code set for Pixel {$pixel_id}: " . $test_code[ $pixel_id ]);
			}

			try {
				error_log("FMB Engine Debug: Executing Facebook API Request for event '{$event->getEventName()}' (Pixel: {$pixel_id}). Order ID: {$order_id}");
				$response = $request->execute();
				error_log("FMB Engine Debug: API Response Success for event '{$event->getEventName()}'. Order ID: {$order_id}");
			} catch ( \Exception $e ) {
				error_log("FMB Engine Debug: Execution Exception for event '{$event->getEventName()}' - Error: " . $e->getMessage() . " Order ID: {$order_id}");
			}
		}
	}
}



function CustomOrderStatusEventManager() {
	return Custom_Order_Status_Event_Manager::instance();
}


CustomOrderStatusEventManager();

