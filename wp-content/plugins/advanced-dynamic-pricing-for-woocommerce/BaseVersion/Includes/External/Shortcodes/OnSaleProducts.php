<?php

namespace ADP\BaseVersion\Includes\External\Shortcodes;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\CacheHelper;
use ADP\BaseVersion\Includes\Rule\Interfaces\Rule;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;
use ADP\BaseVersion\Includes\External\SqlGenerator;
use ADP\Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class OnSaleProducts extends Products {
	const NAME = 'adp_products_on_sale';
	const STORAGE_KEY = 'wdp_products_onsale';

	protected function set_adp_products_on_sale_query_args( &$query_args ) {
		$query_args['post__in'] = array_merge( array( 0 ), static::get_cached_products_ids( $this->context ) );
	}

	/**
	 * @param Context $context
	 *
	 * @return array
	 */
	public static function get_products_ids( $context ) {
		global $wpdb;

		$rulesCollection = CacheHelper::loadActiveRules( $context );
		$rulesArray       = $context->get_option( 'rules_apply_mode' ) !== "none" ? $rulesCollection->getRules() : array();

		/** @var $sql_generator SqlGenerator */
		$sql_generator = Factory::get( "External_SqlGenerator" );

		foreach ( $rulesArray as $rule ) {
			if ( self::isSimpleRule( $rule ) ) {
				$sql_generator->apply_rule_to_query( $rule );
			}
		}

		if ( $sql_generator->is_empty() ) {
			return array();
		}

		$sql_joins = $sql_generator->get_join();
		$sql_where = $sql_generator->get_where();

		$sql = "SELECT post.ID as id, post.post_parent as parent_id FROM `$wpdb->posts` AS post
			" . implode( " ", $sql_joins ) . "
			WHERE post.post_type IN ( 'product', 'product_variation' )
				AND post.post_status = 'publish'
			" . ( $sql_where ? " AND " : "" ) . implode( " OR ", array_map( function ( $v ) {
				return "(" . $v . ")";
			}, $sql_where ) ) . "
			GROUP BY post.ID";

		$bogo_products = $wpdb->get_results( $sql );

		$product_ids_bogo = wp_parse_id_list( array_merge( wp_list_pluck( $bogo_products, 'id' ),
			array_diff( wp_list_pluck( $bogo_products, 'parent_id' ), array( 0 ) ) ) );

		return $product_ids_bogo;
	}

	/**
	 * @param Rule $rule
	 *
	 * @return bool
	 */
	protected static function isSimpleRule( $rule ) {
		return
			$rule instanceof SingleItemRule &&
			$rule->getProductAdjustmentHandler() &&
			! $rule->getProductRangeAdjustmentHandler() &&
			! $rule->getRoleDiscounts() &&
			count( $rule->getGifts() ) === 0 &&
			count( $rule->getItemGifts() ) === 0 &&
			count( $rule->getConditions() ) === 0 &&
			count( $rule->getLimits() ) === 0;
	}
}
