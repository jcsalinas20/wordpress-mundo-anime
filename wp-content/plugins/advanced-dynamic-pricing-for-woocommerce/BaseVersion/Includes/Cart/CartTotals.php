<?php

namespace ADP\BaseVersion\Includes\Cart;

use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\External\WC\WcCustomerConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartTotals {
	/**
	 * @var Cart
	 */
	protected $cart;

	/**
	 * @var WcCustomerConverter
	 */
	protected $wcCustomerConverter;

	/**
	 * @param Cart $cart
	 */
	public function __construct( $cart ) {
		$this->cart                = $cart;
		$this->wcCustomerConverter = new WcCustomerConverter( $cart->get_context()->getGlobalContext() );
	}

	/**
	 * @param bool $inclTax
	 *
	 * @return float
	 */
	protected function calculateItemsSubtotals( $inclTax = true ) {
		/** @see \WC_Cart_Totals::calculate_item_subtotals */

		$cart                            = $this->cart;
		$cartContext                     = $cart->get_context();
		$context                         = $cartContext->getGlobalContext();
		$adjust_non_base_location_prices = apply_filters( 'woocommerce_adjust_non_base_location_prices', true );
		$is_customer_vat_exempt          = $cart->get_context()->getCustomer()->isVatExempt();
		$calculate_tax                   = $context->get_is_tax_enabled() && ! $is_customer_vat_exempt;

		$itemsSubtotals = floatval( 0 );
		foreach ( $cart->getItems() as $item ) {
			$product            = $item->getWcItem()->getProduct();
			$price_includes_tax = $context->get_is_prices_include_tax();
			$taxable            = $context->get_is_tax_enabled() && 'taxable' === $product->get_tax_status();
			$price              = $item->getTotalPrice();

			$wcCustomer = $this->wcCustomerConverter->convertToWcCustomer( $cartContext->getCustomer() );

			if ( $context->get_is_tax_enabled() ) {
				$tax_rates = \WC_Tax::get_rates( $product->get_tax_class(), $wcCustomer );
			} else {
				$tax_rates = array();
			}

			if ( $price_includes_tax ) {
				if ( $is_customer_vat_exempt ) {

					/** @see \WC_Cart_Totals::remove_item_base_taxes */
					if ( $price_includes_tax && $taxable ) {
						if ( apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ) {
							$base_tax_rates = \WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );
						} else {
							$base_tax_rates = $tax_rates;
						}

						// Work out a new base price without the shop's base tax.
						$taxes = \WC_Tax::calc_tax( $price, $base_tax_rates, true );

						// Now we have a new item price (excluding TAX).
						$price              = round( $price - array_sum( $taxes ) );
						$price_includes_tax = false;
					}

				} elseif ( $adjust_non_base_location_prices ) {

					/** @see \WC_Cart_Totals::adjust_non_base_location_price */
					if ( $price_includes_tax && $taxable ) {
						$base_tax_rates = \WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );

						if ( $tax_rates !== $base_tax_rates ) {
							// Work out a new base price without the shop's base tax.
							$taxes     = \WC_Tax::calc_tax( $price, $base_tax_rates, true );
							$new_taxes = \WC_Tax::calc_tax( $price - array_sum( $taxes ), $tax_rates, false );

							// Now we have a new item price.
							$price = $price - array_sum( $taxes ) + array_sum( $new_taxes );
						}
					}

				}
			}

			$subtotal     = $price;
			$subtotal_tax = floatval( 0 );

			if ( $calculate_tax && $taxable ) {
				$subtotal_taxes = \WC_Tax::calc_tax( $subtotal, $tax_rates, $price_includes_tax );
				$subtotal_tax   = array_sum( array_map( array( $this, 'round_line_tax' ), $subtotal_taxes ) );

				if ( $price_includes_tax ) {
					// Use unrounded taxes so we can re-calculate from the orders screen accurately later.
					$subtotal = $subtotal - array_sum( $subtotal_taxes );
				}
			}

			$itemsSubtotals += $subtotal;

			if ( $inclTax ) {
				$itemsSubtotals += $subtotal_tax;
			}
		}

		return $itemsSubtotals;
	}

	protected static function round_line_tax( $value, $in_cents = true ) {
		if ( ! self::round_at_subtotal() ) {
			$value = wc_round_tax_total( $value, $in_cents ? 0 : null );
		}
		return $value;
	}

	protected static function round_at_subtotal() {
		return 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' );
	}

	/**
	 * @return float
	 */
	public function getSubtotal() {
		return $this->calculateItemsSubtotals( false );
	}
}
