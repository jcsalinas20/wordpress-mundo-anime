<?php

namespace ADP\BaseVersion\Includes\External\LoadStrategies;

use ADP\BaseVersion\Includes\Admin\Ajax;
use ADP\BaseVersion\Includes\Admin\Settings;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\AdminPage;
use ADP\BaseVersion\Includes\External\Customizer\Customizer;
use ADP\BaseVersion\Includes\External\Engine;
use ADP\BaseVersion\Includes\External\LoadStrategies\Interfaces\LoadStrategy;
use ADP\BaseVersion\Includes\External\PriceAjax;
use ADP\BaseVersion\Includes\External\RangeDiscountTable\RangeDiscountTableAjax;
use ADP\BaseVersion\Includes\External\Reporter\ReporterAjax;
use ADP\BaseVersion\Includes\External\WcCartStatsCollector;
use ADP\Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AdminAjax implements LoadStrategy {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	public function start() {
		/**
		 * @var Customizer $customizer
		 * @var AdminPage  $admin_page
		 * @var Engine     $engine
		 */
		$customizer       = Factory::get( "External_Customizer_Customizer", $this->context );
		$discount_message = Factory::get( "External_DiscountMessage", $this->context );
		$admin_page       = Factory::get( 'External_AdminPage_AdminPage', $this->context );
		$engine           = Factory::get( "External_Engine", $this->context, WC()->cart );

		/**
		 * @var PriceAjax $priceAjax
		 */
		$priceAjax  = new PriceAjax( $this->context, $engine );

		$admin_page->register_ajax();

		/**
		 * @var $ajax Ajax
		 */
		$ajax = Factory::get( 'Admin_Ajax', $this->context );
		$ajax->register();

		/**
		 * @var $tableAjax RangeDiscountTableAjax
		 */
		$tableAjax = new RangeDiscountTableAjax( $this->context, $customizer );
		$tableAjax->register();
		if ( !$this->context->is(Context::CUSTOMIZER ) ) {
			$discount_message->set_theme_options( $customizer );
		}
		new Settings( $this->context );

		$engine->installCartProcessAction();
		if ( $this->context->get_option( "show_debug_bar" ) && is_super_admin( $this->context->get_current_user()->ID ) ) {
			$profiler = $engine->getProfiler();
			$profiler->installActionCollectReport();
			$profilerAjax = new ReporterAjax( $profiler );
			$profilerAjax->register();
		}

		$priceAjax->register();

		$wcCartStatsCollector = new WcCartStatsCollector( $this->context );
		$wcCartStatsCollector->setActionCheckoutOrderProcessed();

		if ( $this->context->get_option( 'update_cross_sells' ) ) {
			add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'woocommerce_add_to_cart_fragments' ), 10, 2 );
		}

		/** @see Functions::install() */
		Factory::callStaticMethod( "Functions", 'install', $this->context, $engine );
	}

	public function woocommerce_add_to_cart_fragments( $fragments ) {
		/**
		 * Fix incorrect add-to-cart url in cross sells elements.
		 * We need to remove "wc-ajax" argument because WC_Product childs in method add_to_cart_url() use
		 * add_query_arg() with current url.
		 * Do not forget to set current url to cart_url.
		 */
		$_SERVER['REQUEST_URI'] = remove_query_arg( 'wc-ajax', wc_get_cart_url() );

		ob_start();
		woocommerce_cross_sell_display();
		$text = trim( ob_get_clean() );
		if ( empty( $text ) ) {
			$text = '<div class="cross-sells"></div>';
		}
		$fragments['div.cross-sells'] = $text;

		return $fragments;
	}
}
