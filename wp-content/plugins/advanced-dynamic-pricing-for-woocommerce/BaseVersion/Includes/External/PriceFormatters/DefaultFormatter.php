<?php

namespace ADP\BaseVersion\Includes\External\PriceFormatters;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\PriceFunctions;
use ADP\BaseVersion\Includes\Product\ProcessedProductSimple;
use ADP\BaseVersion\Includes\Product\ProcessedVariableProduct;

class DefaultFormatter {
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
		$this->formatter->setTemplate( htmlspecialchars_decode( $this->context->get_option( "price_html_template", "{{price_html}}" ) ) );
		$this->priceFunctions = new PriceFunctions( $context );
	}

	/**
	 * @param ProcessedProductSimple|ProcessedVariableProduct $processedProduct
	 *
	 * @return bool
	 */
	public function isNeeded( $processedProduct ) {
		if ( $processedProduct instanceof ProcessedVariableProduct ) {
			return false;
		}

		$index = $processedProduct->getQtyAlreadyInCart() + $processedProduct->getQty();

		return $this->context->get_option( "enable_product_html_template", false ) && $index > 1;
	}

	/**
	 * @param string $priceHtml
	 * @param ProcessedProductSimple $processedProduct
	 *
	 * @return string
	 */
	public function getHtml( $priceHtml, $processedProduct ) {
		$index = $processedProduct->getQtyAlreadyInCart() + $processedProduct->getQty();

		$replacements = array(
			'price_html'          => $priceHtml,
			'Nth_item'            => $this->add_suffix_of( $index ),
			'qty_already_in_cart' => $processedProduct->getQtyAlreadyInCart(),
			'price_suffix'        => get_option( 'woocommerce_price_display_suffix' ),
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