<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\DateTimeComparisonCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Date extends AbstractCondition implements DateTimeComparisonCondition {
	const FROM = 'from';
	const TO = 'to';
	const SPECIFIC_DATE = 'specific_date';

	const AVAILABLE_COMP_METHODS = array(
		self::FROM,
		self::TO,
		self::SPECIFIC_DATE,
	);

	/**
	 * @var string
	 */
	protected $comparison_date;
	/**
	 * @var string
	 */
	protected $comparison_method;

	public function check( $cart ) {
		$date = $cart->get_context()->datetime( 'd-m-Y' );
		$date = strtotime( $date );

		$comparison_date   = strtotime( $this->comparison_date );
		$comparison_method = $this->comparison_method;

		return $this->compare_time_unix_format( $date, $comparison_date, $comparison_method );
	}
	
	public static function getType() {
		return 'date';
	}

	public static function getLabel() {
		return __( 'Date', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/datetime/date.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_DATE_TIME;
	}

	/**
	 * @param string $comparison_datetime
	 */
	public function setComparisonDateTime( $comparison_datetime ) {
		gettype($comparison_datetime) === 'string' ? $this->comparison_date = $comparison_datetime : $this->comparison_date = null;
	}

    /**
     * @param string $comparison_method
     */
    public function setDateTimeComparisonMethod ( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	public function getComparisonDateTime()
	{
		return $this->comparison_date;
	}

	public function getDateTimeComparisonMethod()
	{
		return $this->comparison_method;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_date );
	}
}