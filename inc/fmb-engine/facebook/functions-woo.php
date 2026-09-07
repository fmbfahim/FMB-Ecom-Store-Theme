<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

function isWooCommerceVersionGte( $version ) {

	if ( defined( 'WC_VERSION' ) && WC_VERSION ) {
		return version_compare( WC_VERSION, $version, '>=' );
	} else if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) {
		return version_compare( WOOCOMMERCE_VERSION, $version, '>=' );
	} else {
		return false;
	}

}



function wooProductIsType( $product, $type ) {

	if ( isWooCommerceVersionGte( '2.7' ) ) {
		return $type == $product->is_type( $type );
	} else {
		return $product->product_type == $type;
	}

}
function wooIsRequestContainOrderId() {
    global $wp;
    return  isset( $_REQUEST['key'] )  && $_REQUEST['key'] != ""
        || !empty($wp->query_vars['order-received'])
        || isset( $_REQUEST['referenceCode'] )  && $_REQUEST['referenceCode'] != ""
        || isset( $_REQUEST['ref_venta'] )  && $_REQUEST['ref_venta'] != ""
        || !empty( $_REQUEST['wcf-order'] );
}



function orderflow_fb_resolve_order_id_from_order_key( $order_key ) {
	$order_key = is_string( $order_key ) ? $order_key : '';
	if ( $order_key === '' ) {
		return 0;
	}

	$cache_key = 'order_id_' . $order_key;
	$order_id  = (int) get_transient( $cache_key );

	global $wp;
	if ( $order_id <= 0 && FacebookRuntime()->woo_is_order_received_page() && ! empty( $wp->query_vars['order-received'] ) ) {
		$order_id = absint( $wp->query_vars['order-received'] );
		if ( $order_id ) {
			set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
		}
	}

	if ( $order_id <= 0 && function_exists( 'wc_get_order_id_by_order_key' ) ) {
		$order_id = (int) wc_get_order_id_by_order_key( $order_key );
		if ( $order_id ) {
			set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
		}
	}

	return $order_id > 0 ? $order_id : 0;
}
function getWooProductPriceToDisplay( $product_id, $qty = 1 ) {

	if ( ! $product = wc_get_product( $product_id ) ) {
		return 0;
	}


    $productPrice = "";

    
    if($product->get_type() == "variable") {
        $prices = $product->get_variation_prices( true );
        if(empty( $prices['price'] )) {
            $productPrice = $product->get_price();
        } else {
            $variation_id = key($prices['price']); 
            $variation = wc_get_product($variation_id); 

            if ($variation && is_a($variation, 'WC_Product')) { 
                $productPrice = current( $prices['price'] ); 
            } else {
                
                
                $productPrice = $product->get_price(); 
            }
        }

    } else {
        $productPrice = $product->get_price();
    }

    return formatPriceTrimZeros((float) wc_get_price_to_display( $product, array( 'qty' => $qty,'price'=>$productPrice ) ));
}


function getWooEventCartTotal($event) {

    return getWooEventCartSubtotal($event);
}


function getWooEventCartSubtotal($event) {
    $subTotal = 0;
    $include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';

    foreach ($event->args['products'] as $product) {
        $subTotal += $product['subtotal'];
        if($include_tax) {
            $subTotal += $product['subtotal_tax'];
        }
    }
    return orderflow_round( $subTotal );
}
function getWooCartSubtotal() {
    WC()->cart->calculate_totals();
	 
	$include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';

	if ( $include_tax ) {

		if ( isWooCommerceVersionGte( '3.2.0' ) ) {
			$subtotal = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		} else {
			$subtotal = WC()->cart->subtotal;
		}

	} else {

		if ( isWooCommerceVersionGte( '3.2.0' ) ) {
			$subtotal = (float) WC()->cart->get_subtotal();
		} else {
			$subtotal = WC()->cart->subtotal_ex_tax;
		}

	}

	return $subtotal;

}

