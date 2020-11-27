<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface CombinationCondition {
	const COMBINE_TYPE_KEY = 'combine_type';
	const COMBINE_LIST_KEY = 'combine_list';

	/**
	 * @param string $combine_type
	 */
	public function setCombineType( $combine_type );

	/**
	 * @return string
	 */
	public function getCombineType();

	/**
	 * @param array $combine_list
	 */
	public function setCombineList( $combine_list );

	/**
	 * @return array
	 */
	public function getCombineList();
}
