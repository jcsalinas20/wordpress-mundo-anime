<?php

namespace ADP\BaseVersion\Includes\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Database {
	const TABLE_RULES = 'wdp_rules';
	const TABLE_ORDER_RULES = 'wdp_orders';
	const TABLE_ORDER_ITEMS_RULES = 'wdp_order_items';

	public static function create_database() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		// Table for Rulles (discounts)
		$rules_table_name = $wpdb->prefix . self::TABLE_RULES;

		$sql = /** @lang MySQL */
			"CREATE TABLE {$rules_table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            deleted TINYINT(1) DEFAULT 0,
            enabled TINYINT(1) DEFAULT 1,
            exclusive TINYINT(1) DEFAULT 0,
            type VARCHAR(50),
            title VARCHAR(255),
            priority INT,
            options TEXT,
            additional TEXT,
            conditions TEXT,
            filters TEXT,
            limits TEXT,
            product_adjustments TEXT,
            sortable_blocks_priority TEXT,
            bulk_adjustments TEXT,
            role_discounts TEXT,
            cart_adjustments TEXT,
            get_products TEXT,
            PRIMARY KEY  (id),
            KEY deleted (deleted),
            KEY enabled (enabled)
        ) $charset_collate;";
		dbDelta( $sql );

		$order_rules_table_name = $wpdb->prefix . self::TABLE_ORDER_RULES;

		// Table for history of applied rules
		$sql = /** @lang MySQL */
			"CREATE TABLE {$order_rules_table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            rule_id INT NOT NULL,
            amount DECIMAL(50,2) DEFAULT 0,
            qty INT DEFAULT 0,
            extra DECIMAL(50,2) DEFAULT 0,
            shipping DECIMAL(50,2) DEFAULT 0,
            is_free_shipping TINYINT(1) DEFAULT 0,
            gifted_amount DECIMAL(50,2) DEFAULT 0,
            gifted_qty INT DEFAULT 0,
            date DATETIME,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id, rule_id),
            KEY rule_id (rule_id),
            KEY date (date)
        ) $charset_collate;";
		dbDelta( $sql );

		$order_items_rules_table_name = $wpdb->prefix . self::TABLE_ORDER_ITEMS_RULES;

		// Table for history of applied rules
		$sql = /** @lang MySQL */
			"CREATE TABLE {$order_items_rules_table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            rule_id INT NOT NULL,
            amount DECIMAL(50,2) DEFAULT 0,
            qty INT DEFAULT 0,
            gifted_amount DECIMAL(50,2) DEFAULT 0,
            gifted_qty INT DEFAULT 0,
            date DATETIME,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id, rule_id, product_id),
            KEY rule_id (rule_id),
            KEY product_id (product_id),
            KEY date (date)
        ) $charset_collate;";
		dbDelta( $sql );
	}

	public static function delete_database() {
		global $wpdb;

		$rules_table_name = $wpdb->prefix . self::TABLE_RULES;
		$wpdb->query( "DROP TABLE IF EXISTS $rules_table_name" );

		$order_rules_table_name = $wpdb->prefix . self::TABLE_ORDER_RULES;
		$wpdb->query( "DROP TABLE IF EXISTS $order_rules_table_name" );

		$order_rules_items_table_name = $wpdb->prefix . self::TABLE_ORDER_ITEMS_RULES;
		$wpdb->query( "DROP TABLE IF EXISTS $order_rules_items_table_name" );
	}

	/**
	 * get_rules
	 *
	 * @param array $args ( array|string types, bool active_only, bool include_deleted, bool exclusive, int|array id )
	 *
	 * @return array filtered rules
	 */
	public static function get_rules( $args = array() ) {
//    	return self::get_test_rules();
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		$sql = "SELECT * FROM $table WHERE 1 ";

		if ( isset( $args['types'] ) ) {
			$types        = (array) $args['types'];
			$placeholders = array_fill( 0, count( $types ), '%s' );
			$placeholders = implode( ', ', $placeholders );
			$sql          = $wpdb->prepare( "$sql AND type IN($placeholders)", $types );
		}

		$active_only = isset( $args['active_only'] ) && $args['active_only'];
		if ( $active_only ) {
			$sql .= ' AND enabled = 1';
		}

		$include_deleted = isset( $args['include_deleted'] ) && $args['include_deleted'];
		if ( ! $include_deleted ) {
			$sql .= ' AND deleted = 0';
		}

		if ( isset( $args['exclusive'] ) ) {
			$show_exclusive = $args['exclusive'] ? 1 : 0;
			$sql            = "$sql AND exclusive = $show_exclusive";
		}

		if ( isset( $args['id'] ) ) {
			$ids          = (array) $args['id'];
			$placeholders = array_fill( 0, count( $ids ), '%d' );
			$placeholders = implode( ', ', $placeholders );
			$sql          = $wpdb->prepare( "$sql AND id IN($placeholders)", $ids );
		}

		$sql .= " ORDER BY exclusive DESC, priority";

		if ( isset( $args['limit'] ) ) {
			$sql_limit = "";
			$limit     = $args['limit'];

			$count = null;
			$start = null;

			if ( is_string( $limit ) ) {
				$count = $limit;
			} elseif ( is_array( $limit ) ) {
				if ( 1 === count( $limit ) ) {
					$count = reset( $limit );
				} elseif ( 2 === count( $limit ) ) {
					list( $start, $count ) = $limit;
				}
			}

			if ( ! is_null( $count ) ) {
				$count = (int) $count;
				if ( ! is_null( $start ) ) {
					$start     = (int) $start;
					$sql_limit = sprintf( "LIMIT %d, %d", $start, $count );
				} else {
					$sql_limit = sprintf( "LIMIT %d", $count );
				}
			}

			$sql .= " " . $sql_limit;
		}

		$rows = $wpdb->get_results( $sql );

		$rows = array_map( function ( $item ) {
			$result = array(
				'id'                       => $item->id,
				'title'                    => $item->title,
				'type'                     => $item->type,
				'exclusive'                => $item->exclusive,
				'priority'                 => $item->priority,
				'enabled'                  => $item->enabled ? 'on' : 'off',
				'options'                  => unserialize( $item->options ),
				'additional'               => unserialize( $item->additional ),
				'conditions'               => unserialize( $item->conditions ),
				'filters'                  => unserialize( $item->filters ),
				'limits'                   => unserialize( $item->limits ),
				'product_adjustments'      => unserialize( $item->product_adjustments ),
				'sortable_blocks_priority' => unserialize( $item->sortable_blocks_priority ),
				'bulk_adjustments'         => unserialize( $item->bulk_adjustments ),
				'role_discounts'           => unserialize( $item->role_discounts ),
				'cart_adjustments'         => unserialize( $item->cart_adjustments ),
				'get_products'             => unserialize( $item->get_products ),
			);
			$result = self::decode_array_text_fields( $result );

			return $result;
		}, $rows );

		if ( isset( $args['product'] ) ) {
			$new_rows = array();
			foreach ( $rows as $row ) {
				foreach ( $row['filters'] as $filter ) {
					if ( $filter['type'] == 'products' ) {
						foreach ( $filter['value'] as $value ) {
							if ( (int) $value == $args['product'] ) {
								$new_rows[] = $row;
								break 2;
							}
						}
					}
				}
			}
			$rows = $new_rows;
		}

		foreach ( $rows as &$row ) {
			$row = self::validate_bulk_adjustments( $row );
		}

		$rows = self::migrate_to_2_2_3( $rows );

        $country_states = WC()->countries->get_states();

		// fix collections in conditions
		foreach ( $rows as &$row ) {
			foreach ( $row['conditions'] as &$condition ) {
				$type = $condition['type'];
				$options = &$condition['options'];

				if ( $type === 'product_collections' ) {
					$options = array( $options[0], $options[2], $options[3]);
				} elseif ( $type === 'amount_product_collections' ) {
					$options = array( $options[1], $options[2], $options[3]);
				} elseif ( $type === 'shipping_state' ) {
                    $comparison_value = $condition['options'][1];

				    $new_comparison_value = array();
				    $changed = false;

				    foreach ( $comparison_value as $value ) {
					    if ( strpos( $value, ':' ) === false ) {
						    foreach ( $country_states as $country_code => $states ) {
							    if ( isset( $states[ $value ] ) ) {
								    $new_comparison_value[] = $country_code . ":" . $value;
								    $changed = true;
							    }
						    }
					    }
				    }

				    if ( $changed ) {
					    $condition['options'][1] = $new_comparison_value;
				    }
				}
			}
		}

		return $rows;
	}

	private static function validate_bulk_adjustments( $row ) {
		if ( empty( $row['bulk_adjustments']['ranges'] ) ) {
			return $row;
		}

		$ranges = $row['bulk_adjustments']['ranges'];
		$ranges = array_values( array_filter( array_map( function ( $range ) {
			return isset( $range['to'], $range['from'], $range['value'] ) ? $range : false;
		}, $ranges ) ) );


		usort( $ranges, function ( $a, $b ) {
			if ( $a["to"] === '' && $b["to"] === '' ) {
				return 0;
			} elseif ( $a["to"] === '' ) {
				return 1;
			} elseif ( $b["to"] === '' ) {
				return - 1;
			}

			return (integer) $a["to"] - (integer) $b["to"];
		} );

		$previous_range = null;
		foreach ( $ranges as &$range ) {
			$from = $range['from'];
			if ( $from === '' ) {
				if ( is_null( $previous_range ) ) {
					$from = 1;
				} else {
					if ( $previous_range['to'] !== '' ) {
						$from = (integer) $previous_range['to'] + 1;
					}
				}
			}
			$range['from']  = $from;
			$previous_range = $range;
		}

		$row['bulk_adjustments']['ranges'] = $ranges;

		return $row;
	}

	private static function migrate_to_2_2_3( $rows ) {
		// add selector "in_list/not_in_list" for amount conditions
		foreach ( $rows as &$row ) {
			foreach ( $row['conditions'] as &$condition ) {
				if ( 'amount_' === substr( $condition['type'], 0,
						strlen( 'amount_' ) ) && 3 === count( $condition['options'] ) ) {
					array_unshift( $condition['options'], 'in_list' );
				}
			}
		}

		return $rows;
	}

	public static function get_rules_count( $args = array() ) {
		//    	return self::get_test_rules();
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		$sql = "SELECT COUNT(*) FROM $table WHERE 1 ";

		if ( isset( $args['types'] ) ) {
			$types        = (array) $args['types'];
			$placeholders = array_fill( 0, count( $types ), '%s' );
			$placeholders = implode( ', ', $placeholders );
			$sql          = $wpdb->prepare( "$sql AND type IN($placeholders)", $types );
		}

		$active_only = isset( $args['active_only'] ) && $args['active_only'];
		if ( $active_only ) {
			$sql .= ' AND enabled = 1';
		}

		$include_deleted = isset( $args['include_deleted'] ) && $args['include_deleted'];
		if ( ! $include_deleted ) {
			$sql .= ' AND deleted = 0';
		}

		if ( isset( $args['exclusive'] ) ) {
			$show_exclusive = $args['exclusive'] ? 1 : 0;
			$sql            = "$sql AND exclusive = $show_exclusive";
		}

		if ( isset( $args['id'] ) ) {
			$ids          = (array) $args['id'];
			$placeholders = array_fill( 0, count( $ids ), '%d' );
			$placeholders = implode( ', ', $placeholders );
			$sql          = $wpdb->prepare( "$sql AND id IN($placeholders)", $ids );
		}

		return $wpdb->get_var( $sql );
	}

	private static function decode_array_text_fields( $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = self::decode_array_text_fields( $value );
			} else {
				$value = trim( htmlspecialchars_decode( $value ) );
			}
		}

		return $array;
	}

	private static function get_test_rules() {
		$test_rules = array(
			array(
				'id'                  => 1,
				'title'               => 'Exclusive',
				'type'                => 'package',
				'exclusive'           => true,
				'priority'            => '1',
				'enabled'             => 'on',
				'conditions'          => array(
					array(
						'type'    => 'time',
						'options' => array(
							'from',
							'18:00',
						),
					),
					array(
						'type'    => 'shipping_country',
						'options' => array(
							'in_list',
							array( 'US' ),
						),
					),
					array(
						'type'    => 'cart_subtotal',
						'options' => array(
							'>',
							'100',
						),
					),
					array(
						'type'    => 'product_attributes',
						'options' => array(
							'in_list',
							array( 171 ),
						),
					),
				),
				'filters'             => array(
					array(
						'qty'   => '1',
						'type'  => 'products', // products, product_categories, product_attributes, product_tags
						'value' => array(
							'3678',
						),
					),
					array(
						'qty'   => '1',
						'type'  => 'products',
						'value' => array(
							'4085',
							'3888',
						),
					),
				),
				'limits'              => array(
					array(
						'type'    => 'max_usage',
						'options' => '10',
					),
				),
				'options'             => array(
					'repeat' => '9', // -1, 0, 1, 2, ..., 10
				), //TODO: fill this +
				'product_adjustments' => array(
					'type'             => 'split', // total, split
					//					'type'  => 'split', // total, split
					'total'            => array(
						'type'  => 'price__fixed', // discount__amount, discount__percentage, price__fixed
						'value' => '13.7',
					),
					'split'            => array(
						array(
							'type'  => 'discount__percentage', // discount__amount, discount__percentage, price__fixed
							'value' => '1',
						),
						array(
							'type'  => 'discount__percentage',
							'value' => '2',
						),
					),
					'max_discount_sum' => '10',
				),
				'cart_adjustments'    => array(
					array(
						// discount__amount, discount__percentage, fee__amount, fee__percentage, free__shipping, reduced__shipping
						'type'    => 'discount__percentage',
						'options' => '1',
					),
					array(
						'type'    => 'discount__percentage',
						'options' => '3',
					),
				),
				'get_products'        => array(
					'repeat' => '10',
					'value'  => array(
						array(
							'type'  => 'products', // only products for now
							'qty'   => '1',
							'value' => array( '4647' ),
						),
						array(
							'type'  => 'products', // only products for now
							'qty'   => '3',
							'value' => array( '4646' ),
						),
					),
				),
				'bulk_adjustments'    => array(
					'type'          => 'bulk', // bulk, tier
					'discount_type' => 'discount__percentage', // discount__amount, discount__percentage, price__fixed
					'ranges'        => array(
						array(
							'from'  => '1',
							'to'    => '1',
							'value' => '50',
						),
						array(
							'from'  => '2',
							'to'    => '10',
							'value' => '90',
						),
					),
				),
			),
			array(
				'id'                  => 2,
				'title'               => 'Exclusive 2',
				'type'                => 'package',
				'exclusive'           => true,
				'priority'            => '2',
				'enabled'             => 'on',
				'conditions'          => array(
					array(
						'type'    => 'date',
						'options' => array(
							'from',
							'2017-10-19',
						),
					),
					array(
						'type'    => 'days_of_week',
						'options' => array(
							'in_list',
							array( 0, 4 ),
						),
					),
				),
				'filters'             => array(
					array(
						'qty'   => '1',
						'type'  => 'products', // products, product_categories, product_attributes, product_tags
						'value' => array(
							'4646',
						),
					),
				),
				'limits'              => array(
					array(
						'type'    => 'max_usage',
						'options' => '10',
					),
				),
				'options'             => array(
					'repeat' => '8', // -1, 0, 1, 2, ..., 10
				),
				'product_adjustments' => array(
					'type'  => 'total', // total, split
					'total' => array(
						'type'  => 'discount__percentage', // discount__amount, discount__percentage, price__fixed
						'value' => '50',
					),
				),
				'cart_adjustments'    => array(
					// discount__amount, discount__percentage, fee__amount, fee__percentage, free__shipping, reduced__shipping
					array(
						'type'    => 'free__shipping',
						'options' => '',
					),
					array(
						'type'    => 'fee__percentage',
						'options' => '3',
					),
				),
				'get_products'        => array(),
				'bulk_adjustments'    => array(),
			),
		);

//		$test_rules = array();

		return $test_rules;
	}

	public static function delete_all_rules() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;
		$sql   = "DELETE FROM $table";
		$wpdb->query( $sql );
	}

	public static function mark_rules_as_deleted( $type ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		$sql = "UPDATE $table SET deleted = 1 WHERE type ";
		if ( is_array( $type ) ) {
			$format = implode( ', ', array_fill( 0, count( $type ), '%s' ) );
			$sql    = $wpdb->prepare( "$sql IN ($format)", $type );
		} else {
			$sql = $wpdb->prepare( "$sql = %s", $type );
		}

		$wpdb->query( $sql );
	}

	public static function mark_rule_as_deleted( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		$data  = array( 'deleted' => 1 );
		$where = array( 'id' => $rule_id );
		$wpdb->update( $table, $data, $where );
	}

	public static function store_rule( $data, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		if ( ! empty( $id ) ) {
			$where  = array( 'id' => $id );
			$result = $wpdb->update( $table, $data, $where );

			return $id;
		} else {
			$result = $wpdb->insert( $table, $data );

			return $wpdb->insert_id;
		}
	}

	public static function add_order_stats( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_ORDER_RULES;

		$data = array_merge( array(
			'order_id'         => 0,
			'rule_id'          => 0,
			'amount'           => 0,
			'extra'            => 0,
			'shipping'         => 0,
			'is_free_shipping' => 0,
			'gifted_amount'    => 0,
			'gifted_qty'       => 0,
			'date'             => 0,
		), $data );

		$wpdb->replace( $table, $data );
	}

	public static function add_product_stats( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_ORDER_ITEMS_RULES;

		$data = array_merge( array(
			'order_id'      => 0,
			'product_id'    => 0,
			'rule_id'       => 0,
			'amount'        => 0,
			'qty'           => 0,
			'gifted_amount' => 0,
			'gifted_qty'    => 0,
			'date'          => 0,
		), $data );

		$wpdb->replace( $table, $data );
	}

	public static function get_applied_rules_for_order( $order_id ) {
		global $wpdb;

		$table_order_rules = $wpdb->prefix . self::TABLE_ORDER_RULES;
		$table_rules       = $wpdb->prefix . self::TABLE_RULES;

		$sql = $wpdb->prepare( "
            SELECT *
            FROM $table_order_rules LEFT JOIN $table_rules ON $table_order_rules.rule_id = $table_rules.id
            WHERE order_id = %d
            ORDER BY amount DESC
        ", $order_id );

		$rows = $wpdb->get_results( $sql );

		return $rows;
	}

	public static function get_count_of_rule_usages( $rule_id ) {
		global $wpdb;

		$table_order_rules = $wpdb->prefix . self::TABLE_ORDER_RULES;

		$sql = $wpdb->prepare( "
            SELECT COUNT(*)
            FROM {$table_order_rules}
            WHERE rule_id = %d
        ", $rule_id );

		$value = $wpdb->get_var( $sql );

		return (int) $value;
	}

	public static function get_count_of_rule_usages_per_customer( $rule_id, $customer_id ) {
		global $wpdb;

		$table_order_rules = $wpdb->prefix . self::TABLE_ORDER_RULES;

		$customer_orders_ids = get_posts( array(
			'fields'      => 'ids',
			'numberposts' => - 1,
			'meta_key'    => '_customer_user',
			'meta_value'  => $customer_id,
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
		) );
		if ( empty( $customer_orders_ids ) ) {
			return 0;
		}

		$value = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_order_rules}
		            WHERE rule_id = $rule_id  AND order_id IN (" . implode( ',', $customer_orders_ids ) . ")" );

		return (int) $value;
	}

	public static function mark_as_disabled_by_plugin( $rule_id ) {
		global $wpdb;

		$table_rules = $wpdb->prefix . self::TABLE_RULES;

		$sql = $wpdb->prepare( "
            SELECT {$table_rules}.additional
            FROM {$table_rules}
            WHERE 'rule_id' = %d
        ", $rule_id );

		$additional                       = $wpdb->get_var( $sql );
		$additional                       = unserialize( $additional );
		$additional['disabled_by_plugin'] = 1;

		$data  = array( 'enabled' => 0, 'additional' => serialize( $additional ) );
		$where = array( 'id' => $rule_id );
		$wpdb->update( $table_rules, $data, $where );
	}

	public static function delete_conditions_from_db_by_types( $types ) {

		$rules = array_merge( self::get_rules(), self::get_rules( array(
				'exclusive' => true,
			) ) );

		foreach ( $rules as $key_rule => $rule ) {
			if ( isset( $rule['conditions'] ) ) {
				$conditions = $rule['conditions'];
			} else {
				continue;
			}
			foreach ( $conditions as $key_condition => $condition ) {
				if ( in_array( $condition['type'], $types ) ) {
					unset( $conditions[ $key_condition ] );
				}
			}
			$conditions = array_values( $conditions );

			$data = array( 'conditions' => serialize( $conditions ) );
			self::store_rule( $data, $rule['id'] );
		}
	}

	public static function is_condition_type_active( $types ) {
		$rules = array_merge( self::get_rules( array(
				'active_only' => true,
			) ), self::get_rules( array(
				'exclusive'   => true,
				'active_only' => true,
			) ) );

		foreach ( $rules as $key_rule => $rule ) {
			if ( isset( $rule['conditions'] ) ) {
				$conditions = $rule['conditions'];
			} else {
				continue;
			}
			foreach ( $conditions as $key_condition => $condition ) {
				if ( in_array( $condition['type'], $types ) ) {
					return true;
				}
			}

		}

		return false;
	}

	public static function disable_rule( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_RULES;

		$data  = array( 'enabled' => 0 );
		$where = array( 'id' => $rule_id );
		$wpdb->update( $table, $data, $where );
	}

	public static function get_only_required_child_post_meta_data( $parent_id ) {
		global $wpdb;

		$required_keys = array(
			'_sale_price',
			'_regular_price',
			'_sale_price_dates_from',
			'_sale_price_dates_to',
			'_tax_status',
			'_tax_class',
			'_sku',
		);
		$required_keys = '"' . implode( '","', $required_keys ) . '"';

		$meta_list = $wpdb->get_results( "
			SELECT post_id, meta_key, meta_value 
			FROM $wpdb->postmeta 
			WHERE 
				post_id IN (SELECT ID FROM $wpdb->posts WHERE post_parent = $parent_id ) 
				AND
				(meta_key IN ( $required_keys ) OR meta_key LIKE 'attribute_%')
			ORDER BY post_id ASC", ARRAY_A );

		$post_data = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_parent = $parent_id ", OBJECT_K );

		$required_data = array();

		foreach ( $post_data as $post_datum ) {
			$post_datum->meta = array();

			$required_data[ $post_datum->ID ] = $post_datum;
		}

		foreach ( $meta_list as $row ) {
			$value                                                      = maybe_unserialize( $row['meta_value'] );
			$required_data[ $row['post_id'] ]->meta[ $row['meta_key'] ] = $value;
		}

		return $required_data;
	}
}
