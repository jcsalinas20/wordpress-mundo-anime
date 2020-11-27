<?php

namespace ADP\BaseVersion\Includes\Admin;

use \ADP\BaseVersion\Includes\Common\Database;
use \ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Context;
use ADP\Factory;

class Importer {
	public static function import_rules( $data, $reset_rules ) {
		if ( $reset_rules ) {
			Database::delete_all_rules();
		}
		$imported = array();
		$ruleStorage = Factory::get("External_RuleStorage", new Context() );
		$rulesCol = $ruleStorage->buildRules($data);
		$exporter = Factory::get("Admin_Exporter", new Context() );

		foreach ( $rulesCol->getRules() as $ruleObject ) {
			$rule = $exporter->convertRule( $ruleObject );
			//unset( $rule['id'] );

			$rule['enabled'] = ( isset( $rule['enabled'] ) && $rule['enabled'] === 'on' ) ? 1 : 0;

			if ( ! empty( $rule['filters'] ) ) {
				foreach ( $rule['filters'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = self::convert_elements_from_name_to_id( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['get_products']['value'] ) ) {
				foreach ( $rule['get_products']['value'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = self::convert_elements_from_name_to_id( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as &$item ) {
					if ( ! isset( $item['options'][2] ) ) {
						continue;
					}

					$item['options'][2] = self::convert_elements_from_name_to_id( $item['options'][2], $item['type'] );
				}
				unset( $item );
			}

			$attributes = array(
				'options',
				'conditions',
				'filters',
				'limits',
				'cart_adjustments',
				'product_adjustments',
				'bulk_adjustments',
				'role_discounts',
				'get_products',
				'sortable_blocks_priority',
				'additional',
			);
			foreach ( $attributes as $attr ) {
				$rule[ $attr ] = serialize( isset( $rule[ $attr ] ) ? $rule[ $attr ] : array() );
			}

			$imported[] = Database::store_rule( $rule );
		}

		return $imported;
	}

	protected static function convert_elements_from_name_to_id( $items, $type ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return $items;
		}
		foreach ( $items as &$value ) {
			if ( 'products' === $type ) {
				$value = Helpers::get_product_id( $value );
			} elseif ( 'product_categories' === $type ) {
				$value = Helpers::get_category_id( $value );
			} elseif ( 'product_tags' === $type ) {
				$value = Helpers::get_tag_id( $value );
			} elseif ( 'product_attributes' === $type ) {
				$value = Helpers::get_attribute_id( $value );
			}

			if ( empty( $value ) ) {
				$value = 0;
			}
		}

		return $items;
	}


}