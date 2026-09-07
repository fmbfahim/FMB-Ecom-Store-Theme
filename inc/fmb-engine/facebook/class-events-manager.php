<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

class EventsManager {

	private const WOO_SLUG        = 'woo';
	private const FB_PIXEL_SLUG   = 'orderflow_facebook';
	private const WOO_STATIC_IDS  = array(
		'woo_view_content',
		'woo_initiate_checkout',
		'woo_purchase',
	);

	private $standardParams = array();
	private $staticEvents   = array();
	private $dynamicEvents  = array();
	private $uniqueId       = array();

	/** @var int Count of shop/archive loop products that received inline add-to-cart payload (see orderflow_max_shop_loop_product_data). */
	private $shop_loop_product_payload_count = 0;

	/** @var int|null Temporary product ID for orderflow_conditional_post_id while building payloads. */
	private static $conditional_post_id_override = null;

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'setupEventsParams' ), 14 );
		add_action( 'wp_enqueue_scripts', array( $this, 'outputData' ), 15 );
		add_action( 'wp_footer', array( $this, 'outputNoScriptData' ), 10 );
	}

	public function enqueueScripts() {
		$public_js = FMB_ENGINE_PATH . 'dist/scripts/public.js';
		$script_ver  = ( file_exists( $public_js ) ? (string) filemtime( $public_js ) : '' );
		$script_ver .= '-' . ( defined( 'FMB_THEME_VERSION' ) ? FMB_THEME_VERSION : '1.9.0' );

		wp_register_script( 'fmb-engine-jquery-bind-first', FMB_ENGINE_URL . '/dist/scripts/jquery.bind-first-0.2.3.min.js', array( 'jquery' ) );
		wp_enqueue_script( 'fmb-engine-jquery-bind-first' );
		wp_register_script( 'fmb-engine-js-cookie', FMB_ENGINE_URL . '/dist/scripts/js.cookie-2.1.3.min.js', array(), '2.1.3' );
		wp_register_script( 'fmb-engine-js-tld', FMB_ENGINE_URL . '/dist/scripts/tld.min.js', array( 'jquery' ), '2.3.1' );
		wp_enqueue_script( 'fmb-engine-js-cookie' );
		wp_enqueue_script( 'fmb-engine-js-tld' );
		wp_enqueue_script(
			'fmb-engine-public',
			FMB_ENGINE_URL . '/dist/scripts/public.js',
			array( 'jquery', 'fmb-engine-js-cookie', 'fmb-engine-jquery-bind-first', 'fmb-engine-js-tld' ),
			$script_ver
		);
	}

	public function outputData() {
		 
		$data = array(
			'staticEvents'      => $this->staticEvents,
			'dynamicEvents'     => $this->dynamicEvents,
			'triggerEvents'     => array(),
			'triggerEventTypes' => array(),
		);

		$fb = Facebook::instance();
		if ( $fb->configured() ) {
			$data[ $fb->getSlug() ] = $fb->getPixelOptions();
		}

		$standard = getStandardParams();
		$options  = $this->build_script_options( $standard );

		$options[ self::WOO_SLUG ] = $this->woo_get_options();
		$public_js = FMB_ENGINE_PATH . 'dist/scripts/public.js';
		$options['cache_bypass'] = file_exists( $public_js ) ? filemtime( $public_js ) : ( defined( 'FMB_THEME_VERSION' ) ? FMB_THEME_VERSION : 0 );

		$this->inject_init_event_extras( $data, $standard );
		$this->alias_pixel_slug_for_js( $data, 'orderflow_facebook', 'facebook' );

		wp_localize_script( 'fmb-engine-public', 'orderflowOptions', array_merge( $data, $options ) );
	}

	 
	private function build_script_options( array $standardParams ): array {
		$core = FacebookRuntime();
		$opts = array(
			'debug'                            => $core->getOption( 'debug_enabled' ),
			'siteUrl'                          => site_url(),
			'ajaxUrl'                          => admin_url( 'admin-ajax.php' ),
			'ajax_event'                       => wp_create_nonce( 'ajax-event-nonce' ),
			'enable_remove_download_url_param' => $core->getOption( 'enable_remove_download_url_param' ),
			'cookie_duration'                  => 4380, // Forced lifetime expiration (~12 years)
			'last_visit_duration'              => $core->getOption( 'last_visit_duration' ),
			'enable_success_send_form'         => $core->getOption( 'enable_success_send_form' ),
			'ajaxForServerEvent'               => $core->getOption( 'server_event_use_ajax' ) ?? true,
			'ajaxForServerStaticEvent'         => $core->getOption( 'server_static_event_use_ajax' ) ?? true,
			'useSendBeacon'                    => $core->getOption( 'use_send_beacon' ),
			'send_external_id'                 => $core->getOption( 'send_external_id' ),
			'external_id_expire'               => 4380, // Forced lifetime expiration (~12 years)
			'track_cookie_for_subdomains'      => $core->getOption( 'track_cookie_for_subdomains' ),
			'cookie'                           => array(
				'disabled_all_cookie'                => apply_filters( 'orderflow_disable_all_cookie', false ),
				'disabled_start_session_cookie'      => apply_filters( 'orderflow_disabled_start_session_cookie', false ),
				'disabled_advanced_form_data_cookie' => apply_filters( 'orderflow_disable_advanced_form_data_cookie', false ) || apply_filters( 'orderflow_disable_advance_data_cookie', false ),
				'disabled_landing_page_cookie'       => apply_filters( 'orderflow_disable_landing_page_cookie', false ),
				'disabled_first_visit_cookie'        => apply_filters( 'orderflow_disable_first_visit_cookie', false ),
				'disabled_trafficsource_cookie'      => apply_filters( 'orderflow_disable_trafficsource_cookie', false ),
				'disabled_utmTerms_cookie'           => apply_filters( 'orderflow_disable_utmTerms_cookie', false ),
				'disabled_utmId_cookie'              => apply_filters( 'orderflow_disable_utmId_cookie', false ),
			),
			'tracking_analytics'               => array(
				'TrafficSource'  => getTrafficSource(),
				'TrafficLanding' => $_COOKIE['orderflow_landing_page'] ?? $_SESSION['LandingPage'] ?? 'undefined',
				'TrafficUtms'    => getUtms(),
				'TrafficUtmsId'  => getUtmsId(),
			),
		);

		if ( ! empty( $standardParams['post_id'] ) ) {
			$opts['current_post_id'] = $standardParams['post_id'];
		}
		if ( ! empty( $standardParams['page_title'] ) ) {
			$opts['current_page_title'] = $standardParams['page_title'];
		}
		if ( is_singular( 'product' ) ) {
			$opts['current_tags'] = self::product_page_tag_string();
		} elseif ( isset( $standardParams['tags'] ) ) {
			$opts['current_tags'] = $standardParams['tags'];
		}

		return $opts;
	}

	 
	private function inject_init_event_extras( array &$data, array $standardParams ): void {
		if ( empty( $data['staticEvents'] ) ) {
			return;
		}

		$product_tags = is_singular( 'product' ) ? self::product_page_tag_string() : null;

		$page_title = null;
		if ( FacebookRuntime()->getOption( 'enable_page_title_param' ) && ! empty( $standardParams['page_title'] ) ) {
			$page_title = $standardParams['page_title'];
		}

		foreach ( $data['staticEvents'] as $slug => $events ) {
			if ( empty( $events['init_event'] ) || ! is_array( $events['init_event'] ) ) {
				continue;
			}
			foreach ( $events['init_event'] as $idx => $event_data ) {
				if ( empty( $event_data['params'] ) ) {
					continue;
				}
				if ( $product_tags !== null ) {
					$data['staticEvents'][ $slug ]['init_event'][ $idx ]['params']['tags'] = $product_tags;
				}
				if ( $page_title !== null ) {
					$data['staticEvents'][ $slug ]['init_event'][ $idx ]['params']['page_title'] = $page_title;
				}
			}
		}
	}

	 
	private function alias_pixel_slug_for_js( array &$data, string $from, string $to ): void {
		if ( isset( $data[ $from ] ) && ! isset( $data[ $to ] ) ) {
			$data[ $to ] = $data[ $from ];
		}
		foreach ( array( 'staticEvents', 'dynamicEvents', 'triggerEvents' ) as $bucket ) {
			if ( empty( $data[ $bucket ] ) || ! is_array( $data[ $bucket ] ) ) {
				continue;
			}
			foreach ( $data[ $bucket ] as $id => $row ) {
				if ( is_array( $row ) && isset( $row[ $from ] ) ) {
					$data[ $bucket ][ $id ][ $to ] = $row[ $from ];
				}
			}
		}
	}

	public function outputNoScriptData() {
		Facebook::instance()->outputNoScriptEvents();
	}

	public function setupEventsParams() {
		$this->standardParams = getStandardParams();

		if ( isWooCommerceActive() ) {
			$this->addEvents( $this->woo_generate_events(), self::WOO_SLUG );
		}

		$initEvent = new SingleEvent( 'init_event', EventTypes::$STATIC, '' );
		if ( get_post_type() === 'post' && ! is_archive() ) {
			global $post;
			$names = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && ! empty( $names ) ) {
				$initEvent->addParams( array( 'post_category' => implode( ', ', $names ) ) );
			}
		}

		$fb = Facebook::instance();
		if ( $fb->configured() ) {
			foreach ( $fb->generateEvents( $initEvent ) as $event ) {
				$event->addParams( $this->standardParams );
				$this->addStaticEvent( $event, $fb, '' );
			}
		}

		if ( isWooCommerceActive() ) {
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'setupWooLoopProductData' ) );
			add_action( 'woocommerce_after_add_to_cart_button', array( self::class, 'setupWooSingleProductData' ) );
			add_filter( 'woocommerce_blocks_product_grid_item_html', array( $this, 'setupWooBlocksProductData' ), 10, 3 );
			add_filter( 'jet-woo-builder/elementor-views/frontend/archive-item-content', array( $this, 'setupWooBlocksProductData' ), 10, 3 );
		}
	}

	public function getStaticEvents( $context ) {
		return $this->staticEvents[ $context ] ?? array();
	}

	

	public function addEvents( $pixelEvents, $slug ) {
		$pixel = Facebook::instance();
		if ( ! $pixel->configured() || empty( $pixelEvents[ self::FB_PIXEL_SLUG ] ) ) {
			return;
		}
		foreach ( $pixelEvents[ self::FB_PIXEL_SLUG ] as $event ) {
			if ( ! isset( $this->uniqueId[ $event->getId() ] ) ) {
				$this->uniqueId[ $event->getId() ] = EventIdGenerator::guidv4();
			}
			$event->addPayload( array( 'eventID' => $this->uniqueId[ $event->getId() ] ) );

			$event->addParams( $this->standardParams );
			if ( $event->getType() === EventTypes::$STATIC ) {
				$this->addStaticEvent( $event, $pixel, $slug );
			} elseif ( $event->getType() === EventTypes::$DYNAMIC ) {
				$this->addDynamicEvent( $event, $pixel, $slug );
			}
		}
	}

	public function addDynamicEvent( $event, $pixel, $slug ) {
		$eventData = $event->getData();
		$eventData = self::filterEventParams( $eventData, $slug, array( 'event_id' => $event->getId(), 'pixel' => $pixel->getSlug() ) );

		$this->dynamicEvents[ $event->getId() ][ $pixel->getSlug() ] = $eventData;
	}

	

	public function addStaticEvent( $event, $pixel, $slug ) {
		$event_getId = $event->getId() === 'custom_event' ? $event->getPayloadValue( 'custom_event_post_id' ) : $event->getId();

		if ( ! isset( $this->uniqueId[ $event_getId ] ) ) {
			$this->uniqueId[ $event_getId ] = EventIdGenerator::guidv4();
		}

		$event->addPayload( array( 'eventID' => $this->uniqueId[ $event_getId ] ) );

		$eventData = $event->getData();
		$eventData = self::filterEventParams( $eventData, $slug, array( 'event_id' => $event->getId(), 'pixel' => $pixel->getSlug() ) );

		$this->staticEvents[ $pixel->getSlug() ][ $event->getId() ][] = $eventData;
	}

	public static function filterEventParams( $data, $slug, $context = null ) {
		$runtime = FacebookRuntime();
		if ( ! $runtime->getOption( 'enable_content_name_param' ) ) {
			$event_id = isset( $context['event_id'] ) ? $context['event_id'] : '';
			$keep     = array( 'woo_initiate_checkout', 'woo_purchase', 'woo_add_to_cart_on_button_click' );
			if ( ! in_array( $event_id, $keep, true ) ) {
				unset( $data['params']['content_name'] );
			}
		}
		if ( ! $runtime->getOption( 'enable_page_title_param' ) ) {
			unset( $data['params']['page_title'] );
		}
		if ( ! $runtime->getOption( 'enable_post_category_param' ) ) {
			unset( $data['params']['post_category'] );
		}
		return $data;
	}

	public static function isTrackExternalId() {
		return FacebookRuntime()->getOption( 'send_external_id' ) && ! apply_filters( 'orderflow_disable_all_cookie', false );
	}

	public function setupWooLoopProductData() {
		global $product;

		$this->setupWooProductData( $product );
	}

	public function setupWooBlocksProductData( $html, $data, $product ) {
		$this->setupWooProductData( $product );
		return $html;
	}

	public function setupWooProductData( $product ) {

		if ( ! is_a( $product, 'WC_Product' )
			|| wooProductIsType( $product, 'variable' )
			|| wooProductIsType( $product, 'grouped' )
		) {
			return;
		}

		$max_loop = (int) apply_filters( 'orderflow_max_shop_loop_product_data', 0 );
		if ( $max_loop > 0 && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) ) {
			if ( $this->shop_loop_product_payload_count >= $max_loop ) {
				return;
			}
			$this->shop_loop_product_payload_count++;
		}

		$product_id = $product->get_id();
		$params     = self::woo_add_to_cart_js_payload( $product_id, false );
		if ( empty( $params ) ) {
			return;
		}

		$params = wp_json_encode( $params );

		?>

		<script type="application/javascript" style="display:none">
			window.orderflowWooProductData = window.orderflowWooProductData || [];
			window.orderflowWooProductData[ <?php echo esc_js( (string) $product_id ); ?> ] = <?php echo $params; ?>;
		</script>

		<?php

	}

	public static function setupWooSingleProductData() {
		global $product;

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		if ( wooProductIsType( $product, 'external' ) ) {
			return;
		}

		$product_id  = $product->get_id();
		$product_ids = wooProductIsType( $product, 'variable' )
			? array_merge( array( $product_id ), $product->get_children() )
			: array( $product_id );

		$params = array();
		foreach ( $product_ids as $pid ) {
			$row = self::woo_add_to_cart_js_payload( (int) $pid, true );
			if ( ! empty( $row ) ) {
				$params[ $pid ] = $row;
			}
		}

		if ( empty( $params ) ) {
			return;
		}

		?>

		<script type="application/javascript" style="display:none">
			window.orderflowWooProductData = window.orderflowWooProductData || [];
			<?php foreach ( $params as $pid => $product_data ) : ?>
			window.orderflowWooProductData[<?php echo esc_js( (string) $pid ); ?>] = <?php echo wp_json_encode( $product_data ); ?>;
			<?php endforeach; ?>
		</script>

		<?php

	}

	 
	private function woo_generate_events() {
		if ( ! isWooCommerceActive() || ! Facebook::instance()->configured() ) {
			return array();
		}
		$list = array();
		foreach ( self::WOO_STATIC_IDS as $event_id ) {
			$this->woo_push_pixel_events( $list, new SingleEvent( $event_id, EventTypes::$STATIC, 'woo' ) );
		}
		$this->woo_push_pixel_events( $list, new SingleEvent( 'woo_add_to_cart_on_button_click', EventTypes::$DYNAMIC, 'woo' ) );

		return $list;
	}

	

	private function woo_push_pixel_events( array &$eventsList, $event ): void {
		$pixel = Facebook::instance();
		foreach ( $pixel->generateEvents( $event ) as $pixelEvent ) {
			if ( apply_filters( 'orderflow_validate_pixel_event', true, $pixelEvent, $pixel ) ) {
				$eventsList[ $pixel->getSlug() ][] = $pixelEvent;
			}
		}
	}

	private function woo_get_options() {
		if ( isWooCommerceActive() ) {
			global $post;
			return array(
				'enabled'                       => true,
				'enabled_save_data_to_orders'   => FacebookRuntime()->getOption( 'woo_enabled_save_data_to_orders' ),
				'addToCartOnButtonEnabled'      => true,
				'addToCartOnButtonValueEnabled' => FacebookRuntime()->getOption( 'woo_add_to_cart_value_enabled' ),
				'addToCartOnButtonValueOption'  => FacebookRuntime()->getOption( 'woo_add_to_cart_value_option' ),
				'singleProductId'               => isWooCommerceActive() && is_singular( 'product' ) ? $post->ID : null,
				'removeFromCartSelector'        => isWooCommerceVersionGte( '3.0.0' )
					? 'form.woocommerce-cart-form .remove'
					: '.cart .product-remove .remove',
				'addToCartCatchMethod'          => 'add_cart_js',
				'is_order_received_page'        => FacebookRuntime()->woo_is_order_received_page(),
				'containOrderId'                => wooIsRequestContainOrderId(),
			);
		}
		return array( 'enabled' => false );
	}

	 
	private static function product_page_tag_string(): string {
		$tags = getObjectTerms( 'product_tag', get_queried_object_id() );
		if ( ! $tags ) {
			return '';
		}
		return is_array( $tags ) ? implode( ', ', $tags ) : (string) $tags;
	}

	

	/**
	 * Filter callback registered only while building variable-product add-to-cart payloads.
	 *
	 * @param mixed $post_id Default post ID from WordPress.
	 * @return mixed
	 */
	public static function filter_conditional_post_id_for_payload( $post_id ) {
		return null !== self::$conditional_post_id_override ? self::$conditional_post_id_override : $post_id;
	}

	private static function woo_add_to_cart_js_payload( int $product_id, bool $conditional_post_id ): ?array {
		$fb = Facebook::instance();
		if ( ! $fb->configured() ) {
			return null;
		}
		if ( $conditional_post_id ) {
			self::$conditional_post_id_override = $product_id;
			add_filter( 'orderflow_conditional_post_id', array( __CLASS__, 'filter_conditional_post_id_for_payload' ), 10, 1 );
		}
		$event = new SingleEvent( 'woo_add_to_cart_on_button_click', EventTypes::$STATIC, 'woo' );
		$event->args = array( 'productId' => $product_id, 'quantity' => 1 );
		$events      = $fb->generateEvents( $event );
		if ( $conditional_post_id ) {
			remove_filter( 'orderflow_conditional_post_id', array( __CLASS__, 'filter_conditional_post_id_for_payload' ), 10 );
			self::$conditional_post_id_override = null;
		}
		foreach ( $events as $ev ) {
			$data = self::filterEventParams(
				$ev->getData(),
				'woo',
				array( 'event_id' => $ev->getId(), 'pixel' => $fb->getSlug() )
			);
			return array(
				$fb->getSlug() => $data,
				'facebook'     => $data,
			);
		}
		return null;
	}

}
