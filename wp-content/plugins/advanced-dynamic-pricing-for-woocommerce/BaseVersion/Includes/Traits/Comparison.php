<?php

namespace ADP\BaseVersion\Includes\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface ComparisonMethods {
	const LT  = '<';
	const LTE = '<=';
	const MTE = '>=';
	const MT  = '>';
	const EQ  = '=';
	const NEQ = '!=';
	const IN_LIST = 'in_list';
	const NOT_IN_LIST = 'not_in_list';
	const AT_LEAST_ONE_ANY = 'at_least_one_any';
	const AT_LEAST_ONE = 'at_least_one';
	const ALL = 'all';
	const ONLY = 'only';
	const NONE = 'none';
	const NONE_AT_ALL = 'none_at_all';
	const IN_RANGE = 'in_range';
	const NOT_IN_RANGE = 'not_in_range';
	const LATER = 'later';
	const EARLIER = 'earlier';
	const FROM = 'from';
	const TO = 'to';
	const SPECIFIC_DATE = 'specific_date';
}

trait Comparison {
	/**
	 * @param mixed  $value
	 * @param array  $comparison_list
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_value_with_list( $value, $comparison_list, $comparison_method = ComparisonMethods::IN_LIST ) {
		$result = false;

		if ( ComparisonMethods::IN_LIST === $comparison_method ) {
			$result = in_array( $value, $comparison_list );
		} elseif ( ComparisonMethods::NOT_IN_LIST === $comparison_method ) {
			$result = ! in_array( $value, $comparison_list );
		}

		return $result;
	}

	/**
	 * @param array  $list
	 * @param array  $comparison_list
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_lists( $list, $comparison_list, $comparison_method = ComparisonMethods::IN_LIST ) {
		$result = false;

//		if ( ComparisonMethods::IN_LIST === $comparison_method ) {
//			$result = count( array_intersect( $list, $comparison_list ) ) == count( $comparison_list );
//		} elseif ( ComparisonMethods::NOT_IN_LIST === $comparison_method ) {
//			$result = count( array_intersect( $list, $comparison_list ) ) == 0;
		if ( ComparisonMethods::AT_LEAST_ONE_ANY === $comparison_method ) {
			$result = ! empty( $list );
		} elseif ( ComparisonMethods::AT_LEAST_ONE === $comparison_method OR ComparisonMethods::IN_LIST === $comparison_method ) {
			$result = count( array_intersect( $comparison_list, $list ) ) > 0;
		} elseif ( ComparisonMethods::ALL === $comparison_method ) {
			$result = count( array_intersect( $comparison_list, $list ) ) == count( $comparison_list );
		} elseif ( ComparisonMethods::ONLY === $comparison_method ) {
			$result = array_diff( $comparison_list, $list ) === array_diff( $list, $comparison_list ) && count($comparison_list) === count($list);
		} elseif ( ComparisonMethods::NONE === $comparison_method OR ComparisonMethods::NOT_IN_LIST === $comparison_method ) {
			$result = count( array_intersect( $list, $comparison_list ) ) === 0;
		} elseif ( ComparisonMethods::NONE_AT_ALL === $comparison_method ) {
			$result = empty( $list );
		}
		return $result;
	}

	/**
	 * @param mixed  $value
	 * @param mixed  $comparison_value
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_values( $value, $comparison_value, $comparison_method = ComparisonMethods::LT ) {
		if ( $comparison_method === ComparisonMethods::IN_RANGE ) {
			$start  = isset( $comparison_value[0] ) ? (float) $comparison_value[0] : null;
			$finish = isset( $comparison_value[1] ) ? (float) $comparison_value[1] : null;

			return $this->value_in_range( $value, $start, $finish );
		}

		$result = false;

		if ( ComparisonMethods::LT === $comparison_method ) {
			$result = $value < $comparison_value;
		} elseif ( ComparisonMethods::LTE === $comparison_method ) {
			$result = $value <= $comparison_value;
		} elseif ( ComparisonMethods::MTE === $comparison_method ) {
			$result = $value >= $comparison_value;
		} elseif ( ComparisonMethods::MT === $comparison_method ) {
			$result = $value > $comparison_value;
		} elseif ( ComparisonMethods::EQ === $comparison_method ) {
			$result = $value === $comparison_value;
		} elseif ( ComparisonMethods::NEQ === $comparison_method ) {
			$result = $value !== $comparison_value;
		}

		return $result;
	}

	/**
	 * @param int $value
	 * @param int $start
	 * @param int $finish
	 *
	 * @return bool
	 */
	public function value_in_range( $value, $start, $finish ) {
		return $start && $finish && $start <= $value && $finish >= $value;
	}

	/**
	 * @param integer  $value Time in unix format
	 * @param integer  $comparison_value Time in unix format
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_time_unix_format( $value, $comparison_value, $comparison_method = ComparisonMethods::LATER ) {
		$result = false;

		if ( $comparison_method === ComparisonMethods::LATER ) {
			$result = $value > $comparison_value;
		} elseif ( $comparison_method === ComparisonMethods::EARLIER ) {
			$result = $value < $comparison_value;
		} elseif ( $comparison_method === ComparisonMethods::FROM ) {
			$result = $value >= $comparison_value;
		} elseif ( $comparison_method === ComparisonMethods::TO ) {
			$result = $value <= $comparison_value;
		} elseif ( $comparison_method === ComparisonMethods::SPECIFIC_DATE ) {
			$result = $value == $comparison_value;
		}

		return $result;
	}
}