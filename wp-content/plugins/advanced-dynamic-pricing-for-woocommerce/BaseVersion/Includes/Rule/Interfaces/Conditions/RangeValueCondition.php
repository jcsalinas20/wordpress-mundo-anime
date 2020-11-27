<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface RangeValueCondition {
	const START_RANGE_KEY = 'start_range';
	const END_RANGE_KEY = 'end_range';

	/**
	 * @param integer $start_range
	 */
	public function setStartRange( $start_range );

	/**
	 * @return integer
	 */
	public function getStartRange();

	/**
	 * @param integer $end_range
	 */
	public function setEndRange( $end_range );

	/**
	 * @return integer
	 */
	public function getEndRange();
}
