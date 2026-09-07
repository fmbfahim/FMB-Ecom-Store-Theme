<?php
namespace fmb_engine\Facebook;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Content;
defined('ABSPATH') or die('Direct access not allowed');


class FacebookCapiEventHelper {
    private static $fbp;
    private static $fbc;

    public static function mapEventToServerEvent($event) {
        $eventData = $event->getData();
        
        
        $preserve_page_title    = isset($eventData['params']['page_title']) ? $eventData['params']['page_title'] : null;
        $preserve_content_name  = isset($eventData['params']['content_name']) ? $eventData['params']['content_name'] : null;
        $eventData = \fmb_engine\Facebook\EventsManager::filterEventParams($eventData,$event->getCategory(),[
            'event_id'=>$event->getId(),
            'pixel'=>\fmb_engine\Facebook\Facebook::instance()->getSlug()
        ]);
        if ( $preserve_page_title !== null && ( empty( $eventData['params']['page_title'] ) ) ) {
            $eventData['params']['page_title'] = $preserve_page_title;
        }
        if ( $preserve_content_name !== null && ( empty( $eventData['params']['content_name'] ) ) ) {
            $eventData['params']['content_name'] = $preserve_content_name;
        }

        $eventName = $eventData['name'];
        $eventParams = $eventData['params'];
        $eventId = $event->payload['eventID'];
        $wooOrder = isset($event->payload['woo_order']) ? $event->payload['woo_order'] : null;

        if ( ! $eventId ) {
            return null;
        }

        $user_data = self::getUserData($wooOrder);
        self::applyExplicitEventUserData( $user_data, $eventParams );
        $user_data
            ->setClientIpAddress(self::getIpAddress())
            ->setClientUserAgent(self::getHttpUserAgent());

		if ( ! self::getFbp() && ( ! isset( $eventParams['_fbp'] ) || ! $eventParams['_fbp'] ) && ! headers_sent() ) {
			self::setFbp( 'fb.1.' . time() . '.' . wp_rand( 1000000000, 9999999999 ) );
			if ( ! headers_sent() ) {
				setcookie( '_fbp', self::getFbp(), 2147483647, '/', \fmb_engine\Facebook\FacebookRuntime()->general_domain );
			}
		}

		if ( ! self::getFbc() && self::getUrlParameter( 'fbclid' ) ) {
			$fbclid = self::getUrlParameter( 'fbclid' );
			if ( $fbclid ) {
				self::setFbc( 'fb.1.' . time() . '.' . $fbclid );
				if ( ! headers_sent() ) {
					setcookie( '_fbc', self::$fbc, 2147483647, '/', \fmb_engine\Facebook\FacebookRuntime()->general_domain );
				}
			}
		}

        $fbp = '';
        $fbc = '';

        if ($wooOrder) {
            $fbp = self::getFbStatFromOrder('fbp', $wooOrder);
            $fbc = self::getFbStatFromOrder('fbc', $wooOrder);
        }


        if(empty($fbp)) {
            $fbp = self::getFbp() ?? $eventParams['_fbp'] ?? '';
        }
        if (empty($fbc)) {
            $fbc = self::getFbc() ?? $eventParams['_fbc'] ?? '';
        }

        if(!empty($fbp)) { $user_data->setFbp($fbp); }
        if(!empty($fbc)) { $user_data->setFbc($fbc); }

        $customData = self::paramsToCustomData($eventParams);
        $uri = self::getRequestUri(\fmb_engine\Facebook\FacebookRuntime()->getOption('enable_remove_source_url_params'));

        
        if(isset($_POST['url'])) {
            if(\fmb_engine\Facebook\FacebookRuntime()->getOption('enable_remove_source_url_params')) {
                $list = explode("?",$_POST['url']);
                if(is_array($list) && count($list) > 0) {
                    $uri = $list[0];
                } else {
                    $uri = $_POST['url'];
                }
            } else {
                $uri = $_POST['url'];
            }
        }

        $event = (new Event())
            ->setEventName($eventName)
            ->setEventTime(time())
            ->setEventId($eventId)
            ->setEventSourceUrl($uri)
            ->setActionSource("website")
            ->setCustomData($customData)
            ->setUserData($user_data);

		if ( \fmb_engine\Facebook\Facebook::instance()->getLDUMode() ) {
			$event
			->setDataProcessingOptions( [ 'LDU' ] )
			->setDataProcessingOptionsCountry( 0 )
			->setDataProcessingOptionsState( 0 );
		}

        return $event;
    }

    

