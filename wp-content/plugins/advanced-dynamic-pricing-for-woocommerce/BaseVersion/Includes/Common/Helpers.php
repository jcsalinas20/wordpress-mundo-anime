<?php

namespace ADP\BaseVersion\Includes\Common;

use ADP\BaseVersion\Includes\External\CacheHelper;
use WC_Product;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
add_action( 'woocommerce_subscriptions_deactivated', function () {
	Database::delete_conditions_from_db_by_types( array( 'subscription' ) );
} );

class Helpers {
	public static function get_settings() {
		$options = get_option( 'wdp_settings' );

		return $options ? array_merge( self::get_default_settings(), array_filter( (array) $options, function ( $a ) {
			return isset( $a );
		} ) ) : self::get_default_settings();
	}

	protected static function get_default_settings() {
		return apply_filters( "wdp_get_default_settings", array(
			'show_matched_bulk_table'            => 1,
			'show_category_bulk_table'           => 0,
			'show_matched_deals'                 => 0,
			'show_matched_adjustments'           => 0,
			'show_matched_get_products'          => 0,
			'show_matched_cart_adjustments'      => 0,
			'show_matched_bulk'                  => 0,
			'show_striked_prices'                => 1,
			'hide_coupon_word_in_totals'         => 0,
			'show_onsale_badge'                  => 0,
			'update_price_with_qty'              => 0,
			'limit_results_in_autocomplete'      => 25,
			'rule_max_exec_time'                 => 5,
			'rules_per_page'                     => 50,
			'support_shortcode_products_on_sale' => 0,
			'support_shortcode_products_bogo'    => 0,
			'enable_product_html_template'       => 0,
			'price_html_template'                => "{{price_html}}",

			'show_cross_out_subtotal_in_cart_totals'  => 0,
			'show_message_after_add_free_product'     => 0,
			'message_template_after_add_free_product' => __( "Added {{qty}} free {{product_name}}", 'advanced-dynamic-pricing-for-woocommerce' ),

			'bulk_table_calculation_mode' => "only_bulk_rule_table",

			'combine_discounts'                          => 0,
			'default_discount_name'                      => __( 'Coupon', 'advanced-dynamic-pricing-for-woocommerce' ),
			'combine_fees'                               => 0,
			'default_fee_name'                           => __( 'Fee', 'advanced-dynamic-pricing-for-woocommerce' ),
			'default_fee_tax_class'                      => "",
			'discount_for_onsale'                        => 'compare_discounted_and_sale',
			'initial_price_context'                      => 'nofilter',
			'do_not_modify_price_at_product_page'        => 0,
			'discount_table_ignores_conditions'          => 0,
			'use_first_range_as_min_qty'                 => 0,
			'is_override_cents'                          => 0,
			'is_calculate_based_on_wc_precision'         => 0,
			'prices_ends_with'                           => 99,
			'replace_price_with_min_bulk_price'          => 0,
			'replace_price_with_min_bulk_price_template' => __( "From {{price}} {{price_suffix}}", 'advanced-dynamic-pricing-for-woocommerce' ),

			'uninstall_remove_data'                          => 0,
			'load_in_backend'                                => 0,
			'update_prices_while_doing_cron'                 => 0,
			'update_prices_while_doing_rest_api'             => 0,
			'allow_to_edit_prices_in_po'                     => 0,
			'suppress_other_pricing_plugins'                 => 0,
			'disable_external_coupons'                       => 'dont_disable',
			'allow_to_exclude_products'                      => 1,

			'show_debug_bar' => 0,

		) );
	}

