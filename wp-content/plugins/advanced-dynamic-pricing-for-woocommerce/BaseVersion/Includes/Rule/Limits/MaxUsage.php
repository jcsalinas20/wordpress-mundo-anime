<?php

namespace ADP\BaseVersion\Includes\Rule\Limits;

use ADP\BaseVersion\Includes\Rule\Interfaces\RuleLimit;
use ADP\BaseVersion\Includes\Rule\LimitsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Limits\MaxUsageLimit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MaxUsage implements RuleLimit, MaxUsageLimit {
	protected $max_usage;

	public function __construct() {
	}

	public function check( $rule, $cart ) {
		$comparison_value = (int) $this->max_usage;

		$value = $cart->get_context()->get_count_of_rule_usages( $rule->getId() );

		return $value < $comparison_value;
	}

	public static function getType() {
		return 'max_usage';
	}

	public static function getLabel() {
		return __( 'Max usage', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'limits/max-usage.php';
	}

	public static function getGroup() {
		return LimitsLoader::GROUP_USAGE_RESTRICT;
	}

	/**
     * @param integer $max_usage
     */
    public function setMaxUsage ( $max_usage ) {
		$this->max_usage = $max_usage;
	}

	public function getMaxUsage() {
		return $this->max_usage;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return isset( $this->max_usage );
	}
}
