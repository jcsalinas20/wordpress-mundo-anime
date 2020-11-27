<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface TimeRangeCondition {
	const TIME_RANGE_KEY = 'time_range';

	/**
	 * @param string $time_range
	 */
	public function setTimeRange( $time_range );

	/**
	 * @return string
	 */
	public function getTimeRange();
}
