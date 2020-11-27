<?php

namespace ADP\BaseVersion\Includes\Rule\CartAdjustments;

use ADP\BaseVersion\Includes\Cart\Structures\CouponCart;
use ADP\BaseVersion\Includes\Rule\CartAdjustmentsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\CartAdjustment;
use ADP\BaseVersion\Includes\Rule\Interfaces\CartAdjustments\CouponCartAdj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class DiscountAmount extends AbstractCartAdjustment implements CouponCartAdj, CartAdjustment {
	/**
	 * @var float
	 */
	protected $coupon_value;

	/**
	 * @var string
	 */
	protected $coupon_code;

	public static function getType() {
		return 'discount__amount';
	}

	public static function getLabel() {
		return __( 'Fixed discount, once', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'cart_adjustments/discount.php';
	}

	public static function getGroup() {
		return CartAdjustmentsLoader::GROUP_DISCOUNT;
	}

	public function __construct() {
		$this->amount_indexes = array( 'coupon_value' );
	}

	/**
	 * @param float $coupon_value
	 */
	public function setCouponValue( $coupon_value ) {
		$this->coupon_value = $coupon_value;
	}

	/**
	 * @param string $coupon_code
	 */
	public function setCouponCode( $coupon_code ) {
		$this->coupon_code = $coupon_code;
	}

	public function getCouponValue()
	{
		return $this->coupon_value;
	}

	public function getCouponCode()
	{
		return $this->coupon_code;
	}

	public function isValid() {
		return isset( $this->coupon_value ) OR isset( $this->coupon_code );
	}

	public function applyToCart( $rule, $cart ) {
		$context    = $cart->get_context()->getGlobalContext();
		$couponCode = ! empty( $this->coupon_code ) ? $this->coupon_code : $context->get_option( 'default_discount_name' );

		$cart->addCoupon( new CouponCart( $context, CouponCart::TYPE_FIXED_VALUE, $couponCode, $this->coupon_value, $rule->getId() ) );
	}
}
