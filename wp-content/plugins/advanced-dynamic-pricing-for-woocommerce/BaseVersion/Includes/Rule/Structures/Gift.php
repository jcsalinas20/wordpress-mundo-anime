<?php

namespace ADP\BaseVersion\Includes\Rule\Structures;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\CacheHelper;
use Exception;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Gift {
	const TYPE_PRODUCT = 'product';
	const TYPE_CLONE_ADJUSTED = 'clone';
	const AVAILABLE_TYPES = array(
		self::TYPE_PRODUCT,
		self::TYPE_CLONE_ADJUSTED,
	);

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var array
	 */
	protected $values;

	/**
	 * @var float
	 */
	protected $qty;

	/**
	 * @param Context $context
	 * @param string  $type
	 */
	public function __construct( $context, $type ) {
		if ( ! in_array( $type, self::AVAILABLE_TYPES ) ) {
			$context->handle_error( new Exception( sprintf( "Gift type '%s' not supported", $type ) ) );
		}

		$this->type   = $type;
		$this->values = array();
		$this->qty    = floatval( 0 );
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param array $values
	 *
	 * @return Gift
	 */
	public function setValues( $values ) {
		if ( is_array( $values ) ) {
			$filteredValues = array();
			foreach ( $values as $value ) {
				if ( $this->type === self::TYPE_PRODUCT ) {
					$product = CacheHelper::getWcProduct( $value );
					//  sorry, we do not gift variable products
					if ( $product instanceof WC_Product && ! $product->is_type( 'variable' ) ) {
						$filteredValues[] = $value;
					}
				} else {
					$filteredValues[] = $value;
				}
			}

			$this->values = $filteredValues;
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}

	/**
	 * @param float $qty
	 *
	 * @return Gift
	 */
	public function setQty( $qty ) {
		$this->qty = floatval( $qty );

		return $this;
	}

	/**
	 * @return float
	 */
	public function getQty() {
		return $this->qty;
	}

	public function isValid() {
		return isset( $this->type, $this->values, $this->qty ) && ( count( $this->values ) > 0 || $this->type === $this::TYPE_CLONE_ADJUSTED ) && $this->qty > 0;
	}
}
