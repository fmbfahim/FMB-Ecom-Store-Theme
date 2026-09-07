<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}



class Custom_Events_Manager {

	private static $_instance;
	private $link_events = array();
	private $scroll_events = array();
	private $click_events = array();
	private $time_events = array();

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		$this->load_events();
		add_action( 'wp_footer', array( $this, 'output_custom_events_script' ), 20 );
	}

	private function load_events() {
		$this->link_events   = get_option( 'ads_fb_link_events', array() );
		$this->scroll_events = get_option( 'ads_fb_scroll_events', array() );
		$this->click_events  = get_option( 'ads_fb_click_events', array() );
		$this->time_events   = get_option( 'ads_fb_time_events', array() );
	}

	public function has_custom_events() {
		return ! empty( $this->link_events )
			|| ! empty( $this->scroll_events )
			|| ! empty( $this->click_events )
			|| ! empty( $this->time_events );
	}

	public function output_custom_events_script() {
		if ( ! fmb_engine_Facebook()->configured() ) {
			return;
		}

		if ( ! $this->has_custom_events() ) {
			return;
		}

		$standard_params = getStandardParams();

		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			const CONFIG = {
				linkEvents: <?php echo wp_json_encode( $this->link_events ); ?>,
				scrollEvents: <?php echo wp_json_encode( $this->scroll_events ); ?>,
				clickEvents: <?php echo wp_json_encode( $this->click_events ); ?>,
				timeEvents: <?php echo wp_json_encode( $this->time_events ); ?>,
				standardParams: <?php echo wp_json_encode( $standard_params ); ?>
			};

			const State = {
				isSetup: false,
				setupSelectors: {},
				pixel: null,
				clickTimestamps: {}
			};

			function getPixel() {
				if (State.pixel) {
					return State.pixel;
				}

				if (typeof window.orderflow !== 'undefined' && window.orderflow.Facebook && typeof window.orderflow.Facebook.fireEvent === 'function') {
					State.pixel = window.orderflow.Facebook;
				} else if (typeof getPixelBySlag === 'function') {
					State.pixel = getPixelBySlag('orderflow_facebook');
				}

				return State.pixel;
			}

			function fireCustomEvent(eventName, clickTimestamp, customData) {
				console.log('🔥 [FMB Engine Custom Event Triggered]:', eventName);
				
				var pixel = getPixel();
				if (!pixel || typeof pixel.fireEvent !== 'function') {
					return;
				}

				if (clickTimestamp) {
					var eventKey = eventName + '_' + clickTimestamp;
					if (State.clickTimestamps[eventKey]) {
						return;
					}
					State.clickTimestamps[eventKey] = true;

					setTimeout(function() {
						delete State.clickTimestamps[eventKey];
					}, 1000);
				}

				var eventParams = {};
				if (typeof CONFIG.standardParams !== 'undefined' && CONFIG.standardParams !== null) {
					for (var key in CONFIG.standardParams) {
						if (CONFIG.standardParams.hasOwnProperty(key)) {
							eventParams[key] = CONFIG.standardParams[key];
						}
					}
				}

				if (typeof customData === 'object' && customData !== null) {
					for (var cKey in customData) {
						if (customData.hasOwnProperty(cKey)) {
							eventParams[cKey] = customData[cKey];
						}
					}
				}

				pixel.fireEvent(eventName, {
					name: eventName,
					params: eventParams,
					type: 'dynamic',
					eventID: [],
					e_id: 'custom_event_' + eventName.replace(/\s+/g, '_').toLowerCase()
				});
			}

			function setupLinkEvents() {
				CONFIG.linkEvents.forEach(function(event) {
					if (!event.selector || !event.event_name) {
						return;
					}

					if (State.setupSelectors['link_' + event.selector]) {
						return;
					}

					State.setupSelectors['link_' + event.selector] = true;

					$(document).off('click.customEventLink', event.selector);

					$(document).on('click.customEventLink', event.selector, function(e) {
						console.log('🔗 [FMB Engine Link Click Matched] Selector:', event.selector, 'Target:', e.target, 'Event:', event.event_name);
						var clickTimestamp = Date.now() + '_' + Math.random();
						var $el = $(this);
						var customData = {
							target_url: $el.attr('href') || $el.closest('a').attr('href') || '',
							text: $el.text().trim().substring(0, 100) || '',
							element_classes: $el.attr('class') || '',
							element_id: $el.attr('id') || ''
						};
						fireCustomEvent(event.event_name, clickTimestamp, customData);
					});
				});
			}

			function setupClickEvents() {
				CONFIG.clickEvents.forEach(function(event) {
					if (!event.selector || !event.event_name) {
						return;
					}

					if (State.setupSelectors['click_' + event.selector]) {
						return;
					}

					State.setupSelectors['click_' + event.selector] = true;

					$(document).off('click.customEventClick', event.selector);

					$(document).on('click.customEventClick', event.selector, function(e) {
						console.log('🖱️ [FMB Engine Generic Click Matched] Selector:', event.selector, 'Target:', e.target, 'Event:', event.event_name);
						var clickTimestamp = Date.now() + '_' + Math.random();
						var $el = $(this);
						var customData = {
							text: $el.text().trim().substring(0, 100) || '',
							element_classes: $el.attr('class') || '',
							element_id: $el.attr('id') || ''
						};
						fireCustomEvent(event.event_name, clickTimestamp, customData);
					});
				});
			}

			function setupScrollEvents() {
				if (CONFIG.scrollEvents.length === 0) {
					return;
				}

				$(window).on('scroll.customEventScroll', function() {
					var scrollPercent = Math.round((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100);
					
					CONFIG.scrollEvents.forEach(function(event, index) {
						if (!event.percentage || !event.event_name) {
							return;
						}
						
						var targetPercent = parseInt(event.percentage, 10);
						if (isNaN(targetPercent)) return;

						var stateKey = 'scroll_' + index;
						if (!State.setupSelectors[stateKey] && scrollPercent >= targetPercent) {
							console.log('📜 [FMB Engine Scroll Matched] Target:', targetPercent, 'Actual:', scrollPercent, 'Event:', event.event_name);
							State.setupSelectors[stateKey] = true;
							
							var customData = {
								scroll_depth_percent: scrollPercent
							};
							
							fireCustomEvent(event.event_name, null, customData);
						}
					});
				});
			}

			function setupTimeEvents() {
				CONFIG.timeEvents.forEach(function(event, index) {
					if (!event.event_name || !event.seconds) {
						return;
					}

					var targetSeconds = parseInt(event.seconds, 10);
					if (isNaN(targetSeconds)) return;

					var stateKey = 'time_' + index;
					if (State.setupSelectors[stateKey]) {
						return;
					}

					setTimeout(function() {
						if (!State.setupSelectors[stateKey]) {
							console.log('⏳ [FMB Engine Time Event Matched] Seconds:', targetSeconds, 'Event:', event.event_name);
							State.setupSelectors[stateKey] = true;
							
							var customData = {
								time_spent_seconds: targetSeconds
							};
							fireCustomEvent(event.event_name, null, customData);
						}
					}, targetSeconds * 1000);
				});
			}

			function setupCustomEvents() {
				if (State.isSetup) {
					return;
				}

				State.isSetup = true;

				setupLinkEvents();
				setupClickEvents();
				setupScrollEvents();
				setupTimeEvents();
			}

			function initCustomEvents() {
				var checkPixel = setInterval(function() {
					if (getPixel() && typeof getPixel().fireEvent === 'function') {
						clearInterval(checkPixel);
						setupCustomEvents();
					}
				}, 100);

				setTimeout(function() {
					clearInterval(checkPixel);
					if (getPixel()) {
						setupCustomEvents();
					}
				}, 5000);
			}

			$(document).ready(function() {
				initCustomEvents();
			});
		})(jQuery);
		</script>
		<?php
	}
}
