<?php

namespace ADP\BaseVersion\Includes\Rule\CartAdjustments;

use ADP\BaseVersion\Includes\Cart\Structures\ShippingAdjustment;
use ADP\BaseVersion\Includes\Rule\Interfaces\CartAdjustment;

use ADP\BaseVersion\Includes\Rule\CartAdjustmentsLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FreeShipping extends AbstractCartAdjustment implements CartAdjustment {
	public static function getType() {
		return 'free__shipping';
	}

	public static function getLabel() {
		return __( 'Free shipping', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'cart_adjustments/empty.php';
	}

	public static function getGroup() {
		return CartAdjustmentsLoader::GROUP_SHIPPING;
	}

	public function __construct() {
		$this->amount_indexes = array();
	}

	public function isValid() {
		return true;
	}

	public function applyToCart( $rule, $cart ) {
		$context = $cart->get_context()->getGlobalContext();
		$cart->addShippingAdjustment( new ShippingAdjustment( $context, ShippingAdjustment::TYPE_FREE, 0, $rule->getId() ) );
	}
}
