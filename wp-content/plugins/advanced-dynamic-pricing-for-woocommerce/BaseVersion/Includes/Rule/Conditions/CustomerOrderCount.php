<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Cart\CartCustomerHelper;
use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\ValueComparisonCondition;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\TimeRangeCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CustomerOrderCount extends AbstractCondition implements ValueComparisonCondition, TimeRangeCondition {
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

	/**
	 * @var string
	 */
	protected $comparison_method;
	/**
	 * @var integer
	 */
	protected $comparison_value;
	/**
	 * @var string
	 */
	protected $time_range;

	public function check( $cart ) {
		$time_range         = $this->time_range;
		$comparison_method  = $this->comparison_method;
		$comparison_value   = (int) $this->comparison_value;
		$context            = $cart->get_context()->getGlobalContext();
		$cartCustomerHelper = new CartCustomerHelper( $context, $cart->get_context()->getCustomer() );
		$order_count        = $cartCustomerHelper->getOrderCountAfter( $time_range );

		return $this->compare_values( $order_count, $comparison_value, $comparison_method );
	}

	public static function getType() {
		return 'customer_order_count';
	}

	public static function getLabel() {
		return __( 'Order count', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/customer/order-count.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CUSTOMER;
	}

	/**
	 * @param string $comparison_method
	 */
	public function setValueComparisonMethod( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	/**
	 * @param integer $comparison_value
	 */
	public function setComparisonValue( $comparison_value ) {
		$this->comparison_value =  (int)$comparison_value;
	}

	public function getValueComparisonMethod()
	{
		return $this->comparison_method;
	}

	public function getComparisonValue()
	{
		return $this->comparison_value;
	}

	/**
	 * @param string $time_range
	 */
	public function setTimeRange ( $time_range ) {
		gettype($time_range) === 'string' ? $this->time_range = $time_range : $this->time_range = null;
	}

	public function getTimeRange()
	{
		return $this->time_range;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_value ) AND !is_null( $this->time_range );
	}
}