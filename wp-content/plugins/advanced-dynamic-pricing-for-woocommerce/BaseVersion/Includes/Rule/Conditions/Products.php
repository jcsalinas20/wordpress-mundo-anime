<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ConditionsLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Products extends AbstractConditionCartItems {
    protected $filter_type = 'products';

	public static function getType() {
		return 'products';
	}

	public static function getLabel() {
		return __( 'Products', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/products/products.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CART_ITEMS;
	}
}