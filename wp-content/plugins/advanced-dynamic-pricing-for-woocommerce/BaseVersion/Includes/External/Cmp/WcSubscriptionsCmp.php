<?php

namespace ADP\BaseVersion\Includes\External\Cmp;

use ADP\BaseVersion\Includes\Context;
use WC_Subscriptions_Product;
use ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\External\WC\WcNoFilterWorker;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WcSubscriptionsCmp {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var bool
	 */
	protected $isActive;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
		$this->loadRequirements();
	}

	public function loadRequirements() {
		if ( ! did_action( 'plugins_loaded' ) ) {
			_doing_it_wrong( __FUNCTION__, sprintf( __( '%1$s should not be called earlier the %2$s action.',
				'advanced-dynamic-pricing-for-woocommerce' ), 'loadRequirements', 'plugins_loaded' ), WC_ADP_VERSION );
		}

		$this->isActive = class_exists( "\WC_Subscriptions" );
	}

	/**
	 * @return bool
	 */
	public function isActive() {
		return $this->isActive;
	}

	/**
	 * @param \WC_Product $product
	 *
	 * @return bool
	 */
	public function isSubscriptionProduct( $product ) {
		return class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * @param \WC_Product $product
	 * @param string $priceHtml
	 *
	 * @return bool
	 */
	public function maybeAddSubsTail( $product, $priceHtml ) {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return $priceHtml;
		}

		return WC_Subscriptions_Product::get_price_string( $product,
			array( 'price' => $priceHtml, 'tax_calculation' => $this->context->get_tax_display_cart_mode() ) );
	}
}
