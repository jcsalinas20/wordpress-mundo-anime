<?php

namespace ADP\BaseVersion\Includes\External\AdminPage\Tabs;

use ADP\BaseVersion\Includes\External\AdminPage\Interfaces\AdminTabInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collections implements AdminTabInterface {
	/**
	 * @var string
	 */
	protected $title;

	public function __construct( $context ) {
		$this->title = self::get_title();
	}

	public function handle_submit_action() {
		// do nothing
	}

	public static function get_relative_view_path() {
		return 'admin_page/tabs/collections.php';
	}

	public static function get_header_display_priority() {
		return 120;
	}

	public static function get_key() {
		return 'product_collections';
	}

	public static function get_title() {
		return __( 'Product Collections', 'advanced-dynamic-pricing-for-woocommerce' ) . "&nbsp;&#x1f512;";
	}

	public function get_view_variables() {
		return array();
	}

	public function enqueue_scripts() {
	}

	public function register_ajax() {

	}
}
