<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface BinaryCondition {
	const COMPARISON_BIN_VALUE_KEY = 'comparison_bin_value';

	/**
	 * @param string $comparison_value
	 */
	public function setComparisonBinValue( $comparison_value );

	/**
	 * @return string
	 */
	public function getComparisonBinValue();
}
