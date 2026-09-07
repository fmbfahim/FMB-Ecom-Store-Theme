<?php

namespace fmb_engine\Facebook\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}


if ( ! function_exists( 'fmb_engine\Facebook\Helpers\get_persistence_user_data' ) ) {
    function get_persistence_user_data( $email = '', $first_name = '', $last_name = '', $phone = '' ) {
        return array(
            'em' => sanitize_email( $email ),
            'fn' => sanitize_text_field( $first_name ),
            'ln' => sanitize_text_field( $last_name ),
            'tel' => sanitize_text_field( $phone ),
        );
    }
}

if ( ! function_exists( 'fmb_engine\Facebook\Helpers\isWPMLActive' ) ) {
    function isWPMLActive() {
        return defined( 'ICL_SITEPRESS_VERSION' );
    }
}



function getAdvancedMatchingParams() {

	$params = array();
	$user = wp_get_current_user();

	if ( $user->ID ) {
		$user_persistence_data = get_persistence_user_data( $user->get( 'user_email' ), $user->get( 'user_firstname' ), $user->get( 'user_lastname' ), '' );
	} else {
		$user_persistence_data = get_persistence_user_data( '', '', '', '' );
	}

	if ( !empty( $user_persistence_data[ 'fn' ] ) ) $params[ 'fn' ] = $user_persistence_data[ 'fn' ];
	if ( !empty( $user_persistence_data[ 'ln' ] ) ) $params[ 'ln' ] = $user_persistence_data[ 'ln' ];
	if ( !empty( $user_persistence_data[ 'em' ] ) ) $params[ 'em' ] = $user_persistence_data[ 'em' ];
	if ( !empty( $user_persistence_data[ 'tel' ] ) ) $params[ 'ph' ] = $user_persistence_data[ 'tel' ];

	


	if ( \fmb_engine\Facebook\isWooCommerceActive() ) {

		 
		if ( empty( $params['fn'] ) ) {
			$params['fn'] = $user->get( 'billing_first_name' );
		}

		 
		if ( empty( $params['ln'] ) ) {
			$params['ln'] = $user->get( 'billing_last_name' );
		}

		$user_persistence_data = get_persistence_user_data( '', $params['fn'], $params['ln'], $user->get('billing_phone') );
		if ( !empty( $user_persistence_data[ 'fn' ] ) ) $params[ 'fn' ] = $user_persistence_data[ 'fn' ];
		if ( !empty( $user_persistence_data[ 'ln' ] ) ) $params[ 'ln' ] = $user_persistence_data[ 'ln' ];
		if ( !empty( $user_persistence_data[ 'tel' ] ) ) $params[ 'ph' ] = $user_persistence_data[ 'tel' ];

		$params['ct'] = $user->get( 'billing_city' );
		$params['st'] = $user->get( 'billing_state' );
		$params['country'] = $user->get( 'billing_country' );
		$params['zp'] = $user->get( 'billing_postcode' );

		


		if ( \fmb_engine\Facebook\FacebookRuntime()->woo_is_order_received_page() && isset( $_REQUEST['key'] ) && $_REQUEST['key'] != '' ) {
			$order_key = sanitize_key( wp_unslash( $_REQUEST['key'] ) );
			$order_id  = \fmb_engine\Facebook\orderflow_fb_resolve_order_id_from_order_key( $order_key );
			$order     = wc_get_order( $order_id );

			if ( $order ) {
				$user_persistence_data = get_persistence_user_data( $order->get_billing_email(), $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_billing_phone() );
				$params                = array(
					'em'      => $user_persistence_data['em'],
					'ph'      => $user_persistence_data['tel'],
					'fn'      => $user_persistence_data['fn'],
					'ln'      => $user_persistence_data['ln'],
					'ct'      => $order->get_billing_city(),
					'st'      => $order->get_billing_state(),
					'country' => $order->get_billing_country(),
					'zp'      => $order->get_billing_postcode(),
				);
			}
		}
	}

    if(\fmb_engine\Facebook\EventsManager::isTrackExternalId()){
        if($user && $user->get( 'external_id' )){
            $params['external_id'] = $user->get( 'external_id' );
        } elseif (\fmb_engine\Facebook\FacebookRuntime()->get_of_id()) {
            $params['external_id'] = \fmb_engine\Facebook\FacebookRuntime()->get_of_id();
        }
    }
	$sanitized = array();

	foreach ( $params as $key => $value ) {

		if ( ! empty( $value ) ) {
			$sanitized[ $key ] = sanitizeAdvancedMatchingParam( $value, $key );
		}

	}

	return $sanitized;

}

