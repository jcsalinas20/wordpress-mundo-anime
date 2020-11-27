<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Functions {
	public static function get_gifted_cart_products() {
		return adp_functions()->getGiftedCartProducts();
	}

	public static function get_active_rules_for_product( $product_id, $qty = 1, $use_empty_cart = false ) {
		return adp_functions()->getActiveRulesForProduct( $product_id, $qty, $use_empty_cart );
	}

	/**
	 *
	 * @param array   $array_of_products
	 * array[]['product_id']
	 * array[]['qty']
	 * @param boolean $plain Type of returning array. With False returns grouped by rules
	 *
	 * @return array
	 * @throws Exception
	 *
	 */
	public static function get_discounted_products_for_cart( $array_of_products, $plain = false ) {
		return adp_functions()->getDiscountedProductsForCart( $array_of_products, $plain );
	}


	/**
	 * @param int|WC_product $the_product
	 * @param int            $qty
	 * @param bool           $use_empty_cart
	 *
	 * @return float|array|null
	 * float for simple product
	 * array is (min, max) range for variable
	 * null if product is incorrect
	 */
	public static function get_discounted_product_price( $the_product, $qty, $use_empty_cart = true ) {
		return adp_functions()->getDiscountedProductPrice( $the_product, $qty, $use_empty_cart );
	}

	public static function process_cart_manually() {
		adp_functions()->processCartManually();
	}
}
