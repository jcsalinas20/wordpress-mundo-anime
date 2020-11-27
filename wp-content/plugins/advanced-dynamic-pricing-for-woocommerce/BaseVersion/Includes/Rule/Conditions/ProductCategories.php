<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ConditionsLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ProductCategories extends AbstractConditionCartItems {
	protected $filter_type = 'product_categories';

	public static function getType() {
		return 'product_categories';
	}

	public static function getLabel() {
		return __( 'Product categories', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/products/product-categories.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CART_ITEMS;
	}
}