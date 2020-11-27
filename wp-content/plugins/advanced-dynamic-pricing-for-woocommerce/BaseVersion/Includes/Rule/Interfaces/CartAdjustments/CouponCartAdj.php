<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\CartAdjustments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface CouponCartAdj {
	const COUPON_VALUE_KEY = 'coupon_value';
	const COUPON_CODE_KEY = 'coupon_code';
	const COUPON_MAX_DISCOUNT = 'coupon_max_discount';

	/**
	 * @param float $coupon_value
	 */
	public function setCouponValue( $coupon_value );

	/**
	 * @param string $coupon_code
	 */
	public function setCouponCode( $coupon_code );

	/**
	 * @return float
	 */
	public function getCouponValue();

	/**
	 * @return string
	 */
	public function getCouponCode();
}
