<?php

namespace ADP\BaseVersion\Includes\External;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\PriceFunctions;

class PriceAjax {
	const ACTION_GET_SUBTOTAL_HTML = 'get_price_product_with_bulk_table';

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var Engine
	 */
	protected $engine;

	/**
	 * @param Context $context
	 * @param Engine  $engine
	 */
	public function __construct( $context, $engine ) {
		$this->context = $context;
		$this->engine  = $engine;
		$this->priceFunctions = new PriceFunctions($context);
	}

	public function register() {
		add_action( "wp_ajax_nopriv_" . self::ACTION_GET_SUBTOTAL_HTML, array( $this, "ajaxCalculatePrice" ) );
		add_action( "wp_ajax_" . self::ACTION_GET_SUBTOTAL_HTML, array( $this, "ajaxCalculatePrice" ) );
	}

	public function ajaxCalculatePrice() {
		$prodId = ! empty( $_REQUEST['product_id'] ) ? intval( $_REQUEST['product_id'] ) : false;
		$qty    = ! empty( $_REQUEST['qty'] ) ? floatval( $_REQUEST['qty'] ) : false;

		$pageData  = ! empty( $_REQUEST['page_data'] ) ? (array) $_REQUEST['page_data'] : array();
		$isProduct = isset( $pageData['is_product'] ) ? wc_string_to_bool( $pageData['is_product'] ) : null;

		if ( ! empty( $_REQUEST['custom_price'] ) ) {
			$custom_price = $_REQUEST['custom_price'];
			if ( preg_match( '/\d+\\' . wc_get_price_decimal_separator() . '\d+/', $custom_price,
					$matches ) !== false ) {
				$custom_price = floatval( reset( $matches ) );
			} else {
				$custom_price = false;
			}

		} else {
			$custom_price = false;
		}

		if ( ! $prodId || ! $qty ) {
			wp_send_json_error();
		}

		$context = $this->context;

		$context->set_props( array(
			$context::ADMIN           => false,
			$context::AJAX            => false,
			$context::WC_PRODUCT_PAGE => $isProduct,
		) );

		$product = CacheHelper::getWcProduct( $prodId );
		if ( $custom_price !== false ) {
			$product->set_price( $custom_price );
		}

		$processedProduct = $this->engine->getProductProcessor()->calculateProduct( $product, $qty );

		if ( is_null( $processedProduct ) ) {
			wp_send_json_error();
		}

		$priceDisplay = $this->engine->getPriceDisplay();
		$strikethrough = $priceDisplay::priceHtmlIsAllowToStrikethroughPrice( $context );

		if ( ! $priceDisplay->priceHtmlIsModifyNeeded() ) {
			wp_send_json_success( array(
				'price_html'    => $this->priceFunctions->format( $this->priceFunctions->getPriceToDisplay( $product ) ),
				'subtotal_html' => $this->priceFunctions->format( $this->priceFunctions->getPriceToDisplay( $product, array( 'qty' => $qty ) ) ),
			) );

			return;
		}

		wp_send_json_success( array(
			'price_html'    => $processedProduct->getPriceHtml( $strikethrough ),
			'subtotal_html' => $processedProduct->getSubtotalHtml( $strikethrough ),
		) );
	}
}