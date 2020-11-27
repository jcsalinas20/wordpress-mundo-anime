<?php

namespace ADP\BaseVersion\Includes\Cart;

use ADP\BaseVersion\Includes\Rule\Interfaces\Rule;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class RulesCollection {
	/** @var Rule[] */
	protected $rules;

	/**
	 * @param Rule[] $rules
	 */
	public function __construct( $rules ) {
		$this->rules = $rules;
	}

	/**
	 * @return Rule[]
	 */
	public function getRules() {
		return $this->rules;
	}


	public function count() {
		return count( $this->rules );
	}

	public function isEmpty() {
		return empty( $this->rules );
	}

	/**
	 * @return Rule|null
	 */
	public function getFirst() {
		return count( $this->rules ) ? reset( $this->rules ) : null;
	}

	protected function getRule( $pos ) {
		$rule = null;

		if ( isset( $this->rules[ $pos ] ) ) {
			$rule = $this->rules[ $pos ];
		} else {
			throw new Exception( 'Invalid pos number for collection of rules' );
		}

		return $rule;
	}

	public function getExact( $rule_ids ) {
		$filtered_rules    = array();
		$rule_ids = (array) $rule_ids;

		foreach ( $this->rules as $rule ) {
			/**
			 * @var $rule Rule
			 */
			if ( in_array( $rule->getId(), $rule_ids ) ) {
				$filtered_rules[] = $rule;
			}
		}

		return new self( $filtered_rules );
	}

	public function withRangeDiscounts() {
		$filtered_rules = array();
		foreach ( $this->rules as $rule ) {
			if ( $rule instanceof SingleItemRule ) {
				if ( $rule->getProductRangeAdjustmentHandler() ) {
					$filtered_rules[] = $rule;
				}
			} elseif ( $rule instanceof PackageRule ) {
				if ( $rule->getProductRangeAdjustmentHandler() ) {
					$filtered_rules[] = $rule;
				}
			}
		}

		return new self( $filtered_rules );
	}
}
