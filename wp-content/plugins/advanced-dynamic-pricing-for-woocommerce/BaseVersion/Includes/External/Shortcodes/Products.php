<?php

namespace ADP\BaseVersion\Includes\External\Shortcodes;

use ADP\BaseVersion\Includes\Context;
use WC_Shortcode_Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Products extends WC_Shortcode_Products {
	const NAME = '';
	const STORAGE_KEY = '';

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public static function register( $context ) {
		add_shortcode( static::NAME, function ( $atts ) use ( $context ) {
			return static::create( $atts, $context );
		} );
	}

	public function __construct( $attributes = array(), $type = 'products', $context = null ) {
		$this->context = $context;
		parent::__construct( $attributes, $type );
	}

	/**
	 * @param array $atts
	 * @param Context $context
	 *
	 * @return string
	 */
	public static function create( $atts, $context ) {

		// apply legacy [sale_products] attributes
		$atts = array_merge( array(
			'limit'        => '12',
			'columns'      => '4',
			'orderby'      => 'title',
			'order'        => 'ASC',
			'category'     => '',
			'cat_operator' => 'IN',
		), (array) $atts );

		$shortcode = new static( $atts, static::NAME, $context );

		return $shortcode->get_content();
	}

	/**
	 * @param Context $context
	 *
	 * @return mixed
	 */
	public static function get_cached_products_ids( $context ) {

		// Load from cache.
		$product_ids = get_transient( static::STORAGE_KEY );

		// Valid cache found.
		if ( false !== $product_ids ) {
			return $product_ids;
		}

		return static::update_cached_products_ids( $context );
	}

	/**
	 * @param Context $context
	 *
	 * @return mixed
	 */
	public static function update_cached_products_ids( $context ) {

		$product_ids = static::get_products_ids( $context );

		set_transient( static::STORAGE_KEY, $product_ids, DAY_IN_SECONDS * 30 );

		return $product_ids;
	}

}
