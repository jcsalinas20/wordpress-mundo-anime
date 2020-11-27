<?php

namespace ADP\BaseVersion\Includes\External\WC;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Product\ProcessedProductSimple;
use WC_Product;
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * todo add tests!
 *
 * @package ADP\BaseVersion\Includes\External\WC
 */
class PriceFunctions {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * @param string $currency
	 *
	 * @return string
	 */
	public function getCurrencySymbol( $currency = '' ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			return get_woocommerce_currency_symbol( $currency );
		}

		$currencyController = $this->context->currencyController;

		if ( ! $currency ) {
			$currency = $currencyController->getCurrentCurrency()->getCode();
		}

		$symbols = $currencyController->getCurrencySymbols();

		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';
	}

	/**
	 * @param float $price
	 * @param array $args
	 *
	 * @return string
	 */
	public function format( $price, $args = array() ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			return wc_price( $price, $args );
		}

		$priceSettings   = $this->context->priceSettings;
		$currentCurrency = $this->context->currencyController->getCurrentCurrency();

		$args = wp_parse_args( // todo replace global wp_parse_args???
			$args, array(
			'ex_tax_label'       => false,
			'currency'           => $currentCurrency->getCode(),
			'decimal_separator'  => $priceSettings->getDecimalSeparator(),
			'thousand_separator' => $priceSettings->getThousandSeparator(),
			'decimals'           => $priceSettings->getDecimals(),
			'price_format'       => $priceSettings->getPriceFormat(),
		) );

		$negative = $price < 0;
		$price    = floatval( $negative ? $price * - 1 : $price );
		$price    = number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );

		$formatted_price = ( $negative ? '-' : '' ) . sprintf( $args['price_format'],
				'<span class="woocommerce-Price-currencySymbol">' . $this->getCurrencySymbol( $args['currency'] ) . '</span>',
				$price );
		$return          = '<span class="woocommerce-Price-amount amount">' . $formatted_price . '</span>';

		if ( $args['ex_tax_label'] && $priceSettings->isTaxEnabled() ) {
			$return .= ' <small class="woocommerce-Price-taxLabel tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>'; // todo replace global WC()->countries->ex_tax_or_vat()
		}

		return $return;
	}

	/**
	 * @param WC_Product $product WC_Product object.
	 * @param array      $args Optional arguments to pass product quantity and price.
	 *
	 * @return float
	 * @see wc_get_price_including_tax()
	 */
	public function getPriceIncludingTax( $product, $args = array() ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			$this->forcePriceDecimals();
			$price = wc_get_price_including_tax( $product, $args );
			$this->stopForcePriceDecimals();

			return $price;
		}

		$args = wp_parse_args( // todo replace global wp_parse_args???
			$args, array(
			'qty'                             => '',
			'price'                           => '',
			'adjust_non_base_location_prices' => true,
		) );

		// always get product product price without hooks!
		$price = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : $product->get_price( 'edit' );

		$qty = '' !== $args['qty'] ? max( 0.0, (float) $args['qty'] ) : 1;

		if ( '' === $price ) {
			return '';
		} elseif ( empty( $qty ) ) {
			return 0.0;
		}

		$priceSettings = $this->context->priceSettings;

		$line_price   = $price * $qty;
		$return_price = $line_price;

		if ( $product->is_taxable() ) {
			if ( ! $priceSettings->isIncludeTax() ) {
				$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
				$taxes     = WC_Tax::calc_tax( $line_price, $tax_rates, false );

				if ( 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
					$taxes_total = array_sum( $taxes );
				} else {
					$taxes_total = array_sum( array_map( 'wc_round_tax_total', $taxes ) );
				}

				$return_price = round( $line_price + $taxes_total, $priceSettings->getDecimals() );
			} else {
				$tax_rates      = WC_Tax::get_rates( $product->get_tax_class() );
				$base_tax_rates = WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );

				/**
				 * If the customer is excempt from VAT, remove the taxes here.
				 * Either remove the base or the user taxes depending on woocommerce_adjust_non_base_location_prices setting.
				 */
				if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) { // @codingStandardsIgnoreLine.
					$remove_taxes = $args['adjust_non_base_location_prices'] ? WC_Tax::calc_tax( $line_price,
						$base_tax_rates, true ) : WC_Tax::calc_tax( $line_price, $tax_rates, true );

					if ( 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
						$remove_taxes_total = array_sum( $remove_taxes );
					} else {
						$remove_taxes_total = array_sum( array_map( 'wc_round_tax_total', $remove_taxes ) );
					}

					$return_price = round( $line_price - $remove_taxes_total, $priceSettings->getDecimals() );

					/**
					 * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
					 * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
					 * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
					 */
				} elseif ( $tax_rates !== $base_tax_rates && $args['adjust_non_base_location_prices'] ) {
					$base_taxes   = WC_Tax::calc_tax( $line_price, $base_tax_rates, true );
					$modded_taxes = WC_Tax::calc_tax( $line_price - array_sum( $base_taxes ), $tax_rates, false );

					if ( 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
						$base_taxes_total   = array_sum( $base_taxes );
						$modded_taxes_total = array_sum( $modded_taxes );
					} else {
						$base_taxes_total   = array_sum( array_map( 'wc_round_tax_total', $base_taxes ) );
						$modded_taxes_total = array_sum( array_map( 'wc_round_tax_total', $modded_taxes ) );
					}

					$return_price = round( $line_price - $base_taxes_total + $modded_taxes_total,
						$priceSettings->getDecimals() );
				}
			}
		}

		return $return_price;
	}

	/**
	 * @param WC_Product $product WC_Product object.
	 * @param array      $args Optional arguments to pass product quantity and price.
	 *
	 * @return float
	 * @see wc_get_price_excluding_tax()
	 */
	public function getPriceExcludingTax( $product, $args = array() ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			$this->forcePriceDecimals();
			$price = wc_get_price_excluding_tax( $product, $args );
			$this->stopForcePriceDecimals();

			return $price;
		}

		$args = wp_parse_args( // todo replace global wp_parse_args???
			$args, array(
			'qty'                             => '',
			'price'                           => '',
			'adjust_non_base_location_prices' => true,
		) );

		// always get product product price without hooks!
		$price = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : $product->get_price();

		$qty = '' !== $args['qty'] ? max( 0.0, (float) $args['qty'] ) : 1;

		if ( '' === $price ) {
			return '';
		} elseif ( empty( $qty ) ) {
			return 0.0;
		}

		$priceSettings = $this->context->priceSettings;

		$line_price = $price * $qty;

		if ( $product->is_taxable() && $priceSettings->isIncludeTax() ) {
			$tax_rates      = WC_Tax::get_rates( $product->get_tax_class() );
			$base_tax_rates = WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );
			$remove_taxes   = $args['adjust_non_base_location_prices'] ? WC_Tax::calc_tax( $line_price, $base_tax_rates,
				true ) : WC_Tax::calc_tax( $line_price, $tax_rates, true );
			$return_price   = $line_price - array_sum( $remove_taxes ); // Unrounded since we're dealing with tax inclusive prices. Matches logic in cart-totals class. @see adjust_non_base_location_price.
		} else {
			$return_price = $line_price;
		}

		return $return_price;
	}

	/**
	 * @param WC_Product $product WC_Product object.
	 * @param array      $args Optional arguments to pass product quantity and price.
	 *
	 * @return float
	 * @see wc_get_price_to_display()
	 */
	public function getPriceToDisplay( $product, $args = array() ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			$this->forcePriceDecimals();
			$price = wc_get_price_to_display( $product, $args );
			$this->stopForcePriceDecimals();

			return $price;
		}

		$args = wp_parse_args(  // todo replace global wp_parse_args???
			$args, array(
			'qty'   => 1,

			// always get product product price without hooks!
			'price' => $product->get_price( 'edit' ),
		) );

		$price = $args['price'];
		$qty   = $args['qty'];

		return 'incl' === $this->context->get_tax_display_shop_mode() ? $this->getPriceIncludingTax( $product,
			array( 'qty' => $qty, 'price' => $price, ) ) : $this->getPriceExcludingTax( $product,
			array( 'qty' => $qty, 'price' => $price, ) );
	}

	/**
	 * @param string $from Price from.
	 * @param string $to Price to.
	 *
	 * @return string
	 * @see wc_format_price_range()
	 */
	function formatRange( $from, $to ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			return wc_format_price_range( $from, $to );
		}

		/* translators: 1: price from 2: price to */

		return sprintf( _x( '%1$s &ndash; %2$s', 'Price range: from-to', 'woocommerce' ),
			is_numeric( $from ) ? wc_price( $from ) : $from, is_numeric( $to ) ? wc_price( $to ) : $to );
	}

	/**
	 * @param string $regular_price Regular price.
	 * @param string $sale_price Sale price.
	 *
	 * @return string
	 * @see wc_format_sale_price()
	 */
	function formatSalePrice( $regular_price, $sale_price ) {
		if ( $this->context->isUsingGlobalPriceSettings() ) {
			return wc_format_sale_price( $regular_price, $sale_price );
		}

		$del = is_numeric( $regular_price ) ? $this->format( $regular_price ) : $regular_price;
		$ins = is_numeric( $sale_price ) ? $this->format( $sale_price ) : $sale_price;

		return '<del>' . $del . '</del> <ins>' . $ins . '</ins>';
	}

	/**
	 * @param float|null             $price
	 * @param ProcessedProductSimple $prod
	 *
	 * @return float
	 */
	public function getProcProductPriceToDisplay( $prod, $price = null ) {
		if ( is_null( $price ) ) {
			$price = $prod->getPrice();
		}

		return $this->getPriceToDisplay( $prod->getProduct(), array( 'price' => $price, 'qty' => 1 ) );
	}

	protected function forcePriceDecimals() {
		if ( ! $this->context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			add_filter( 'wc_get_price_decimals', array( $this, 'setPriceDecimals' ), 10, 0 );
		}
	}

	public function setPriceDecimals() {
		return $this->context->priceSettings->getDecimals() + 2;
	}

	protected function stopForcePriceDecimals() {
		remove_filter( 'wc_get_price_decimals', array( $this, 'setPriceDecimals' ), 10 );
	}
}