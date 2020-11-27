<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface ListComparisonCondition {
	const COMPARISON_LIST_KEY = 'comparison_list';
	const COMPARISON_LIST_METHOD_KEY = 'comparison_list_method';

	/**
	 * @param array $comparison_list
	 */
	public function setComparisonList( $comparison_list );

	/**
	 * @return array
	 */
	public function getComparisonList();

	/**
	 * @param string $comparison_method
	 */
	public function setListComparisonMethod( $comparison_method );

	/**
	 * @return string
	 */
	public function getListComparisonMethod();
}
