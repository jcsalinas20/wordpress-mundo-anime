<?php

namespace ADP\BaseVersion\Includes\Interfaces;

use WC_Order;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface User {
	/**
	 * @param WP_User|null $wp_user
	 */
	public function __construct( $wp_user = null );

	/**
	 * @return int|null
	 */
	public function get_id();

	/**
	 * @return bool
	 */
	public function is_logged_in();

	/**
	 * @return array
	 */
	public function get_roles();

	/**
	 * @param $time
	 *
	 * @return int
	 */
	public function get_order_count_after( $time );

	/**
	 * @return string|null
	 */
	public function get_shipping_country();

	/**
	 * @param string $country
	 */
	public function set_shipping_country( $country );

	/**
	 * @return string|null
	 */
	public function get_shipping_state();

	/**
	 * @param string $state
	 */
	public function set_shipping_state( $state );

	/**
	 * @return string|null
	 */
	public function get_payment_method();

	/**
	 * @param string $method
	 */
	public function set_payment_method( $method );

	/**
	 * @return string|null
	 */
	public function get_shipping_methods();

	/**
	 * @param string $method
	 */
	public function set_shipping_methods( $method );

	public function set_is_vat_exempt( $tax_exempt );

	public function get_tax_exempt();

	/**
	 * @return float
	 */
	public function get_avg_spend_amount();

	/**
	 * @param $time
	 *
	 * @return float
	 */
	public function get_total_spend_amount( $time );

	/**
	 * @return false|WC_Order
	 */
	public function get_last_paid_order();
}