	public static function get_validate_filters() {
		return apply_filters( "wdp_get_validate_filters", array(
			'show_matched_bulk_table'            => FILTER_VALIDATE_BOOLEAN,
			'show_category_bulk_table'           => FILTER_VALIDATE_BOOLEAN,
			'show_striked_prices'                => FILTER_VALIDATE_BOOLEAN,
			'hide_coupon_word_in_totals'         => FILTER_VALIDATE_BOOLEAN,
			'show_onsale_badge'                  => FILTER_VALIDATE_BOOLEAN,
			'update_price_with_qty'              => FILTER_VALIDATE_BOOLEAN,
			'limit_results_in_autocomplete'      => FILTER_VALIDATE_INT,
			'rule_max_exec_time'                 => FILTER_SANITIZE_STRING,
			'rules_per_page'                     => FILTER_VALIDATE_INT,
			'support_shortcode_products_on_sale' => FILTER_VALIDATE_BOOLEAN,
			'support_shortcode_products_bogo'    => FILTER_VALIDATE_BOOLEAN,
			'enable_product_html_template'       => FILTER_VALIDATE_BOOLEAN,
			'price_html_template'                => FILTER_SANITIZE_SPECIAL_CHARS,

			'show_cross_out_subtotal_in_cart_totals'  => FILTER_VALIDATE_BOOLEAN,
			'show_message_after_add_free_product'     => FILTER_VALIDATE_BOOLEAN,
			'message_template_after_add_free_product' => FILTER_SANITIZE_STRING,

			'combine_discounts'                          => FILTER_VALIDATE_BOOLEAN,
			'default_discount_name'                      => array(
				'filter'  => FILTER_CALLBACK,
				'options' => 'wc_format_coupon_code',
			),
			'combine_fees'                               => FILTER_VALIDATE_BOOLEAN,
			'default_fee_name'                           => FILTER_SANITIZE_STRING,
			'default_fee_tax_class'                      => FILTER_SANITIZE_STRING,
			'discount_for_onsale'                        => FILTER_SANITIZE_STRING,
			'initial_price_context'                      => FILTER_SANITIZE_STRING,
			'do_not_modify_price_at_product_page'        => FILTER_VALIDATE_BOOLEAN,
			'discount_table_ignores_conditions'          => FILTER_VALIDATE_BOOLEAN,
			'use_first_range_as_min_qty'                 => FILTER_VALIDATE_BOOLEAN,
			'is_override_cents'                          => FILTER_VALIDATE_BOOLEAN,
			'is_calculate_based_on_wc_precision'         => FILTER_VALIDATE_BOOLEAN,
			'prices_ends_with'                           => array(
				'filter'  => FILTER_VALIDATE_REGEXP,
				'options' => array(
					'regexp'  => '/^[0-9]{2}$/',
					'default' => 99,
				),
			),
			'replace_price_with_min_bulk_price'          => FILTER_VALIDATE_BOOLEAN,
			'replace_price_with_min_bulk_price_template' => FILTER_SANITIZE_SPECIAL_CHARS,

			'uninstall_remove_data'                          => FILTER_VALIDATE_BOOLEAN,
			'load_in_backend'                                => FILTER_VALIDATE_BOOLEAN,
			'update_prices_while_doing_cron'                 => FILTER_VALIDATE_BOOLEAN,
			'update_prices_while_doing_rest_api'             => FILTER_VALIDATE_BOOLEAN,
			'allow_to_edit_prices_in_po'                     => FILTER_VALIDATE_BOOLEAN,
			'suppress_other_pricing_plugins'                 => FILTER_VALIDATE_BOOLEAN,
			'disable_external_coupons'                       => FILTER_SANITIZE_STRING,
			'allow_to_exclude_products'                      => FILTER_VALIDATE_BOOLEAN,

			'show_debug_bar' => FILTER_VALIDATE_BOOLEAN,

			'bulk_table_calculation_mode' => FILTER_SANITIZE_STRING,
		) );
	}

	public static function set_settings( $options ) {
		$options = array_merge( self::get_default_settings(), (array) $options );
		update_option( 'wdp_settings', $options );
	}

