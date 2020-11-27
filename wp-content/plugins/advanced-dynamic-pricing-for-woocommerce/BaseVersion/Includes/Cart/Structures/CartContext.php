<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartContext {
	/**
	 * @var CartCustomer
	 */
	private $customer;

	/**
	 * @var array
	 */
	private $environment;

	/**
	 * @var Context
	 */
	private $context;

	/**
	 * @param CartCustomer $customer
	 * @param Context      $context
	 */
	public function __construct( $customer, $context ) {
		$this->customer = $customer;
		$this->context  = $context;

		$this->environment = array(
			'timestamp'           => current_time( 'timestamp' ),
			'prices_includes_tax' => $context->get_is_prices_include_tax(),
			'tab_enabled'         => $context->get_is_tax_enabled(),
			'tax_display_shop'    => $context->get_tax_display_shop_mode(),
		);
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	public function datetime( $format ) {
		return date( $format, $this->environment['timestamp'] );
	}

	/**
	 * @return Context
	 */
	public function getGlobalContext() {
		return $this->context;
	}

	/**
	 * @return CartCustomer
	 */
	public function getCustomer() {
		return $this->customer;
	}

	/**
	 * @return int
	 */
	public function time() {
		return $this->environment['timestamp'];
	}

	public function get_price_mode() {
		return $this->get_option( 'discount_for_onsale' );
	}

	public function is_combine_multiple_discounts() {
		return $this->get_option( 'combine_discounts' );
	}

	public function is_combine_multiple_fees() {
		return $this->get_option( 'combine_fees' );
	}

	public function get_customer_id() {
		return $this->customer->getId();
	}

	public function get_count_of_rule_usages( $rule_id ) {
		return Database::get_count_of_rule_usages( $rule_id );
	}

	public function get_count_of_rule_usages_per_customer( $rule_id, $customer_id ) {
		return Database::get_count_of_rule_usages_per_customer( $rule_id, $customer_id );
	}

	public function is_tax_enabled() {
		return isset( $this->environment['tab_enabled'] ) ? $this->environment['tab_enabled'] : false;
	}

	public function is_prices_includes_tax() {
		return isset( $this->environment['prices_includes_tax'] ) ? $this->environment['prices_includes_tax'] : false;
	}

	public function get_tax_display_shop() {
		return isset( $this->environment['tax_display_shop'] ) ? $this->environment['tax_display_shop'] : '';
	}

	public function get_option( $key, $default = false ) {
		return $this->context->get_option( $key );
	}
}
