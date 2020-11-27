<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface ValueComparisonCondition {
	const COMPARISON_VALUE_KEY = 'comparison_value';
	const COMPARISON_VALUE_METHOD_KEY = 'comparison_value_method';

	/**
	 * @param string $comparison_method
	 */
	public function setValueComparisonMethod( $comparison_method );

	/**
	 * @return string
	 */
	public function getValueComparisonMethod();

	/**
	 * @param float|string $comparison_value
	 */
	public function setComparisonValue( $comparison_value );

	/**
	 * @return float|string
	 */
	public function getComparisonValue();
}
