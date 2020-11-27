<?php

namespace ADP\BaseVersion\Includes\External;

use ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class SqlGenerator {
	protected $applied_rules = array();

	/**
	 * @var array
	 */
	protected $join = array();

	/**
	 * @var array
	 */
	protected $where = array();

	/**
	 * @var array
	 */
	protected $custom_taxonomies = array();

	public function __construct() {
		$this->custom_taxonomies = array_values( array_map( function ( $tax ) {
			return $tax->name;
		}, Helpers::get_custom_product_taxonomies() ) );
	}

	/**
	 * @param SingleItemRule $rule
	 *
	 * @return bool
	 */
	public function apply_rule_to_query( $rule ) {
		$filters = $rule->getFilters();
		if ( ! $filters ) {
			return false;
		}

		$filter = reset( $filters );

		if ( ! $filter->isValid() ) {
			return false;
		}

		$generated = $this->generate_filter_sql_by_type( $filter->getType(), $filter->getValue() );

		if ( ! empty( $generated['where'] ) ) {
			$this->where[] = $generated['where'];
		}

		$this->applied_rules[] = $rule;

		return true;
	}

	public function get_join() {
		return $this->join;
	}

	public function get_where() {
		return $this->where;
	}

	protected function generate_filter_sql_by_type( $type, $value ) {
		if ( in_array( $type, $this->custom_taxonomies ) ) {
			return $this->gen_sql_custom_taxonomy( $type, $value );
		}

		$method_name = "gen_sql_$type";

		return method_exists( $this, $method_name ) ? call_user_func( array( $this, $method_name ), $value ) : false;
	}

	protected function gen_sql_products( $product_ids ) {
		$where = array();

		$ids_sql_in = "( '" . implode( "','", array_map( 'esc_sql', $product_ids ) ) . "' )";

		$where[] = "post.ID IN {$ids_sql_in} OR post.post_parent IN {$ids_sql_in}";

		return array(
			'where' => implode( " ", $where ),
		);
	}

	protected function add_join( $sql_join ) {
		$hash = md5( $sql_join );
		if ( ! isset( $this->join[ $hash ] ) ) {
			$this->join[ $hash ] = $sql_join;
		}
	}

	protected function gen_sql_product_sku( $skus ) {
		global $wpdb;

		$skus_sql_in = "( '" . implode( "','", array_map( 'esc_sql', $skus ) ) . "' )";

		$this->add_join( "LEFT JOIN {$wpdb->postmeta} as postmeta_1 ON post.ID = postmeta_1.post_id" );

		$where   = array();
		$where[] = "postmeta_1.meta_key = '_sku'";
		$where[] = "postmeta_1.meta_value IN {$skus_sql_in}";

		return array(
			'where' => "(" . implode( " AND ", $where ) . ")",
		);
	}

	protected function gen_sql_product_sellers( $sellers ) {
		global $wpdb;

		$sellers_sql_in = "( '" . implode( "','", array_map( 'esc_sql', $sellers ) ) . "' )";

		$where = array();
		$where[] = "post.post_author IN {$sellers_sql_in}";

		return array(
			'where' => "(" . implode( " AND ", $where ) . ")",
		);
	}

	protected function gen_sql_product_tags( $tags ) {
		return $this->gen_sql_by_term_ids( $tags );
	}

	protected function gen_sql_product_categories( $categories ) {
		return $this->gen_sql_by_term_ids( $categories );
	}

	protected function gen_sql_product_category_slug( $category_slugs ) {
		global $wpdb;
		$where = array();

		$category_slugs_sql_in = "( '" . implode( "','", array_map( 'esc_sql', $category_slugs ) ) . "' )";

		$this->add_join( "LEFT JOIN {$wpdb->term_relationships} as term_rel_1 ON post.ID = term_rel_1.object_id" );
		$this->add_join( "LEFT JOIN {$wpdb->term_taxonomy} as term_tax_1 ON term_rel_1.term_taxonomy_id = term_tax_1.term_taxonomy_id" );
		$this->add_join( "LEFT JOIN {$wpdb->terms} as term_1 ON term_tax_1.term_id = term_1.term_id" );

		$where[] = "term_1.slug IN {$category_slugs_sql_in}";

		return array(
			'where' => implode( " ", $where ),
		);
	}

	protected function gen_sql_product_custom_fields( $values ) {
		global $wpdb;
		$where = array();

		$custom_fields = array();
		foreach ( $values as $value ) {
			$value = explode( "=", $value );
			if ( count( $value ) !== 2 ) {
				continue;
			}
			$custom_fields[] = array(
				'key'   => $value[0],
				'value' => $value[1],
			);
		}

		$this->add_join( "LEFT JOIN {$wpdb->postmeta} as postmeta_1 ON post.ID = postmeta_1.post_id" );

		$tmp_where = [];
		foreach ( $custom_fields as $custom_field ) {
			$tmp_where[] = "postmeta_1.meta_key='{$custom_field['key']}' AND postmeta_1.meta_value='{$custom_field['value']}'";
		}

		$where[] = "( " . implode( " OR ", $tmp_where ) . " )";


		return array(
			'where' => implode( " ", $where ),
		);
	}

	protected function gen_sql_product_attributes( $attributes ) {
		return $this->gen_sql_by_term_ids( $attributes );
	}

	protected function gen_sql_custom_taxonomy( $tax_name, $values ) {
		return $this->gen_sql_by_term_ids( $values );
	}

	protected function gen_sql_by_term_ids( $term_ids ) {
		$term_ids_sql_in = "( '" . implode( "','", array_map( 'esc_sql', $term_ids ) ) . "' )";

		global $wpdb;
		$where = array();

		$this->add_join( "LEFT JOIN {$wpdb->term_relationships} as term_rel_1 ON post.ID = term_rel_1.object_id" );
		$this->add_join( "LEFT JOIN {$wpdb->term_taxonomy} as term_tax_1 ON term_rel_1.term_taxonomy_id = term_tax_1.term_taxonomy_id" );

		$where[] = "term_tax_1.term_id IN {$term_ids_sql_in}";

		return array(
			'where' => implode( " ", $where ),
		);
	}

	protected function gen_sql_any() {
		return array(
			'where' => array(),
		);
	}

	public function is_empty() {
		return count( $this->applied_rules ) === 0;
	}
}