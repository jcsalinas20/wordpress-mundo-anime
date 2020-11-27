<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\Interfaces\RuleCondition;
use ADP\BaseVersion\Includes\Traits\Comparison;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class AbstractCondition implements RuleCondition {
	use Comparison;

	protected $amount_indexes = array();
	protected $has_product_dependency = false;

	public function __construct() {

	}

	/**
	 * @param float $rate
	 */
	public function multiplyAmounts( $rate ) {
    }

    public function check( $cart ) {
		return false;
	}

	public function get_involved_cart_items() {
		return null;
	}

	public function match( $cart ) {
		return $this->check( $cart );
	}

	public function has_product_dependency() {
		return $this->has_product_dependency;
	}

	public function get_product_dependency() {
		return false;
    }

    public function translate( $language_code ) {
		return;
	}
}