function getWooEventValue( $valueOption, $global, $percent, $product_id,$qty ) {

    $product = wc_get_product($product_id);

    if(!$product) return 0;

    if($valueOption == 'cog' && isPixelCogActive()) {

        $args = array( 'qty'   => $qty, 'price' => $product->get_price());
        if(get_option( '_pixel_cog_tax_calculating')  == 'no') {
            $amount = wc_get_price_excluding_tax($product, $args);
        } else {
            $amount = wc_get_price_including_tax($product,$args);
        }

        $cog = getAvailableProductCog($product);

        if ($cog['val']) {
            if ($cog['type'] == 'fix') {
                $value = round((float)$amount - (float)$cog['val'], 2);
            } else {
                $value = round((float)$amount - ((float)$amount * (float)$cog['val'] / 100), 2);
            }
        } else {
            $value = (float)$amount;
        }
        return formatPriceTrimZeros($value);
    }

    $amount = getWooProductPriceToDisplay( $product_id, $qty );

    switch ( $valueOption ) {
        case 'global': $value = $global; break;
        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;
        default:$value = (float)$amount;
    }

    return formatPriceTrimZeros($value);

}


function getWooEventValueOrder( $valueOption, $order, $global, $percent = 100 ) {

    
    $has_partial = $order->get_meta('_has_partial_payment');
    $amount = 0;

    if ($has_partial === 'yes') {
        
        $partial_amount = $order->get_meta('_partial_payment_amount');
        $remaining_amount = $order->get_meta('_remaining_payment_amount');
        $amount = (float) $partial_amount + (float) $remaining_amount;
    } else {
        
        $amount = (float) $order->get_total();
    }
    
    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'cog':
            $cog_value = getAvailableProductCogOrder($order);
            ($cog_value !== '') ? $value = (float) round($cog_value, 2) : $value = (float) $amount;
            if ( !isPixelCogActive() ) $value = (float) $amount;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);

}

function get_fees($order){
    $fees = $order->get_fees();
    $fee_amount = 0;

    foreach ($fees as $fee) {
        $fee_amount += $fee->get_total();
    }
    if($fee_amount > 0){
        return $fee_amount;
    }

    return 0;
}
function getWooEventValueCart( $valueOption, $global, $percent = 100 ) {

    if($valueOption == 'cog' && isPixelCogActive()) {
        $cog_value = getAvailableProductCogCart();
        if($cog_value !== '')
            return (float) round($cog_value, 2) ;

        if ( get_option( '_pixel_cog_tax_calculating')  == 'no' ) {
            return WC()->cart->cart_contents_total;
        }

        return WC()->cart->cart_contents_total + WC()->cart->tax_total;
    }


    $amount = $params['value'] = WC()->cart->subtotal;

    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);
}

function wooGetOrderIdFromRequest() {
    global $wp;
    if(isset( $_REQUEST['key'] )  && $_REQUEST['key'] != "") {
        $order_key = sanitize_key($_REQUEST['key']);
        $cache_key = 'order_id_' . $order_key;
        $order_id = get_transient( $cache_key );
        if (\fmb_engine\Facebook\FacebookRuntime()->woo_is_order_received_page() && empty($order_id) && $wp->query_vars['order-received']) {

            $order_id = absint( $wp->query_vars['order-received'] );
            if ($order_id) {
                set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
            }
        }
        if ( empty($order_id) ) {
            $order_id = (int) wc_get_order_id_by_order_key( $order_key );
            set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
        }
        return $order_id;
    }
    if(\fmb_engine\Facebook\FacebookRuntime()->woo_is_order_received_page() && !empty($wp->query_vars['order-received'])){
        $cache_key = 'order_id_' . $wp->query_vars['order-received'];
        $order_id = get_transient( $cache_key );
        if (empty($order_id)) {
            $order_id = absint( $wp->query_vars['order-received'] );
            if ($order_id) {
                set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
            }
        }
        return $order_id;
    }
    if(isset( $_REQUEST['referenceCode'] )  && $_REQUEST['referenceCode'] != "") {
        return (int)$_REQUEST['referenceCode'];
    }
    if(isset( $_REQUEST['ref_venta'] )  && $_REQUEST['ref_venta'] != "") {
        return (int)$_REQUEST['ref_venta'];
    }
    if(!empty($_REQUEST['wcf-order'])) {
        return (int)$_REQUEST['wcf-order'];
    }
    return -1;
}



function isWooUseHPStorage() {
    if(class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )) {
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    return false;

}