<?php

namespace ADP\BaseVersion\Includes\External\PriceFormatters;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\PriceFunctions;
use ADP\BaseVersion\Includes\Product\ProcessedProductSimple;
use ADP\BaseVersion\Includes\Product\ProcessedVariableProduct;

class DiscountRangeFormatter {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var Formatter
	 */
	protected $formatter;

	/**
	 * @var PriceFunctions
	 */
	protected $priceFunctions;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context   = $context;
		$this->formatter = new Formatter( $context );
		$this->formatter->setTemplate( htmlspecialchars_decode( $this->context->get_option( 'replace_price_with_min_bulk_price_template' ) ) );
		$this->priceFunctions = new PriceFunctions( $context );
	}

	/**
	 * @param ProcessedProductSimple|ProcessedVariableProduct $processedProduct
	 *
	 * @return bool
	 */
	public function isNeeded( $processedProduct ) {
		if ( ! $this->context->get_option( "replace_price_with_min_bulk_price",false ) ) {
			return false;
		}

		if ( apply_filters( "adp_force_discount_range_formatter", false, $this->context, $processedProduct ) ) {
			return true;
		}

		return $this->context->is_catalog() && $processedProduct->isAffectedByRangeDiscount();
	}

	/**
	 * @param ProcessedProductSimple|ProcessedVariableProduct $processedProduct
	 *
	 * @return string
	 */
	public function getHtml( $processedProduct ) {
		$product = $processedProduct->getProduct();

		$minDiscountRangePrice = null;
		$initialPrice          = null;
		$pos                   = null;
		if ( $processedProduct instanceof ProcessedVariableProduct ) {
			if ( $discountRangeProcessed = $processedProduct->getLowestRangeDiscountPriceProduct() ) {
				$minDiscountRangePrice = $discountRangeProcessed->getMinDiscountRangePrice();
				$initialPrice          = $discountRangeProcessed->getOriginalPrice();
				$pos = $discountRangeProcessed->getPos();
			}
		} else {
			$minDiscountRangePrice = $processedProduct->getMinDiscountRangePrice();
			$initialPrice          = $processedProduct->getOriginalPrice();
			$pos = $processedProduct->getPos();
		}

		$replacements = array(
			'price'         => ! is_null( $minDiscountRangePrice ) ? $this->priceFunctions->format( $minDiscountRangePrice ) : "",
			'price_suffix'  => $product->get_price_suffix(),
			'price_striked' => ! is_null( $initialPrice ) ? '<del>' . $this->priceFunctions->format( $initialPrice ) . '</del>' : "",
			'initial_price' => ! is_null( $initialPrice ) ? $this->priceFunctions->format( $initialPrice ) : "",
			'Nth_item'      => $pos ? $this->add_suffix_of( $pos ) : "",
		);

		return $this->formatter->applyReplacements( $replacements );
	}

	/**
	 * Add ordinal indicator
	 *
	 * @param $value integer|float
	 *
	 * @return string
	 */
	protected function add_suffix_of( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $value;
		}

		$value = (string) $value;

		$mod10  = $value % 10;
		$mod100 = $value % 100;

		if ( $mod10 === 1 && $mod100 !== 11 ) {
			return $value . "st";
		}

		if ( $mod10 === 2 && $mod100 !== 12 ) {
			return $value . "nd";
		}

		if ( $mod10 === 3 && $mod100 !== 13 ) {
			return $value . "rd";
		}

		return $value . "th";
	}
}