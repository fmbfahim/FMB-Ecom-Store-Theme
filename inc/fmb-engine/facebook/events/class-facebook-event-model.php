<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventTypes {
	public static $DYNAMIC = 'dyn';
	public static $STATIC  = 'static';
	public static $TRIGGER = 'trigger';
}

class SingleEvent {

	protected $id;
	protected $type;
	protected $category;
	public $args   = null;
	public $params = array();

	public $payload = array(
		'delay' => 0,
	);

	private $ecommerceParamArray = array(
		'currency',
		'value',
		'items',
		'tax',
		'shipping',
		'coupon',
		'affiliation',
		'fees',
		'new_customer',
		'transaction_id',
		'total_value',
		'ecomm_prodid',
		'ecomm_pagetype',
		'ecomm_totalvalue',
	);

	public function __construct( $id, $type, $category = '' ) {
		$this->id                = $id;
		$this->type              = $type;
		$this->category          = $category;
		$this->payload['type'] = $type;
	}

	public function getId() {
		return $this->id;
	}

	public function getType() {
		return $this->type;
	}

	public function getCategory() {
		return $this->category;
	}

	public function addParams( $data ) {
		if ( is_array( $data ) ) {
			if ( isset( $this->params['triggerType']['type'] ) && $this->params['triggerType']['type'] === 'ecommerce' ) {
				foreach ( $data as $key => $value ) {
					if ( in_array( $key, $this->ecommerceParamArray, true ) ) {
						$this->params['ecommerce'][ $key ] = $data[ $key ];
					} else {
						$this->params[ $key ] = $data[ $key ];
					}
				}
			} else {
				$this->params = array_merge( $this->params, $data );
			}
		}
	}

	public function addPayload( $data ) {
		if ( is_array( $data ) ) {
			$this->payload = array_merge( $this->payload, $data );
		}
	}

	public function getData() {
		$data                  = $this->payload;
		$data['params']        = sanitizeParams( $this->params );
		$data['e_id']          = $this->getId();
		$data['delay']         = isset( $this->payload['delay'] ) ? $this->payload['delay'] : 0;
		$data['ids']           = isset( $this->payload['ids'] ) ? $this->payload['ids'] : array();
		$data['hasTimeWindow'] = isset( $this->payload['hasTimeWindow'] ) ? $this->payload['hasTimeWindow'] : false;
		$data['timeWindow']    = isset( $this->payload['timeWindow'] ) ? $this->payload['timeWindow'] : 0;
		$data['pixelIds']      = isset( $this->payload['pixelIds'] ) ? $this->payload['pixelIds'] : array();
		$data['eventID']       = isset( $this->payload['eventID'] ) ? $this->payload['eventID'] : '';
		$data['woo_order']     = isset( $this->payload['woo_order'] ) ? $this->payload['woo_order'] : '';

		return $data;
	}

	public function getPayloadValue( $key ) {
		if ( isset( $this->payload[ $key ] ) ) {
			return $this->payload[ $key ];
		}
		return null;
	}

	public function removeParam( $key ) {
		if ( isset( $this->params[ $key ] ) ) {
			unset( $this->params[ $key ] );
		}
	}

	public function removePayload( $key ) {
		if ( isset( $this->payload[ $key ] ) ) {
			unset( $this->payload[ $key ] );
		}
	}
}

final class EventIdGenerator {

	public static function guidv4() {
		$data = openssl_random_pseudo_bytes( 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