	public static function get_product_attributes( $ids ) {
		global $wc_product_attributes, $wpdb;

		if ( empty( $ids ) ) {
			return array();
		}

		$ids = implode( ', ', $ids );

		$items = $wpdb->get_results( "
			SELECT $wpdb->terms.term_id, $wpdb->terms.name, taxonomy
			FROM $wpdb->term_taxonomy INNER JOIN $wpdb->terms USING (term_id)
			WHERE $wpdb->terms.term_id in ($ids)
		" );

		return array_values( array_filter( array_map( function ( $term ) use ( $wc_product_attributes ) {
			if ( ! isset( $wc_product_attributes[ $term->taxonomy ] ) ) {
				return false;
			}

			$attribute = $wc_product_attributes[ $term->taxonomy ]->attribute_label;

			return array(
				'id'   => (string) $term->term_id,
				'text' => $attribute . ': ' . $term->name,
			);
		}, $items ) ) );
	}

	public static function get_users( $ids ) {
		$users = get_users( array(
			'fields'  => array( 'ID', 'user_nicename' ),
			'include' => $ids,
			'orderby' => 'user_nicename',
		) );

		return array_map( function ( $user ) {
			return array(
				'id'   => (string) $user->ID,
				'text' => $user->user_nicename,
			);
		}, $users );
	}

	public static function get_user_roles() {
		global $wp_roles;

		$all_roles = $wp_roles->roles;

		$result = array_map( function ( $id, $role ) {
			return array(
				'id'   => (string) $id,
				'text' => $role['name'],
			);
		}, array_keys( $all_roles ), $all_roles );

		// dummy role for non registered users
		$result[] = array(
			'id'   => 'wdp_guest',
			'text' => __( 'Guest', 'advanced-dynamic-pricing-for-woocommerce' ),
		);

		return array_values( $result );
	}

	public static function get_user_capabilities() {
		global $wp_roles;

		$all_roles = $wp_roles->roles;

		$capabilities = array();

		foreach ( $all_roles as $role ) {
			foreach ( $role['capabilities'] as $capability => $value ) {
				$capabilities[] = (string) $capability;
			}
		}

		$result = array_map( function ( $capability ) {
			return array(
				'id'   => $capability,
				'text' => $capability,
			);
		}, array_unique( $capabilities ) );

		return array_values( $result );
	}

	public static function get_countries() {
		$countries = WC()->countries->get_countries();

		$result = array_map( function ( $id, $text ) {
			return array(
				'id'   => $id,
				'text' => $text,
			);
		}, array_keys( $countries ), $countries );

		return array_values( $result );
	}

	public static function get_states() {
        $shop_country_state = wc_format_country_state_string( get_option( 'woocommerce_default_country', '' ) );
        $shop_country       = isset( $shop_country_state['country'] ) ? $shop_country_state['country'] : null;

        $countries = WC()->countries->get_countries();
		$country_states = WC()->countries->get_states();

		$result = array();
		foreach ( $country_states as $country_code => $states ) {
			foreach ( $states as $id => $text ) {
				$result[] = array(
					'id'   => $country_code . ':' . $id,
					'text' => $text . ( isset( $countries[ $country_code ] ) && $shop_country !== $country_code ? '(' . $countries[ $country_code ] . ')' : '' ),
				);
			}
		}

		return $result;
	}

	public static function get_payment_methods() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		$result = array();
		foreach ( $payment_gateways as $payment_gateway ) {
			$result[] = array(
				'id'   => $payment_gateway->id,
				'text' => $payment_gateway->title,
			);
		}

		return $result;
	}

	public static function get_shipping_methods() {
		$shipping_methods = WC()->shipping->get_shipping_methods();

		$result = array();
		foreach ( $shipping_methods as $shipping_method ) {
			$result[] = array(
				'id'   => $shipping_method->id,
				'text' => $shipping_method->method_title,
			);
		}

		return $result;
	}

	public static function get_shipping_classes() {
		$shipping_classes = WC()->shipping->get_shipping_classes();

		$result = array();
		foreach ( $shipping_classes as $shipping_class ) {
			$result[] = array(
				'id'   => $shipping_class->slug,
				'text' => $shipping_class->name,
			);
		}

		return $result;
	}

