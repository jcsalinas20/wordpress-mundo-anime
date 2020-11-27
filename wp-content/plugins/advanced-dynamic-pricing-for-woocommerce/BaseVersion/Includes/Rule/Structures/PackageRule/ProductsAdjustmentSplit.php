<?php

namespace ADP\BaseVersion\Includes\Rule\Structures\PackageRule;

use ADP\BaseVersion\Includes\Rule\Structures\Discount;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ProductsAdjustmentSplit {
	const AVAILABLE_DISCOUNT_TYPES = array(
		Discount::TYPE_AMOUNT,
		Discount::TYPE_FIXED_VALUE,
		Discount::TYPE_PERCENTAGE,
	);

	/**
	 * Discount by package item position
	 *
	 * @var Discount[]
	 */
	protected $discounts;

	/**
	 * @var float
	 */
	protected $maxAvailableAmount;

	/**
	 * Coupon or Fee
	 *
	 * @var bool
	 */
	protected $replaceAsCartAdjustment;

	/**
	 * @var string
	 */
	protected $replaceCartAdjustmentCode;

	/**
	 * @param Discount[] $discounts
	 */
	public function __construct( $discounts ) {
		foreach ( $discounts as $key => $discount ) {
			if ( $discount instanceof Discount && in_array( $discount->getType(), self::AVAILABLE_DISCOUNT_TYPES ) ) {
				$this->discounts[ $key ] = $discount;
			}
		}

		$this->replaceAsCartAdjustment   = false;
		$this->replaceCartAdjustmentCode = null;
	}

	/**
	 * @param Discount[] $discounts
	 */
	public function setDiscounts( $discounts ) {
		$this->discounts = $discounts;
	}

	/**
	 * @param mixed $key
	 *
	 * @return Discount|null
	 */
	public function getDiscount( $key ) {
		/**
		 * TODO replace reset( $this->discounts ) on null
		 * tweak for split adjustments
		 */
		return isset( $this->discounts[ $key ] ) ? $this->discounts[ $key ] : reset( $this->discounts );
	}

	/**
	 * @return Discount[]
	 */
	public function getDiscounts() {
		return $this->discounts;
	}

	/**
	 * @param float $value
	 */
	public function setMaxAvailableAmount( $value ) {
		$value = floatval( $value );

		$this->maxAvailableAmount = $value;
	}

	/**
	 * @param bool $replace
	 */
	public function setReplaceAsCartAdjustment( $replace ) {
		$this->replaceAsCartAdjustment = boolval( $replace );
	}

	/**
	 * @return bool
	 */
	public function isMaxAvailableAmountExists() {
		return ! is_null( $this->maxAvailableAmount );
	}

	/**
	 * @return float
	 */
	public function getMaxAvailableAmount() {
		return $this->maxAvailableAmount;
	}

	/**
	 * @return bool
	 */
	public function isReplaceWithCartAdjustment() {
		return $this->replaceCartAdjustmentCode && $this->replaceAsCartAdjustment;
	}

	/**
	 * @param string $code
	 */
	public function setReplaceCartAdjustmentCode( $code ) {
		$this->replaceCartAdjustmentCode = (string) $code;
	}

	/**
	 * @return string
	 */
	public function getReplaceCartAdjustmentCode() {
		return $this->replaceCartAdjustmentCode;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return count( $this->discounts ) > 0;
	}
}
