<?php

namespace ADP\BaseVersion\Includes\External\AdminPage;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\Interfaces\AdminTabInterface;
use ADP\BaseVersion\Includes\External\AdminPage\Tabs\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AdminPage {
	const SLUG = 'wdp_settings';
	const TAB_REQUEST_KEY = 'tab';

	/**
	 * @var AdminTabInterface[]
	 */
	protected $tabs;

	/**
	 * @var AdminTabInterface
	 */
	protected $current_tab;

	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context = null ) {
		$this->context = $context;
	}

	public function init_page() {
		$this->prepare_tabs();
		$this->sort_tabs_by_priority();
		$this->detect_current_tab();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'script_loader_src', array( $this, 'do_not_load_external_select2' ), PHP_INT_MAX, 2 );
	}

	public function register_ajax() {
		// TODO Should we detect the current tab and register only its methods?
		$this->prepare_tabs();
		foreach ( $this->tabs as $tab ) {
			$tab->register_ajax();
		}
	}

	public function install_register_page_hook() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Pricing Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Pricing Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'show_admin_page' ) );
	}

	public function show_admin_page() {
		$this->current_tab->handle_submit_action();

		$tabs = $this->tabs;
		$current_tab = $this->current_tab;
		include WC_ADP_PLUGIN_VIEWS_PATH . 'admin_page/admin_page.php';
	}

	public function render_current_tab() {
		$view_variables = $this->current_tab->get_view_variables();
		if ( is_array($view_variables) ) {
			extract( $view_variables );
		}

		$tab_handler = $this->current_tab;
		include WC_ADP_PLUGIN_VIEWS_PATH . $tab_handler::get_relative_view_path();
	}

	public function enqueue_scripts() {
		$current_tab = $this->current_tab;
		$baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";

		// Enqueue script for handling the meta boxes
		wp_enqueue_script( 'wdp_postbox', $baseVersionUrl . 'assets/js/postbox.js', array( 'jquery', 'jquery-ui-sortable' ), WC_ADP_VERSION );

		// jQuery UI Datepicker
		wp_enqueue_script( 'jquery-ui-datepicker' );

		// jQuery UI Datepicker styles
		wp_enqueue_style( 'wdp_jquery-ui', $baseVersionUrl . 'assets/jquery-ui/jquery-ui.min.css', array(), '1.11.4' );

		// Enqueue Select2 related scripts and styles
		wp_enqueue_script( 'wdp_select2', $baseVersionUrl . 'assets/js/select2/select2.full.min.js', array( 'jquery' ), '4.0.3' );
		wp_enqueue_style( 'wdp_select2', $baseVersionUrl . 'assets/css/select2/select2.css', array(), '4.0.3' );

		if ( $current_tab::get_key() !== Options::get_key() ) {
			// Enqueue jquery mobile related scripts and styles (for flip switch)
			// styles below are replacing option sections styles
			wp_enqueue_script( 'jquery-mobile-scripts', $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.min.js', array( 'jquery' ) );
			wp_enqueue_style( 'jquery-mobile-styles', $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.structure.min.css' );
			wp_enqueue_style( 'jquery-mobile-theme-styles', $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.theme.css' );
		}

		// Backend styles
		wp_enqueue_style( 'wdp_settings-styles', $baseVersionUrl . 'assets/css/settings.css', array(), WC_ADP_VERSION );

		// DateTime Picker
		wp_enqueue_script( 'wdp_datetimepicker-scripts', $baseVersionUrl . 'assets/datetimepicker/jquery.datetimepicker.full.min.js', array( 'jquery' ) );
		wp_enqueue_style( 'wdp_datetimepicker-styles', $baseVersionUrl . 'assets/datetimepicker/jquery.datetimepicker.min.css', array() );


		$this->current_tab->enqueue_scripts();
	}

	protected function detect_current_tab() {
		$current_tab_key = null;

		if ( isset( $_REQUEST[ self::TAB_REQUEST_KEY ] ) ) {
			$current_tab_key = $_REQUEST[ self::TAB_REQUEST_KEY ];
		}

		if ( ! isset( $this->tabs[ $current_tab_key ] ) ) {
			$current_tab_key = key( $this->tabs );
		}

		$this->current_tab = $this->tabs[ $current_tab_key ];
	}

	protected function prepare_tabs() {
		$tabs_namespace = __NAMESPACE__ . "\Tabs\\";
		foreach ( glob( dirname( __FILE__ ) . "/Tabs/*" ) as $filename ) {
			$tab       = str_replace( ".php", "", basename( $filename ) );
			$classname = $tabs_namespace . $tab;

			if ( class_exists( $classname ) ) {
				$tab_handler = new $classname( $this->context );
				/**
				 * @var $tab_handler AdminTabInterface
				 */

				$this->tabs[ $tab_handler::get_key() ] = $tab_handler;
			}
		}
	}

	protected function sort_tabs_by_priority() {
		uasort( $this->tabs, function ( $tab1, $tab2 ) {
			/**
			 * @var $tab1 AdminTabInterface
			 * @var $tab2 AdminTabInterface
			 */

			if ( $tab1::get_header_display_priority() <= $tab2::get_header_display_priority() ) {
				return - 1;
			} else {
				return 1;
			}
		} );
	}

	public function do_not_load_external_select2( $src, $handle ) {
		// don't load ANY select2.js / select2.min.js  and OUTDATED select2.full.js
		if ( ! preg_match( '/\/select2\.full\.js\?ver=[1-3]/', $src ) && ! preg_match( '/\/select2\.min\.js/', $src ) && ! preg_match( '/\/select2\.js/', $src ) ) {
			return $src;
		}

		return "";
	}
}
