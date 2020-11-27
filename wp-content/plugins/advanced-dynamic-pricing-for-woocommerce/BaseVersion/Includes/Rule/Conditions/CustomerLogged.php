<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Cart\CartCustomerHelper;
use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\BinaryCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CustomerLogged extends AbstractCondition implements BinaryCondition {
	const BIN_YES = 'yes';
	const BIN_NO = 'no';
	
	const AVAILABLE_COMP_METHODS = array(
		self::BIN_YES,
		self::BIN_NO,
	);

	/**
	 * @var string
	 */
	protected $comparison_value;

	public function check( $cart ) {
		$context            = $cart->get_context()->getGlobalContext();
		$cartCustomerHelper = new CartCustomerHelper( $context, $cart->get_context()->getCustomer() );
		$comparison_value = $this->comparison_value;

		return $cartCustomerHelper->isLoggedIn() === $comparison_value;
	}

	public static function getType() {
		return 'customer_logged';
	}

	public static function getLabel() {
		return __( 'Is logged in', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/customer/is-logged-in.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CUSTOMER;
	}
	
	/**
	 * @param string|bool $comparison_value
	 */
	public function setComparisonBinValue( $comparison_value ) {
		if ( in_array( $comparison_value, self::AVAILABLE_COMP_METHODS ) ) {
			$this->comparison_value = 'yes' === $comparison_value;
		} elseif ( $comparison_value === true ) {
			$this->comparison_value = true;
		} else {
			$this->comparison_value = null;
		}
	}

	public function getComparisonBinValue()
	{
		return $this->comparison_value;
	}
	
	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_value );
	}
}