    public static function getUserDataKeysForLog( $userData ) {
        if ( ! $userData ) {
            return 'null';
        }
        $keys = array();
        $checks = array( 'getEmail' => 'email', 'getPhone' => 'phone', 'getFirstName' => 'first_name', 'getLastName' => 'last_name', 'getCity' => 'city', 'getState' => 'state', 'getCountryCode' => 'country', 'getZipCode' => 'postcode', 'getExternalId' => 'external_id', 'getFbp' => 'fbp', 'getFbc' => 'fbc', 'getClientIpAddress' => 'client_ip', 'getClientUserAgent' => 'user_agent' );
        foreach ( $checks as $method => $label ) {
            if ( method_exists( $userData, $method ) && $userData->{$method}() ) {
                $keys[] = $label;
            }
        }
        return empty( $keys ) ? 'none' : implode( ', ', $keys );
    }

    

    private static function applyExplicitEventUserData( $userData, $eventParams ) {
        if ( ! $userData || ! is_array( $eventParams ) ) {
            return;
        }

        $field_map = array(
            'email'      => array( 'getter' => 'getEmail',       'setter' => 'setEmail',       'sanitize' => 'sanitize_email' ),
            'first_name' => array( 'getter' => 'getFirstName',   'setter' => 'setFirstName',   'sanitize' => 'sanitize_text_field' ),
            'last_name'  => array( 'getter' => 'getLastName',    'setter' => 'setLastName',    'sanitize' => 'sanitize_text_field' ),
            'phone'      => array( 'getter' => 'getPhone',       'setter' => 'setPhone',       'sanitize' => 'sanitize_text_field' ),
            'city'       => array( 'getter' => 'getCity',        'setter' => 'setCity',        'sanitize' => 'sanitize_text_field' ),
            'state'      => array( 'getter' => 'getState',       'setter' => 'setState',       'sanitize' => 'sanitize_text_field' ),
            'country'    => array( 'getter' => 'getCountryCode', 'setter' => 'setCountryCode', 'sanitize' => 'sanitize_text_field' ),
            'postcode'   => array( 'getter' => 'getZipCode',     'setter' => 'setZipCode',     'sanitize' => 'sanitize_text_field' ),
            'external_id'=> array( 'getter' => 'getExternalId',  'setter' => 'setExternalId',  'sanitize' => 'sanitize_text_field' ),
        );

        foreach ( $field_map as $param_key => $config ) {
            if ( empty( $eventParams[ $param_key ] ) ) {
                continue;
            }

            $current_value = method_exists( $userData, $config['getter'] ) ? $userData->{$config['getter']}() : null;
            if ( ! empty( $current_value ) ) {
                continue;
            }

            $value = call_user_func( $config['sanitize'], $eventParams[ $param_key ] );

            if ( $param_key === 'phone' && ! empty( $value ) ) {
                $value = preg_replace('/[^\d]/', '', $value);
                if (strlen($value) === 11 && strpos($value, '01') === 0) {
                    $value = '88' . $value;
                }
            }

            if ( $param_key === 'country' ) {
                $value = strtolower( $value );
            }

            if ( $value !== '' && method_exists( $userData, $config['setter'] ) ) {
                $userData->{$config['setter']}( $value );
            }
        }
    }

    


    private static function getFbStatFromOrder($key,$wooOrder) {

        $order = wc_get_order( $wooOrder );
        if($order) {
            $fbCookie = $order->get_meta( 'orderflow_fb_cookie', true );
            if($fbCookie){
                if(!empty($fbCookie[$key])) {
                    return $fbCookie[$key];
                }
            }
        }
        return null;
    }


