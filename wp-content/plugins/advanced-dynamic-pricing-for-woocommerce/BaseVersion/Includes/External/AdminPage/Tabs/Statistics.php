<?php

namespace ADP\BaseVersion\Includes\External\AdminPage\Tabs;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\Interfaces\AdminTabInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Statistics implements AdminTabInterface {
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context = $context;
		$this->title   = self::get_title();
	}

	public function handle_submit_action() {
		// do nothing
	}

	public function get_view_variables() {
		return array();
	}

	public static function get_relative_view_path() {
		return 'admin_page/tabs/statistics.php';
	}

	public static function get_header_display_priority() {
		return 140;
	}

	public static function get_key() {
		return 'statistics';
	}

	public static function get_title() {
		return __( 'Statistics', 'advanced-dynamic-pricing-for-woocommerce' ) . "&nbsp;&#x1f512;";
	}

	public function enqueue_scripts() {
	}

	public function register_ajax() {

	}
}
