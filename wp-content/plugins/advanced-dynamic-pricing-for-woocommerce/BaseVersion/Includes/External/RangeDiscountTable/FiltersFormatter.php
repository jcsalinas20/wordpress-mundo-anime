<?php

namespace ADP\BaseVersion\Includes\External\RangeDiscountTable;

use ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Rule\Structures\Filter;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FiltersFormatter {
	protected $text_domain = 'advanced-dynamic-pricing-for-woocommerce';

	public function __construct() {}

	/**
	 * @param Filter $filter
	 *
	 * @return string
	 */
	public function formatFilter( $filter ) {
		$filter_type   = $filter->getType();
		$filter_method = $filter->getMethod();

		$filter_qty_label = '1';

		if ( $filter::TYPE_ANY === $filter_type ) {
			return sprintf( '<a href="%s">%s</a>', get_permalink( wc_get_page_id( 'shop' ) ),
				sprintf( __( '%s of any product(s)', $this->text_domain ), $filter_qty_label ) );
		}

		$templates = array_merge( array(
			'products' => array(
				'in_list'     => __( '%s product(s) from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from list: %s', $this->text_domain ),
			),

			'product_sku' => array(
				'in_list'     => __( '%s product(s) with SKUs from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with SKUs not from list: %s', $this->text_domain ),
			),

			'product_sellers' => array(
				'in_list'     => __( '%s product(s) from sellers: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from sellers: %s', $this->text_domain ),
			),

			'product_categories' => array(
				'in_list'     => __( '%s product(s) from categories: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from categories: %s', $this->text_domain ),
			),

			'product_category_slug' => array(
				'in_list'     => __( '%s product(s) from categories with slug: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from categories with slug: %s', $this->text_domain ),
			),

			'product_tags' => array(
				'in_list'     => __( '%s product(s) with tags from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with tags not from list: %s', $this->text_domain ),
			),

			'product_attributes' => array(
				'in_list'     => __( '%s product(s) with attributes from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with attributes not from list: %s', $this->text_domain ),
			),

			'product_custom_fields' => array(
				'in_list'     => __( '%s product(s) with custom fields: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) without custom fields: %s', $this->text_domain ),
			),
		), array_combine( array_keys( Helpers::get_custom_product_taxonomies() ),
			array_map( function ( $tmp_filter_type ) {
				return array(
					'in_list'     => __( '%s product(s) with ' . $tmp_filter_type . ' from list: %s',
						$this->text_domain ),
					'not_in_list' => __( '%s product(s) with ' . $tmp_filter_type . ' not from list: %s',
						$this->text_domain ),
				);
			}, array_keys( Helpers::get_custom_product_taxonomies() ) ) ) );

		if ( ! isset( $templates[ $filter_type ][ $filter_method ] ) ) {
			return "";
		}

		$humanized_values = array();
		foreach ( $filter->getValue() as $id ) {
			$name = Helpers::get_title_by_type( $id, $filter_type );
			$link = Helpers::get_permalink_by_type( $id, $filter_type );

			if ( ! empty( $link ) ) {
				$humanized_value = "<a href='{$link}'>{$name}</a>";
			} else {
				$humanized_value = "'{$name}'";
			}

			$humanized_values[ $id ] = $humanized_value;
		}

		return sprintf( $templates[ $filter_type ][ $filter_method ], $filter_qty_label, implode( ", ", $humanized_values ) );
	}

	/**
	 * @param SingleItemRule $rule
	 *
	 * @return array
	 */
	public function formatRule( $rule ) {
		$humanized_filters = array();

		foreach ( $rule->getFilters() as $filter ) {
			$humanized_filters[] = $this->formatFilter( $filter );
		}

		return $humanized_filters;
	}
}
