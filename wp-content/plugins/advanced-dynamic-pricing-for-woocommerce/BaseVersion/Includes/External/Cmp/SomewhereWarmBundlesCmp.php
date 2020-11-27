<?php

namespace ADP\BaseVersion\Includes\External\Cmp;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TODO force the option 'initial_price_context' value to 'view'
 */
class SomewhereWarmBundlesCmp {
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

	/**
	 * @param WcCartItemFacade $facade
	 *
	 * @return bool
	 */
	public function isBundled( $facade ) {
		return function_exists('wc_pb_maybe_is_bundled_cart_item') && wc_pb_maybe_is_bundled_cart_item( $facade->getData() );
	}
}