<?php

namespace fmb_engine\Facebook;

use fmb_engine\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


final class FacebookRuntime extends Settings {

	private static $_instance;

	 
	private $eventsManager;

	private $registeredPixels = array();

	private $pixels_loaded = false;

	private $externalId;

	/** @var string|null Cached client IP for this request. */
	private $resolved_user_ip = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		parent::__construct( 'core' );

		add_action( 'wp', array( $this, 'controllSessionStart' ), 10 );
		add_action( 'init', array( $this, 'set_pbid' ), 8 );
		add_action( 'init', array( $this, 'init' ), 9 );

		add_action( 'template_redirect', array( $this, 'managePixels' ), 1 );
		add_action( 'wp_ajax_orderflow_get_id', array( $this, 'get_of_id_ajax' ) );
		add_action( 'wp_ajax_nopriv_orderflow_get_id', array( $this, 'get_of_id_ajax' ) );
	}

	public function init() {
		 
		$this->registeredPixels['orderflow_facebook'] = Facebook::instance();

		if ( function_exists( 'isWooCommerceActive' ) && isWooCommerceActive()
			&& isset( $this->registeredPixels['orderflow_facebook'] )
			&& fmb_engine_Facebook()->configured() ) {
			add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', '__return_false' );
		}

		$this->clear_expired_facebook_test_codes();
	}

	public function get_of_id_ajax() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$isTrackExternalId = EventsManager::isTrackExternalId();
			if ( $isTrackExternalId && ! empty( $this->externalId ) ) {
				$ip        = $this->get_user_ip();
				$transient = get_transient( 'orderflow_id-' . $ip );
				wp_send_json_success( array(
					'orderflow_id'      => $this->externalId,
					'transient' => ! empty( $transient ) ? $transient : false,
				) );
			}
		}
	}

	/**
	 * Starts a native PHP session for traffic-source / landing / UTM cookies when enabled.
	 * On some hosts sessions add latency or locking; disable via the session_disable option if pages feel slow.
	 */
	public function controllSessionStart() {
		if ( $this->getOption( 'session_disable' ) ) {
			return;
		}

		if ( ! is_admin() && PHP_SAPI !== 'cli' && session_status() != PHP_SESSION_DISABLED ) {
			if ( ! headers_sent() && session_status() === PHP_SESSION_NONE ) {
				if ( ! session_start() ) {
					return;
				}
			}

			if ( session_status() !== PHP_SESSION_ACTIVE ) {
				return;
			}

			if ( empty( $_SESSION['TrafficSource'] ) ) {
				$_SESSION['TrafficSource'] = function_exists( __NAMESPACE__ . '\getTrafficSource' ) ? \fmb_engine\Facebook\getTrafficSource() : ( function_exists( '\getTrafficSource' ) ? \getTrafficSource() : '' );
			}
			if ( empty( $_SESSION['LandingPage'] ) ) {
				$protocol   = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
				$currentUrl = $protocol . ( $_SERVER['HTTP_HOST'] ?? parse_url( get_site_url(), PHP_URL_HOST ) ) . ( $_SERVER['REQUEST_URI'] ?? '' );
				$landing    = explode( '?', $currentUrl )[0];
				$_SESSION['LandingPage'] = $landing;
			}
			if ( empty( $_SESSION['TrafficUtms'] ) ) {
				$_SESSION['TrafficUtms'] = function_exists( __NAMESPACE__ . '\getUtms' ) ? \fmb_engine\Facebook\getUtms() : ( function_exists( '\getUtms' ) ? \getUtms() : array() );
			}
		}
	}

	public function getRegisteredPixels() {
		return $this->registeredPixels;
	}

	public function managePixels() {
		if ( $this->pixels_loaded ) {
			return;
		}
		$this->pixels_loaded = true;

		if ( $this->should_skip_frontend_pixels() ) {
			return;
		}

		$this->eventsManager = new EventsManager();
		Custom_Events_Manager::instance();

		if ( ! fmb_engine_Facebook()->configured() && ! $this->getOption( 'hide_version_plugin_in_console' ) ) {
			add_action(
				'wp_head',
				static function () {
					echo "<script id='fmb-engine-fb-config-warning-script'>console.warn('FMB Engine Facebook Pixel: no pixel configured.');</script>\n";
				}
			);
		}
	}

	 
	private function should_skip_frontend_pixels(): bool {
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| is_admin()
			|| is_customize_preview()
			|| is_preview()
		) {
			return true;
		}
		if ( did_action( 'elementor/preview/init' ) || did_action( 'elementor/editor/init' ) ) {
			return true;
		}
		if ( isset( $_GET['action'] ) && 'piotnetforms' === $_GET['action'] ) {
			return true;
		}
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return true;
		}
		return isDisabledForCurrentRole();
	}

	private function clear_expired_facebook_test_codes(): void {
		$expires = fmb_engine_Facebook()->getOption( 'orderflow_test_api_event_code_expiration_at' );
		if ( empty( $expires ) || ! is_array( $expires ) ) {
			return;
		}
		$now = time();
		foreach ( $expires as $at ) {
			if ( $now >= (int) $at ) {
				fmb_engine_Facebook()->updateOptions( array( 'orderflow_test_api_event_code' => array() ) );
				fmb_engine_Facebook()->updateOptions( array( 'orderflow_test_api_event_code_expiration_at' => array() ) );
				return;
			}
		}
	}

	public function getEventsManager() {
		return $this->eventsManager;
	}

	public function get_of_id() {
		return $this->externalId;
	}

	public function set_pbid() {
		$pbid_cookie_name   = 'orderflow_id';
		$is_track_external_id = EventsManager::isTrackExternalId();
		if ( ! $is_track_external_id ) {
			return;
		}

		$user = wp_get_current_user();
		if ( $user && $user->ID ) {
			$user_external_id = $user->get( 'external_id' );
			if ( ! empty( $user_external_id ) ) {
				$this->externalId = $user_external_id;
				return;
			}
		}

		if ( ! empty( $_COOKIE[ $pbid_cookie_name ] ) ) {
			$this->externalId = wp_unslash( $_COOKIE[ $pbid_cookie_name ] );
			return;
		}

		$use_transient = $this->getOption( 'external_id_use_transient' );
		$ip            = $this->get_user_ip();
		$tkey          = 'orderflow_id-' . $ip;

		if ( $use_transient ) {
			$from_transient = get_transient( $tkey );
			if ( ! empty( $from_transient ) ) {
				$this->externalId = $from_transient;
				return;
			}
		}

		$unique_id          = bin2hex( random_bytes( 16 ) );
		$encrypted_unique_id = hash( 'sha256', $unique_id );
		$this->externalId   = $encrypted_unique_id;

		if ( $use_transient ) {
			set_transient( $tkey, $this->externalId, 60 * 10 );
		}
	}

	public function get_user_ip() {
		if ( null !== $this->resolved_user_ip ) {
			return $this->resolved_user_ip;
		}

		$ip = $_SERVER['REMOTE_ADDR'] ?? null;

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip             = trim( $forwarded_ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}

		$validated              = filter_var( $ip, FILTER_VALIDATE_IP );
		$this->resolved_user_ip = $validated ? (string) $validated : '0.0.0.0';

		return $this->resolved_user_ip;
	}

	public function woo_is_order_received_page() {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}
		global $post;
		$ids = FacebookRuntime()->getOption( 'woo_checkout_page_ids' );
		if ( ! empty( $ids ) ) {
			if ( $post && in_array( $post->ID, $ids, true ) ) {
				return true;
			}
		}
		if ( did_action( 'elementor/loaded' ) ) {
			if ( $post ) {
				$elementor_page_id = get_option( 'elementor_woocommerce_purchase_summary_page_id' );
				if ( $elementor_page_id == $post->ID ) {
					return true;
				}
			}
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return true;
		}
		return false;
	}
}



function FacebookRuntime() {
	return FacebookRuntime::instance();
}
