<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\DateTimeComparisonCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Time extends AbstractCondition implements DateTimeComparisonCondition {
	const FROM = 'from';
	const TO = 'to';

	const AVAILABLE_COMP_METHODS = array(
		self::FROM,
		self::TO,
	);

	/**
	 * @var string
	 */
	protected $comparison_time;
	/**
	 * @var string
	 */
	protected $comparison_method;

	public function check( $cart ) {
		$time = strtotime( $cart->get_context()->datetime( 'H:i' ) );

		$comparison_time   = strtotime( $this->comparison_time );
		$comparison_method = $this->comparison_method;

		return $this->compare_time_unix_format( $time, $comparison_time, $comparison_method );
	}

	public static function getType() {
		return 'time';
	}

	public static function getLabel() {
		return __( 'Time', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/datetime/time.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_DATE_TIME;
	}
	
	/**
	 * @param string $comparison_datetime
	 */
	public function setComparisonDateTime( $comparison_datetime ) {
		gettype($comparison_datetime) === 'string' ? $this->comparison_time = $comparison_datetime : $this->comparison_time = null;
	}

    /**
     * @param string $comparison_method
     */
    public function setDateTimeComparisonMethod ( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	public function getComparisonDateTime()
	{
		return $this->comparison_time;
	}

	public function getDateTimeComparisonMethod()
	{
		return $this->comparison_method;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_time );
	}
}