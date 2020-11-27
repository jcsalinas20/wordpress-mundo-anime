<?php

namespace ADP\BaseVersion\Includes\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait TimeComparison {
	/**
	 * @param int    $time
	 * @param int    $comparison_time
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function check_time( $time, $comparison_time, $comparison_method ) {
		$result = false;

		if ( $comparison_method === ComparisonMethods::LATER ) {
			$result = $time > $comparison_time;
		} elseif ( $comparison_method === ComparisonMethods::EARLIER ) {
			$result = $time < $comparison_time;
		} elseif ( $comparison_method === ComparisonMethods::FROM ) {
			$result = $time >= $comparison_time;
		} elseif ( $comparison_method === ComparisonMethods::TO ) {
			$result = $time <= $comparison_time;
		} elseif ( $comparison_method === ComparisonMethods::SPECIFIC_DATE ) {
			$result = $time == $comparison_time;
		}

		return $result;
	}
}