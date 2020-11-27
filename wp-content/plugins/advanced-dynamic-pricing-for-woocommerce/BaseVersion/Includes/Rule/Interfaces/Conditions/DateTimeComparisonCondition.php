<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface DateTimeComparisonCondition {
	const COMPARISON_DATETIME_KEY = 'comparison_datetime';
	const COMPARISON_DATETIME_METHOD_KEY = 'comparison_datetime_method';

	/**
	 * @param string $comparison_datetime
	 */
	public function setComparisonDateTime( $comparison_datetime );

	/**
	 * @return string
	 */
	public function getComparisonDateTime();

	/**
	 * @param string $comparison_method
	 */
	public function setDateTimeComparisonMethod( $comparison_method );

	/**
	 * @return string
	 */
	public function getDateTimeComparisonMethod();
}