function sanitizeAdvancedMatchingParam( $value, $key ) {
    
    
    if ( function_exists( 'mb_strtolower' ) ) {
        $value = mb_strtolower( $value );
    } else {
        $value = strtolower( $value );
    }

	if ( $key == 'ph' ) {
		$value = preg_replace( '/\D/', '', $value );
	} elseif ( $key == 'em' ) {
		$value = preg_replace( '/[^a-z0-9._+-@]+/i', '', $value );
	} else {
		$value = preg_replace( '/[^a-z]/', '', $value );
	}

	return $value;

}



function getFacebookWooProductContentId( $product_id ) {

	if(isWPMLActive() && \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_wpml_unified_id' )) {
		$wpml_product_id = apply_filters('wpml_original_element_id', NULL, $product_id);
		if ($wpml_product_id) {
			$product_id = $wpml_product_id;
		}
	}

    if ( \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_content_id' ) == 'product_sku' ) {
        $product = wc_get_product( $product_id );
        if ($product && $product->is_type( 'variation' ) ) {
            $content_id = $product->get_sku();
            if ( empty( $content_id ) ) {
                $parent_id = $product->get_parent_id();
                $parent_product = wc_get_product( $parent_id );
                if($parent_product){
                    $content_id = $parent_product->get_sku();
                }
                if ( empty( $content_id ) ) {
                    $content_id = $product_id;
                }
            }
        } elseif($product) {
            $content_id = $product->get_sku();
            if ( empty( $content_id ) ) {
                $content_id = $product_id;
            }
        } else {
            $content_id = $product_id;
        }
    } else {
        $content_id = $product_id;
    }

	$prefix = \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_content_id_prefix' );
	$suffix = \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_content_id_suffix' );

	$value = $prefix . $content_id . $suffix;
	$value = array( $value );

	 
	if ( ! isDefaultWooContentIdLogic() ) {

		$product = wc_get_product($product_id);

		if ( ! $product ) {
			return $value;
		}


        $ids = array(
            get_fb_plugin_retailer_id($product)
		);

		$value = array_values( array_filter( $ids ) );
		
	}

	return $value;

}
function get_fb_plugin_retailer_id( $woo_product ) {
    if(!$woo_product) return "";
    $woo_id = $woo_product->get_id();

    
    
    return $woo_product->get_sku() ? $woo_product->get_sku() . '_' .
        $woo_id : 'wc_post_id_'. $woo_id;
}
function getFacebookWooCartItemId( $item ) {

	if ( ! \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_variable_as_simple' ) && isset( $item['variation_id'] ) && $item['variation_id'] !== 0 ) {
		$product_id = $item['variation_id'];
	} else {
		$product_id = $item['product_id'];
	}

	 
	if ( ! isDefaultWooContentIdLogic() ) {

		if ( isset( $item['variation_id'] ) && $item['variation_id'] !== 0 ) {
			$product_id = $item['variation_id'];
		} else {
			$product_id = $item['product_id'];
		}

	}

	return $product_id;

}



function getWooCustomAudiencesOptimizationParams( $post_id ) {

	$post = get_post( $post_id );

	$params = array(
		'content_name'  => '',
		'category_name' => '',
	);

	if ( ! $post ) {
		return $params;
	}

	if ( $post->post_type == 'product_variation' ) {
		$post_id = $post->post_parent; 
	}

	$params['content_name'] = $post->post_title;
	$params['category_name'] = implode( ', ', \fmb_engine\Facebook\getObjectTerms( 'product_cat', $post_id ) );

	return $params;

}

