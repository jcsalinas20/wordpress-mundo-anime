<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Rule\Structures\Gift;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface Rule {
	/**
	 * @return int
	 */
	public function getId();

	/**
	 * @return string
	 */
	public function getTitle();

	/**
	 * @param string $title
	 */
	public function setTitle( $title );

	/**
	 * @param Context $context
	 *
	 * @return RuleProcessor
	 */
	public function buildProcessor( $context );

	/**
	 * @return int
	 */
	public function getPriority();

	/**
	 * @return RuleCondition[]
	 */
	public function getConditions();

	/**
	 * @param RuleCondition $condition
	 */
	public function addCondition( $condition );

	/**
	 * TODO remove after implement conditions groups
	 *
	 * @return string
	 */
	public function getConditionsRelationship();

	/**
	 * @return RuleLimit[]
	 */
	public function getLimits();

	/**
	 * @param RuleLimit $limit
	 */
	public function addLimit( $limit );

	/**
	 * @return CartAdjustment[]
	 */
	public function getCartAdjustments();

	/**
	 * @param CartAdjustment $cartAdjustment
	 */
	public function addCartAdjustment( $cartAdjustment );

	/**
	 * @return Gift[]
	 */
	public function getGifts();

	/**
	 * @param $gifts
	 */
	public function setGifts( $gifts );
}
