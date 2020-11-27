<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Cart\CartTotals;
use ADP\BaseVersion\Includes\Cart\Structures\CartItem;
use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\ValueComparisonCondition;
use Automattic\WooCommerce\Blocks\RestApi\StoreApi\Controllers\Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartSubtotal extends AbstractCondition implements ValueComparisonCondition {
	const LT = '<';
	const LTE = '<=';
	const MT = '>';
	const MTE = '>=';

	const AVAILABLE_COMP_METHODS = array(
		self::LT,
		self::LTE,
		self::MT,
		self::MTE,
	);

	protected $amount_indexes = array( 'comparison_value' );
	/**
	 * @var string
	 */
	protected $comparison_method;

	/**
	 * @var float|integer
	 */
	protected $comparison_value;

	public function check( $cart ) {
		$cartTotals     = new CartTotals( $cart );
		$itemsSubtotals = $cartTotals->getSubtotal();

		$comparison_value  = (float) $this->comparison_value;
		$comparison_method = $this->comparison_method;

		return $this->compare_values( $itemsSubtotals, $comparison_value, $comparison_method );
	}

	public static function getType() {
		return 'cart_subtotal';
	}

	public static function getLabel() {
		return __( 'Subtotal (exc. VAT)', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/cart/subtotal.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CART;
	}

	/**
	 * @param string $comparison_method
	 */
	public function setValueComparisonMethod( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	public function getValueComparisonMethod()
	{
		return $this->comparison_method;
	}

	/**
	 * @param float|integer $comparison_value
	 */
	public function setComparisonValue( $comparison_value ) {
		is_numeric( $comparison_value ) ? $this->comparison_value = (float)$comparison_value : $this->comparison_value = null;
	}

	public function getComparisonValue()
	{
		return $this->comparison_value;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_value );
	}

	public function multiplyAmounts( $rate ) {
		$this->comparison_value *= (float)$rate;
	}
}
