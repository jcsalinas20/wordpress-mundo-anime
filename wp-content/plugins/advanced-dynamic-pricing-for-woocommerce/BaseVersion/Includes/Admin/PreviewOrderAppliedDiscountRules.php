<?php

namespace ADP\BaseVersion\Includes\Admin;

use ADP\BaseVersion\Includes\Common\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PreviewOrderAppliedDiscountRules {
	public static function output( $rules ) {
		$html = '<style> .wdp-aplied-rules, .wdp-aplied-rules td:first-child { width: 100%; } </style>';

		$html .= '<div class="wc-order-preview-table-wrapper">';
		$html .= '<table class="wc-order-preview-table">';
		$html .= '<tr><td><strong class="ui-sortable-handle">' . __( 'Applied discounts', 'advanced-dynamic-pricing-for-woocommerce' ) . '</strong></td></tr>';
		foreach ( $rules as $row ) {
			$html   .= '<tr>' . '<td><a href=' . self::rule_url( $row ) . '>' . $row->title . '</a></td>' . '<td>';
			$amount = floatval( $row->amount + $row->extra + $row->gifted_amount );
			$html   .= empty( $amount ) ? '-' : wc_price( $amount );
			$html   .= '</td>' . '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';

		return $html;
	}

	public static function add_data( $export_data, $order ) {
		$rules = Database::get_applied_rules_for_order( $export_data['order_number'] );
		if ( ! empty( $rules ) ) {
			$export_data['rules_rendered'] = self::output( $rules );
		}

		return $export_data;
	}

	public static function render() {
		echo '{{{ data.rules_rendered }}}';
	}

	private static function rule_url( $row ) {
		return add_query_arg( array(
			'rule_id' => $row->id,
			'tab'     => 'rules',
		), admin_url( 'admin.php?page=wdp_settings' ) );
	}
}