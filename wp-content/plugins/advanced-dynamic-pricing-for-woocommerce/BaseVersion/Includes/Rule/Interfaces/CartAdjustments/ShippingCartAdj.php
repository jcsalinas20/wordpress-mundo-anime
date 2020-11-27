<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\CartAdjustments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface ShippingCartAdj {
	const SHIPPING_CARTADJ_VALUE = 'shipping_cartadj_value';

	/**
	 * @param float $shipping_cartadj_value
	 */
	public function setShippingCartAdjValue( $shipping_cartadj_value );

	/**
	 * @return float
	 */
	public function getShippingCartAdjValue();
}
