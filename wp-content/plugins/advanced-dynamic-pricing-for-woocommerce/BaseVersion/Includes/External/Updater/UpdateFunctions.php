<?php

namespace ADP\BaseVersion\Includes\External\Updater;

use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\Rule\OptionsConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class UpdateFunctions {
	public static function call_update_function( $function ) {
		if ( method_exists( __CLASS__, $function ) ) {
			self::$function();
		}
	}

	public static function migrate_to_2_2_3() {
		global $wpdb;

		$table = $wpdb->prefix . Database::TABLE_RULES;
		$sql   = "SELECT id, conditions FROM $table";
		$rows  = $wpdb->get_results( $sql );

		$rows = array_map( function ( $item ) {
			$result = array(
				'id'         => $item->id,
				'conditions' => unserialize( $item->conditions ),
			);

			return $result;
		}, $rows );

		foreach ( $rows as &$row ) {
			$prev_row = $row;
			foreach ( $row['conditions'] as &$condition ) {
				if ( 'amount_' === substr( $condition['type'], 0,
						strlen( 'amount_' ) ) && 3 === count( $condition['options'] ) ) {
					array_unshift( $condition['options'], 'in_list' );
				}
			}
			if ( $prev_row != $row ) {
				$row['conditions'] = serialize( $row['conditions'] );
				$result            = $wpdb->update( $table, array( 'conditions' => $row['conditions'] ),
					array( 'id' => $row['id'] ) );
			}
		}
	}

	public static function migrate_to_3_0_0() {
		global $wpdb;

		$table = $wpdb->prefix . Database::TABLE_RULES;
		$sql   = "SELECT id, conditions, limits, cart_adjustments FROM $table";
		$rows  = $wpdb->get_results( $sql );

		$rows = array_map( function ( $item ) {
			$result = array(
				'id'         => $item->id,
				'conditions' 	   => unserialize( $item->conditions ),
				'limits'	 	   => unserialize( $item->limits ),
				'cart_adjustments' => unserialize( $item->cart_adjustments ),
			);

			return $result;
		}, $rows );

		foreach ( $rows as &$row ) {
			$prev_row = $row;
			foreach( $row['conditions'] as &$data ) {
				$data 	   = OptionsConverter::convertCondition( $data );
			}
			foreach( $row['cart_adjustments'] as &$data ) {
				$data 	   = OptionsConverter::convertCartAdj( $data );
			}
			foreach( $row['limits'] as &$data ) {
				$data 	   = OptionsConverter::convertLimit( $data );
			}
			if( $prev_row != $row ) {
				$row['conditions'] 		 = serialize( $row['conditions'] );
				$row['cart_adjustments'] = serialize( $row['cart_adjustments'] );
				$row['limits'] 			 = serialize( $row['limits'] );
				$result            = $wpdb->update( $table, array( 
					'conditions' 	   => $row['conditions'],
					'cart_adjustments' => $row['cart_adjustments'],
					'limits'		   => $row['limits'],
				),
					array( 'id' => $row['id'] ) );
			}
		}
	}
}