	public static function get_weekdays() {
		$result = array(
			__( 'Sunday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Monday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Tuesday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Wednesday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Thursday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Friday', 'advanced-dynamic-pricing-for-woocommerce' ),
			__( 'Saturday', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
		array_walk( $result, function ( &$item, $key ) {
			$item = array(
				'id'   => $key,
				'text' => $item,
			);
		} );

		return $result;
	}

	public static function get_product_title( $id ) {
		return get_the_title( $id );
	}

	public static function get_product_name( $id ) {
		$post = get_post( $id );
		return $post !== null ? $post->post_name : "";
	}

	public static function get_product_id( $name ) {
		if ( is_int( $name ) ) {
			if ( CacheHelper::getWcProduct( $name ) ) {
				return $name;
			}
		}


		/** @var WC_Product[] $posts */
		$posts = wc_get_products( array(
			'name' => $name,
		) );

		$post = reset( $posts );

		if ( $post instanceof WC_Product ) {
			return $post->get_id();
		}

		return false;
	}

	public static function get_product_link( $id ) {
		return get_post_permalink( $id );
	}

	public static function get_category_title( $id ) {
		$term = get_term( $id, 'product_cat' );

		return ! empty( $term ) && ! is_wp_error( $term ) ? $term->name : $id;
	}

	public static function get_category_slug_title( $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );

		return ! empty( $term ) && ! is_wp_error( $term ) ? $term->name : $slug;
	}

	public static function get_category_slug( $id ) {
		$term = get_term( $id, 'product_cat' );

		return ! empty( $term ) && ! is_wp_error( $term ) ? $term->slug : $id;
	}

	public static function get_category_id( $name ) {
		return is_numeric( $name ) ? $name : self::get_term_id( $name, 'product_cat' );
	}

	public static function get_category_link( $id ) {
		return get_category_link( $id );
	}

	public static function get_category_slug_link( $slug ) {
		$link = get_term_link( $slug, 'product_cat' );

		return ! empty( $link ) && ! is_wp_error( $link ) ? $link : "";
	}

	public static function get_tag_title( $id ) {
		$term = get_term( $id, 'product_tag' );

		return $term ? $term->name : $id;
	}

	public static function get_tag_id( $name ) {
		return is_numeric( $name ) ? $name : self::get_term_id( $name, 'product_tag' );
	}

	public static function get_tag_link( $id ) {
		return get_tag_link( $id );
	}

	public static function get_attribute_title( $id ) {
		global $wc_product_attributes;

		$term = get_term( $id, 'product_tag' );

		if ( $term AND ! is_wp_error( $term ) ) {
			$attribute = $wc_product_attributes[ $term->taxonomy ]->attribute_label;
			$ret       = $attribute . ': ' . $term->name;
		} else {
			$ret = $id;
		}

		return $ret;
	}

	public static function get_attribute_id( $name ) {
		return is_numeric( $name ) ? $name : self::get_term_id( $name, 'product_tag' );
	}

	public static function get_attribute_link( $id ) {
		return '';//TODO:??
	}

	public static function get_term_id( $name, $taxonomy ) {
		$term = get_term_by( 'name', $name, $taxonomy );

		if ( $term instanceof WP_Term ) {
			return $term->term_id;
		}

		return false;
	}

	public static function get_title_by_type( $id, $type ) {
		if ( 'products' === $type ) {
			$name = self::get_product_title( $id );
		} elseif ( 'product_categories' === $type ) {
			$name = self::get_category_title( $id );
		} elseif ( 'product_category_slug' === $type ) {
			$name = self::get_category_slug_title( $id );
		} elseif ( 'product_tags' === $type ) {
			$name = self::get_tag_title( $id );
		} elseif ( 'product_attributes' === $type ) {
			$name = self::get_attribute_title( $id );
		} elseif ( in_array( $type, array_keys( self::get_custom_product_taxonomies() ) ) ) {
			$name = self::get_product_taxonomy_term_title( $id, $type );
		} else {
			$name = $id;
		}

		return $name;
	}

	public static function get_permalink_by_type( $id, $type ) {
		if ( 'products' === $type ) {
			$link = self::get_product_link( $id );
		} elseif ( 'product_categories' === $type ) {
			$link = self::get_category_link( $id );
		} elseif ( 'product_category_slug' === $type ) {
			$link = self::get_category_slug_link( $id );
		} elseif ( 'product_tags' === $type ) {
			$link = self::get_tag_link( $id );
		} elseif ( 'product_attributes' === $type ) {
			$link = self::get_attribute_link( $id );
		} elseif ( in_array( $type, array_keys( self::get_custom_product_taxonomies() ) ) ) {
			$link = self::get_product_taxonomy_term_permalink( $id, $type );
		} else {
			$link = '';
		}

		return $link;
	}

	public static function get_custom_product_taxonomies( $skip_cache = false ) {
		static $custom_taxonomies = null;
		if ( ! $skip_cache && $custom_taxonomies !== null ) {
			return $custom_taxonomies;
		}

		$custom_taxonomies = array_filter( get_taxonomies( array(
			'show_ui'      => true,
			'show_in_menu' => true,
			'object_type'  => array( 'product' ),
		), 'objects' ), function ( $tax ) {
			$build_in_taxonomies = array( 'product_cat', 'product_tag' );

			return ! in_array( $tax->name, $build_in_taxonomies );
		} );

		return $custom_taxonomies;
	}

	public static function get_product_taxonomy_term_title( $term_id, $taxonomy_name ) {
		$term = get_term( $term_id, $taxonomy_name );

		return ! empty( $term ) && ! is_wp_error( $term ) ? $term->name : $term_id;
	}

	public static function get_product_taxonomy_term_permalink( $term_id, $taxonomy_name ) {
		$term_permalink = get_term_link( (int) $term_id, $taxonomy_name );

		return ! empty( $term_permalink ) && ! is_wp_error( $term_permalink ) ? $term_permalink : '';
	}
}