    private static function getIpAddress() {
        if (function_exists('fmb_get_client_ip_address')) {
            return fmb_get_client_ip_address(true);
        }

        $HEADERS_TO_SCAN = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        $found_ipv4 = null;
        foreach ($HEADERS_TO_SCAN as $header) {
            if (array_key_exists($header, $_SERVER)) {
                $ip_list = explode(',', $_SERVER[$header]);
                foreach($ip_list as $ip) {
                    $trimmed_ip = trim($ip);
                    if (filter_var($trimmed_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $trimmed_ip; // Public IPv6 takes absolute top priority
                    }
                    if (!$found_ipv4 && filter_var($trimmed_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $found_ipv4 = $trimmed_ip;
                    }
                }
            }
        }

        return $found_ipv4 ?: (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : "127.0.0.1");
    }

    private static function isValidIpAddress($ip_address) {
        return filter_var($ip_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
            | FILTER_FLAG_IPV6
            | FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function getHttpUserAgent() {
        $user_agent = null;

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        return $user_agent;
    }

    private static function getRequestUri($removeQuery = false) {
        $request_uri = null;

        if (!empty($_SERVER['REQUEST_URI'])) {
            $start = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")."://";
            $host = $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST);
            $request_uri = $start . $host . $_SERVER['REQUEST_URI'];
        }
        if($removeQuery && isset($_SERVER['QUERY_STRING'])) {
            $request_uri = str_replace("?".$_SERVER['QUERY_STRING'],"",$request_uri);
        }


        return $request_uri;
    }
    static function getUrlParameter($sParam) {
        $sPageURL = $_SERVER['QUERY_STRING'];
        $sURLVariables = explode('&', $sPageURL);

        foreach ($sURLVariables as $sURLVariable) {
            $sParameterName = explode('=', $sURLVariable);

            if ($sParameterName[0] === $sParam) {
                return isset($sParameterName[1]) ? urldecode($sParameterName[1]) : true;
            }
        }

        return false;
    }

    public static function setFbp($fbp) {
        self::$fbp = $fbp;
    }

    public static function setFbc($fbc) {
        self::$fbc = $fbc;
    }
    public static function getFbp() {
        $fbp = null;

        if (!empty($_COOKIE['_fbp'])) {
            $fbp = $_COOKIE['_fbp'];
        }
        elseif (!empty(self::$fbp)){
            $fbp = self::$fbp;
        }
        return $fbp;
    }

    public static function getFbc() {
        $fbc = null;

        if (!empty($_COOKIE['_fbc'])) {
            $fbc = $_COOKIE['_fbc'];
        }
        elseif (!empty(self::$fbc)){
            $fbc = self::$fbc;
        }
        return $fbc;
    }

    private static function getUserData( $woo_order = null ) {
		$woo_purchase_context = \fmb_engine\Facebook\isWooCommerceActive() && isEventEnabled( 'woo_purchase_enabled' )
			&& ( $woo_order || ( \fmb_engine\Facebook\FacebookRuntime()->woo_is_order_received_page() && wooIsRequestContainOrderId() ) );

		if ( ! $woo_purchase_context ) {
			return self::getRegularUserData();
		}

		$order_id = wooIsRequestContainOrderId() ? wooGetOrderIdFromRequest() : $woo_order;
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return self::getRegularUserData();
		}

		$userData = new UserData();

		$user_firstname = $order->get_billing_first_name();
		$user_lastname  = $order->get_billing_last_name();
		$user_phone     = $order->get_billing_phone();
		$user_email     = $order->get_billing_email();

		if ( ! empty( $user_phone ) ) {
			$user_phone = preg_replace('/[^\d]/', '', $user_phone);
			if (strlen($user_phone) === 11 && strpos($user_phone, '01') === 0) {
				$user_phone = '88' . $user_phone;
			}
		}

		if ( $order->get_billing_postcode() ) {
			$userData->setZipCode( $order->get_billing_postcode() );
		}
		if ( $order->get_billing_country() ) {
			$userData->setCountryCode( strtolower( $order->get_billing_country() ) );
		}
		if ( $order->get_billing_city() ) {
			$userData->setCity( $order->get_billing_city() );
		}
		if ( $order->get_billing_state() ) {
			$userData->setState( $order->get_billing_state() );
		}

		$has_external_id = false;
		if ( $order->get_meta( 'external_id' ) ) {
			$external_id = $order->get_meta( 'external_id' );
			if ( ! empty( $external_id ) ) {
				$userData->setExternalId( $external_id );
				$has_external_id = true;
			}
		}
		if ( ! $has_external_id && \fmb_engine\Facebook\EventsManager::isTrackExternalId() ) {
			$device_hash = $order->get_meta( '_ads_device_hash', true );
			if ( ! empty( $device_hash ) ) {
				$userData->setExternalId( $device_hash );
			} elseif ( ! empty( $_COOKIE['orderflow_id'] ) ) {
				$userData->setExternalId( sanitize_text_field( wp_unslash( $_COOKIE['orderflow_id'] ) ) );
			} elseif ( \fmb_engine\Facebook\FacebookRuntime()->get_of_id() ) {
				$userData->setExternalId( \fmb_engine\Facebook\FacebookRuntime()->get_of_id() );
			}
		}

		$user_persistence_data = get_persistence_user_data( $user_email, $user_firstname, $user_lastname, $user_phone );
		if ( ! empty( $user_persistence_data['fn'] ) ) {
			$userData->setFirstName( $user_persistence_data['fn'] );
		}
		if ( ! empty( $user_persistence_data['ln'] ) ) {
			$userData->setLastName( $user_persistence_data['ln'] );
		}
		if ( ! empty( $user_persistence_data['em'] ) ) {
			$userData->setEmail( $user_persistence_data['em'] );
		}
		if ( ! empty( $user_persistence_data['tel'] ) ) {
			$userData->setPhone( $user_persistence_data['tel'] );
		}

		$user_id = $order->get_user_id();
		if ( $user_id && apply_filters( 'orderflow_send_meta_id', true ) ) {
			$login_id = get_user_meta( $user_id, '_socplug_social_id_Facebook', true );
			if ( ! empty( $login_id ) ) {
				$userData->setFbLoginId( $login_id );
			}
		}

		return $userData;
    }

    private static function getRegularUserData() {
        $user = wp_get_current_user();
        $userData = new UserData();

        
        if ( \fmb_engine\Facebook\isWooCommerceActive() && function_exists( 'WC' ) && WC()->customer ) {
            $customer = WC()->customer;
            if ( $customer->get_billing_first_name() ) {
                $userData->setFirstName( $customer->get_billing_first_name() );
            }
            if ( $customer->get_billing_last_name() ) {
                $userData->setLastName( $customer->get_billing_last_name() );
            }
            if ( $customer->get_billing_email() ) {
                $userData->setEmail( $customer->get_billing_email() );
            }
            if ( $customer->get_billing_phone() ) {
                $userData->setPhone( $customer->get_billing_phone() );
            }
            if ( $customer->get_billing_country() ) {
                $userData->setCountryCode( strtolower( $customer->get_billing_country() ) );
            }
            if ( $customer->get_billing_city() ) {
                $userData->setCity( $customer->get_billing_city() );
            }
            if ( $customer->get_billing_state() ) {
                $userData->setState( $customer->get_billing_state() );
            }
            if ( $customer->get_billing_postcode() ) {
                $userData->setZipCode( $customer->get_billing_postcode() );
            }
        }

        if ( $user->ID ) {
            
			$user_firstname = $user->get( 'user_firstname' );
			$user_lastname = $user->get( 'user_lastname' );
			$user_phone = $user->get( 'billing_phone' );

			if ( ! empty( $user_phone ) ) {
				$user_phone = preg_replace('/[^\d]/', '', $user_phone);
				if (strlen($user_phone) === 11 && strpos($user_phone, '01') === 0) {
					$user_phone = '88' . $user_phone;
				}
			}

            

            if ( \fmb_engine\Facebook\isWooCommerceActive() ) {
                
				if ( empty( $user_firstname ) ) {
					$user_firstname = $user->get( 'billing_first_name' );
				}

                
				if ( empty( $user_lastname ) ) {
					$user_lastname = $user->get( 'billing_last_name' );
				}

                if($user->get('billing_phone')) {
                    $norm_phone = preg_replace('/[^\d]/', '', $user->get('billing_phone'));
                    if(strlen($norm_phone) === 11 && strpos($norm_phone, '01') === 0) $norm_phone = '88'.$norm_phone;
                    $userData->setPhone($norm_phone);
                }
                if($user->get('billing_city'))
                    $userData->setCity($user->get('billing_city'));
                if($user->get('billing_state'))
                    $userData->setState($user->get('billing_state'));
                
                $country = $user->get('billing_country') ?: $user->get('shipping_country');
                if($country)
                    $userData->setCountryCode(strtolower($country));
                if($user->get('billing_postcode')) {
                    $userData->setZipCode($user->get('billing_postcode'));
                }
            }
			$user_persistence_data = get_persistence_user_data( $user->get( 'user_email' ), $user_firstname, $user_lastname, $user_phone );
            if(\fmb_engine\Facebook\EventsManager::isTrackExternalId()){
                if (!empty(\fmb_engine\Facebook\FacebookRuntime()->get_of_id())) {
                    $userData->setExternalId(\fmb_engine\Facebook\FacebookRuntime()->get_of_id());
                }
            }

			$login_id = get_user_meta( $user->ID, '_socplug_social_id_Facebook', true );
			if ( !empty( $login_id ) && apply_filters( 'orderflow_send_meta_id', true ) ) {
				$userData->setFbLoginId( $login_id );
			}
        } else {
			$user_persistence_data = get_persistence_user_data( '', '', '', '' );
            if (\fmb_engine\Facebook\EventsManager::isTrackExternalId() && isset($_COOKIE['orderflow_id'])) {
                $userData->setExternalId($_COOKIE['orderflow_id']);
            }
        }

		if ( !empty( $user_persistence_data[ 'fn' ] ) ) $userData->setFirstName( $user_persistence_data[ 'fn' ] );
		if ( !empty( $user_persistence_data[ 'ln' ] ) ) $userData->setLastName( $user_persistence_data[ 'ln' ] );
		if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->setEmail( $user_persistence_data[ 'em' ] );
		if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->setPhone( $user_persistence_data[ 'tel' ] );

        return $userData;
    }

    static function paramsToCustomData($data) {

        if(isset($data['contents']) && is_array($data['contents'])) {
            $contents = array();
            foreach ($data['contents'] as $c) {
                $contents[] = new Content([
                    'product_id' => $c['id'],
                    'quantity'  => $c['quantity']
                ]);
            }
            $data['contents'] = $contents;
        } else {
            $data['contents'] = array();
        }

        
        $currency = null;
        if(isset($data['currency']) && !empty($data['currency'])) {
            $currency = strtoupper($data['currency']);
            $data['currency'] = $currency;
        }

        $customData = new CustomData($data);
        
        
        if($currency !== null) {
            $customData->setCurrency($currency);
        }
        
        $customProperties = array();


        if(isset($data['category_name'])) {
            $customData->setContentCategory($data['category_name']);
        }

        
        
        $custom_values = ['event_action','download_type','download_name','download_url','target_url','text','trigger','traffic_source','plugin','user_role','event_url','page_title',"post_type",'post_id','categories','tags','video_type',
            'video_id','video_title','event_trigger','link_type','tag_text',"URL",
            'form_id','form_class','form_submit_label','transactions_count','average_order',
            'shipping_cost','tax','total','shipping','coupon_used','post_category','landing_page',
            'event_day','event_hour','event_month',
            'element_classes','element_id','scroll_depth_percent','element_selector','time_spent_seconds'];

        foreach ($custom_values as $val) {
            if(isset($data[$val])){
                $customProperties[$val] = $data[$val];
            }
        }

        $customData->setCustomProperties($customProperties);
        return $customData;
    }

}