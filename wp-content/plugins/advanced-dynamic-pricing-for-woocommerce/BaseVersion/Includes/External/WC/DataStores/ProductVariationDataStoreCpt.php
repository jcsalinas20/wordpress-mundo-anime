<?php

namespace ADP\BaseVersion\Includes\External\WC\DataStores;

use ADP\BaseVersion\Includes\External\CacheHelper;
use ReflectionClass;
use WC_Data_Exception;
use WC_Object_Data_Store_Interface;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use WC_Product_Variation_Data_Store_CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ProductVariationDataStoreCpt extends WC_Product_Variation_Data_Store_CPT implements WC_Object_Data_Store_Interface {
	/**
	 * @var WC_Product|null
	 */
	private $product_parent = null;

	/**
	 * Reads a product from the database and sets its data to the class.
	 *
	 * @param WC_Product_Variation $product Product object.
	 *
	 * @throws WC_Data_Exception If WC_Product::set_tax_status() is called with an invalid tax status (via read_product_data).
	 */
	public function read( &$product ) {
		if ( is_null( $this->product_parent ) ) {
			return;
		}

		$product->set_defaults();

		if ( ! $product->get_id() ) {
			return;
		}

		$product_data = CacheHelper::getVariationProductData( $product->get_id() );

		if ( ! $product_data || ! in_array( $product_data->post_type, array(
				'product',
				'product_variation'
			), true ) ) {
			return;
		}

		$this->set_product_props( $product, array(
			'name'              => $product_data->post_title,
			'slug'              => $product_data->post_name,
			'status'            => $product_data->post_status,
			'menu_order'        => $product_data->menu_order,
			'reviews_allowed'   => 'open' === $product_data->comment_status,
			'parent_id'         => $product_data->post_parent,
			'attribute_summary' => $product_data->post_excerpt,
		) );

		$product->set_date_created( 0 < $product_data->post_date_gmt ? wc_string_to_timestamp( $product_data->post_date_gmt ) : null );
		$product->set_date_modified( 0 < $product_data->post_modified_gmt ? wc_string_to_timestamp( $product_data->post_modified_gmt ) : null );

		$this->read_product_data( $product );
		$product->set_attributes( $this->get_product_variation_attributes( $product->get_id() ) );

		// Set object_read true once all data is read.
		$product->set_object_read( true );
	}

	private function get_product_variation_attributes( $variation_id ) {
		$all_meta                = CacheHelper::getVariationProductMeta( $variation_id );
		$parent_attributes       = $this->product_parent->get_attributes();
		$found_parent_attributes = array();
		$variation_attributes    = array();

		// Compare to parent variable product attributes and ensure they match.
		foreach ( $parent_attributes as $attribute_name => $attribute ) {
			/**
			 * @var $attribute WC_Product_Attribute
			 */

			if ( $attribute->get_variation() ) {
				$attribute                 = 'attribute_' . $attribute->get_name();
				$found_parent_attributes[] = $attribute;
				if ( ! array_key_exists( $attribute, $variation_attributes ) ) {
					$variation_attributes[ $attribute ] = ''; // Add it - 'any' will be asumed.
				}
			}
		}

		// Get the variation attributes from meta.
		foreach ( $all_meta as $name => $value ) {
			// Only look at valid attribute meta, and also compare variation level attributes and remove any which do not exist at parent level.
			if ( 0 !== strpos( $name, 'attribute_' ) || ! in_array( $name, $found_parent_attributes, true ) ) {
				unset( $variation_attributes[ $name ] );
				continue;
			}

			$variation_attributes[ $name ] = $value;
		}

		return $variation_attributes;
	}

	private function set_product_props( &$product, $props ) {
		$reflection = new ReflectionClass( $product );
		$property   = $reflection->getProperty( 'data' );
		$property->setAccessible( true );
		$data = $property->getValue( $product );

		$property->setValue( $product, array_merge( $data, $props ) );
	}

	/**
	 * @param $parent WC_Product
	 */
	public function add_parent( $parent ) {
		if ( $parent instanceof WC_Product ) {
			$this->product_parent = $parent;
		}
	}

	/**
	 * Read post data.
	 *
	 * @param WC_Product_Variation $product Product object.
	 *
	 * @throws WC_Data_Exception If WC_Product::set_tax_status() is called with an invalid tax status.
	 */
	protected function read_product_data( &$product ) {
		$product_meta = CacheHelper::getVariationProductMeta( $product->get_id() );

		$meta_keys = array(
			'_variation_description' => 'description',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_manage_stock'          => 'manage_stock',
			'_stock_status'          => 'stock_status',
			'_virtual'               => 'virtual',
			'_downloadable'          => 'downloadable',
			'_product_image_gallery' => 'gallery_image_ids',
			'_download_limit'        => 'download_limit',
			'_download_expiry'       => 'download_expiry',
			'_thumbnail_id'          => 'image_id',
			'_backorders'            => 'backorders',
			'_sku'                   => 'sku',
			'_stock'                 => 'stock_quantity',
			'_weight'                => 'weight',
			'_length'                => 'length',
			'_width'                 => 'width',
			'_height'                => 'height',
			'_tax_class'             => 'tax_class',
			'_tax_status'            => 'tax_status',
		);

		$props = array();

		foreach ( $product_meta as $key => $value ) {
			if ( isset( $meta_keys[ $key ] ) ) {
				$props[ $meta_keys[ $key ] ] = $value;
			}
		}

		if ( ! isset( $props['tax_class'] ) ) {
			$props['tax_class'] = 'parent';
		}

		// must use set_date_props()
		if ( isset( $product_meta['_sale_price_dates_from'] ) ) {
			$product->set_date_on_sale_from( $product_meta['_sale_price_dates_from'] );
		}
		if ( isset( $product_meta['_sale_price_dates_to'] ) ) {
			$product->set_date_on_sale_to( $product_meta['_sale_price_dates_to'] );
		}

		$this->set_product_props( $product, $props );

//		$product->set_shipping_class_id( current( $this->get_term_ids( $id, 'product_shipping_class' ) ) );

		if ( $product->is_on_sale( 'edit' ) ) {
			$product->set_price( $product->get_sale_price( 'edit' ) );
		} else {
			$product->set_price( $product->get_regular_price( 'edit' ) );
		}

		$parent_data = array(
			'title'              => $this->product_parent->get_title(),
			'status'             => $this->product_parent->get_status( 'nofilter' ),
			'sku'                => $this->product_parent->get_sku( 'nofilter' ),
			'manage_stock'       => $this->product_parent->managing_stock(),
			'backorders'         => $this->product_parent->backorders_allowed(),
			'stock_quantity'     => $this->product_parent->get_stock_quantity( 'nofilter' ),
			'weight'             => $this->product_parent->get_weight( 'nofilter' ),
			'length'             => $this->product_parent->get_length( 'nofilter' ),
			'width'              => $this->product_parent->get_width( 'nofilter' ),
			'height'             => $this->product_parent->get_height( 'nofilter' ),
			'tax_class'          => $this->product_parent->get_tax_class( 'nofilter' ),
			'shipping_class_id'  => $this->product_parent->get_shipping_class_id( 'nofilter' ),
			'image_id'           => $this->product_parent->get_image_id( 'nofilter' ),
			'purchase_note'      => $this->product_parent->get_purchase_note( 'nofilter' ),
			'catalog_visibility' => $this->product_parent->get_catalog_visibility( 'nofilter' ),
		);
		// since WC 3.5.0
		if ( method_exists( $this->product_parent, "get_low_stock_amount" ) ) {
			$parent_data['low_stock_amount'] = $this->product_parent->get_low_stock_amount( 'nofilter' );
		}

		$product->set_parent_data( $parent_data );

		// Pull data from the parent when there is no user-facing way to set props.
		$product->set_sold_individually( $this->product_parent->get_sold_individually( 'nofilter' ) );
		$product->set_tax_status( $this->product_parent->get_tax_status( 'nofilter' ) );
		$product->set_cross_sell_ids( $this->product_parent->get_cross_sell_ids( 'nofilter' ) );
	}
}