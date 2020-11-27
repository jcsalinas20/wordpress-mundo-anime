<?php

namespace ADP\BaseVersion\Includes\Rule\Interfaces;

use ADP\BaseVersion\Includes\Cart\Structures\Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface RuleCondition {
	public function __construct();

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart );

	/** @return array|null */
	public function get_involved_cart_items();

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	public function match( $cart );

	/**
	 * @return bool
	 */
	public function has_product_dependency();

	/**
	 * @return array|false
	 */
	public function get_product_dependency();

	/**
	 * Compatibility with currency plugins
	 *
	 * @param float $rate
	 */
	public function multiplyAmounts( $rate );

	/**
	 * @param $language_code
	 *
	 */
	public function translate( $language_code );

	public static function getType();

	/**
	 * @return string Localized label
	 */
	public static function getLabel();

	/**
	 * @return string
	 */
	public static function getTemplatePath();

	/**
	 * @return string
	 */
	public static function getGroup();

	/**
	 * @return bool
	 */
	public function isValid();
}