function getWooSingleAddToCartParams( $_product_id, $qty = 1 ) {

	$params = array();
    $product = wc_get_product($_product_id);
    if(!$product) {
        return array();
    }
    
    $product_ids = array();
    $isGrouped = $product->get_type() == "grouped";
    if($isGrouped) {
        $product_ids = $product->get_children();
    } else {
        $product_ids[] = $_product_id;
    }
    $params['content_type'] = 'product';
    $params['content_ids']  = array();
    $params['contents'] = array();




	 
	$params['tags'] = implode( ', ', \fmb_engine\Facebook\getObjectTerms( 'product_tag', $_product_id ) );
	$params = array_merge( $params, getWooCustomAudiencesOptimizationParams( $_product_id ) );
	 
	if ( empty( $params['content_name'] ) && $product ) {
		$params['content_name'] = method_exists( $product, 'get_name' ) ? $product->get_name() : get_the_title( $_product_id );
	}

	 
	$params['currency'] = strtoupper(get_woocommerce_currency());
	
	 
	if ( \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_enabled' ) ) {
		 
		$value_option = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_option' );
		$global_value = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_global', 0 );
		$params['value'] = \fmb_engine\Facebook\getWooEventValue( $value_option, $global_value, 100, $_product_id, $qty );
	} else {
		 
		$params['value'] = \fmb_engine\Facebook\getWooProductPriceToDisplay( $_product_id, $qty );
	}

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if(!$product) continue;
        if($product->get_type() == "variable" && $isGrouped) {
            continue;
        }
        $content_id = getFacebookWooProductContentId( $product_id );
        $params['content_ids'] = array_merge($params['content_ids'],$content_id);
        
        if ( isDefaultWooContentIdLogic() ) {

            
            $params['contents'][] = array(
                'id'         => (string) reset( $content_id ),
                'quantity'   => $qty,
                
            );
        }
    }

	return $params;

}



function woo_cart_basket_params_core() {
	$params             = array();
	$params['content_type'] = 'product';

	$content_ids        = array();
	$content_names      = array();
	$content_categories = array();
	$tags               = array();
	$contents           = array();

	foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {

		$product_id = getFacebookWooCartItemId( $cart_item );
		$_product_id = empty( $cart_item['variation_id'] ) ? $cart_item['product_id'] : $cart_item['variation_id'];
		$content_id = getFacebookWooProductContentId( $product_id );

		$content_ids = array_merge( $content_ids, $content_id );

		$custom_audiences = getWooCustomAudiencesOptimizationParams( $product_id );
		$content_name     = ! empty( $custom_audiences['content_name'] ) ? $custom_audiences['content_name'] : '';
		if ( empty( $content_name ) ) {
			$_product = wc_get_product( $_product_id );
			if ( $_product ) {
				$content_name = method_exists( $_product, 'get_name' ) ? $_product->get_name() : get_the_title( $_product_id );
			}
		}
		$content_names[]      = $content_name;
		$content_categories[] = $custom_audiences['category_name'];

		$cart_item_tags = \fmb_engine\Facebook\getObjectTerms( 'product_tag', $product_id );
		if ( empty( $cart_item_tags ) && ! empty( $cart_item['variation_id'] ) && ! empty( $cart_item['product_id'] ) ) {
			$cart_item_tags = \fmb_engine\Facebook\getObjectTerms( 'product_tag', $cart_item['product_id'] );
		}
		$tags = array_merge( $tags, $cart_item_tags );

		$contents[] = array(
			'id'       => (string) reset( $content_id ),
			'quantity' => $cart_item['quantity'],
		);
	}

	$params['content_ids']   = $content_ids;
	$params['content_name']  = implode( ', ', $content_names );
	$params['category_name'] = implode( ', ', $content_categories );

	if ( isDefaultWooContentIdLogic() ) {
		$params['contents'] = $contents;
	}

	$tags           = array_unique( $tags );
	$tags           = array_slice( $tags, 0, 100 );
	$params['tags'] = implode( ', ', $tags );

	return $params;
}



function getWooCartParamsForInitiateCheckout() {
	$params                = woo_cart_basket_params_core();
	$params['num_items']   = WC()->cart->get_cart_contents_count();
	$params['subtotal']    = \fmb_engine\Facebook\getWooCartSubtotal();
	$value_option          = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_initiate_checkout_value_option' );
	$global_value          = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_initiate_checkout_value_global', 0 );
	$params['value']       = \fmb_engine\Facebook\getWooEventValueCart( $value_option, $global_value );
	$params['currency']    = strtoupper( get_woocommerce_currency() );
	return $params;
}



function getWooCartParamsForCartAddToCart() {
	$params = woo_cart_basket_params_core();
	if ( \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_enabled' ) ) {
		$value_option       = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_option' );
		$global_value       = \fmb_engine\Facebook\FacebookRuntime()->getOption( 'woo_add_to_cart_value_global', 0 );
		$params['value']    = \fmb_engine\Facebook\getWooEventValueCart( $value_option, $global_value );
		$params['currency'] = strtoupper( get_woocommerce_currency() );
	}
	return $params;
}

function isFacebookForWooCommerceActive() {
	return class_exists( 'WC_Facebookcommerce' );
}

function isDefaultWooContentIdLogic() {
	return ! isFacebookForWooCommerceActive() || \fmb_engine\Facebook\Facebook::instance()->getOption( 'woo_content_id_logic' ) != 'facebook_for_woocommerce';
}

