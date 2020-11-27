<?php

namespace ADP\BaseVersion\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class OverrideCentsStrategy {
	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * @param float $price
	 * @param \WC_Product $product
	 *
	 * @return float
	 */
	public function maybeOverrideCents( $price, $product ) {
		if ( $custom_price = apply_filters( "wdp_custom_override_cents", false, $price, $this->context, $product ) ) {
			return $custom_price;
		}

		if ( ! $this->context->get_option( 'is_override_cents' ) ) {
			return $price;
		}

		$prices_ends_with = $this->context->get_option( 'prices_ends_with' );

		$price_fraction     = $price - intval( $price );
		$new_price_fraction = $prices_ends_with / 100;

		$round_new_price_fraction = round( $new_price_fraction );

		if ( 0 == intval( $price ) and 0 < $new_price_fraction ) {
			$price = $new_price_fraction;

			return $price;
		}

		if ( $round_new_price_fraction ) {

			if ( $price_fraction <= $new_price_fraction - round( 1 / 2, 2 ) ) {
				$price = intval( $price ) - 1 + $new_price_fraction;
			} else {
				$price = intval( $price ) + $new_price_fraction;
			}

		} else {

			if ( $price_fraction >= $new_price_fraction + round( 1 / 2, 2 ) ) {
				$price = intval( $price ) + 1 + $new_price_fraction;
			} else {
				$price = intval( $price ) + $new_price_fraction;
			}

		}

		return $price;
	}
}
