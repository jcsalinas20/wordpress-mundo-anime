<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ProductFiltering;
use ADP\Factory;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\ListComparisonCondition;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\RangeValueCondition;
use ADP\BaseVersion\Includes\Translators\FilterTranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class AbstractConditionCartItems extends AbstractCondition implements ListComparisonCondition, RangeValueCondition {
	const IN_LIST = 'in_list';
	const NOT_IN_LIST = 'not_in_list';
	const NOT_CONTAINING = 'not_containing';

	const AVAILABLE_COMP_METHODS = array(
		self::IN_LIST,
		self::NOT_IN_LIST,
		self::NOT_CONTAINING,
	);

	protected $comparison_list;
	protected $comparison_method;
	protected $comparison_qty;
	protected $comparison_qty_finish;
	/**
	 * @var array $comparison_list
	 * @var string $comparison_method
	 * @var integer $comparison_qty
	 * @var integer $comparison_qty_finish
	 */

	protected $used_items;
	protected $has_product_dependency = true;
	protected $filter_type = '';

	public function check( $cart ) {
		$this->used_items = array();

		$comparison_qty               = (float) $this->comparison_qty;
		$comparison_qty_finish_exists = isset( $this->comparison_qty_finish ) && $this->comparison_qty_finish != 0 ? "" !== $this->comparison_qty_finish : false;
		$comparison_qty_finish        = $comparison_qty_finish_exists ? (float) $this->comparison_qty_finish : INF;
		$comparison_method            = isset( $this->comparison_method ) ? $this->comparison_method : 'in_list';
		$comparison_list              = isset( $this->comparison_list ) ? $this->comparison_list : array();

		if ( empty( $comparison_qty ) ) {
			return true;
		}

		$invert_filtering = false;
		if ( $comparison_method === "not_containing" ) {
			$invert_filtering  = true;
			$comparison_method = 'in_list';
		}

		$qty               = 0;
		$product_filtering = Factory::get( "Rule_ProductFiltering", $cart->get_context()->getGlobalContext() );
		/**
		 * @var ProductFiltering $product_filtering 
		 */
		$product_filtering->prepare( $this->filter_type, $comparison_list, $comparison_method );

		foreach ( $cart->getItems() as $item_key => $item ) {
			$wrapper = $item->getWcItem();
			$checked   = $product_filtering->check_product_suitability( $wrapper->getProduct() );

			if ( $checked ) {
				$qty += $item->getQty();
//				$this->used_items[] = $item_key;
			}
		}

		$result = $comparison_qty_finish_exists ? ( $comparison_qty <= $qty ) && ( $qty <= $comparison_qty_finish ) : $comparison_qty <= $qty;

		return $invert_filtering ? ! $result : $result;
	}

	public function get_involved_cart_items() {
		return $this->used_items;
	}

	public function match( $cart ) {
		return $this->check( $cart );
	}

	public function get_product_dependency() {
		return array(
			'qty'    => $this->comparison_qty,
			'type'   => $this->filter_type,
			'method' => $this->comparison_method,
			'value'  => (array) $this->comparison_list,
		);
	}

	public function translate( $language_code ) {
		parent::translate( $language_code );

		$comparison_list = (array) $this->comparison_list;

		$comparison_list = ( new FilterTranslator() )->translateByType( $this->filter_type, $comparison_list, $language_code );

		$this->comparison_list = $comparison_list;
	}

	/**
	 * @param array $comparison_list
	 */
	public function setComparisonList( $comparison_list ) {
		gettype($comparison_list) === 'array' ?	$this->comparison_list = $comparison_list :	$this->comparison_list = null;
	}

	/**
	 * @param string $comparison_method
	 */
	public function setListComparisonMethod( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	public function getComparisonList()
	{
		return $this->comparison_list;
	}

	public function getListComparisonMethod() {
		return $this->comparison_method;
	}

	/**
	 * @param integer $start_range
	 */
	public function setStartRange( $start_range ) {
		$this->comparison_qty = (int)$start_range;
	}

	public function getStartRange()
	{
		return $this->comparison_qty;
	}

	/**
	 * @param integer $end_range
	 */
    public function setEndRange( $end_range ) {
		$this->comparison_qty_finish = (int)$end_range;
	}

	public function getEndRange()
	{
		return $this->comparison_qty_finish;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_list ) AND !is_null( $this->comparison_qty );
	}
}