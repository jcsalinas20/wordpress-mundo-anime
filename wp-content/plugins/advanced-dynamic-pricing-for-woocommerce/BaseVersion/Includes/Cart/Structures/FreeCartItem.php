<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

use Exception;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FreeCartItem {
	/**
	 * @var WC_Product
	 */
	protected $product;

	/**
	 * @var float
	 */
	protected $initialPrice;

	/**
	 * @var float
	 */
	protected $initialTax;

	/**
	 * @var float
	 */
	public $qty;

	/**
	 * @var float
	 */
	protected $qtyAlreadyInWcCart;

	/**
	 * @var bool
	 */
	protected $replaceWithCoupon;

	/**
	 * @var string
	 */
	protected $replaceCouponCode;

	protected $ruleId;

	public $originalWcCartItem = array();

	/**
	 * @var int
	 */
	protected $pos;

	/**
	 * TODO detect who gift the product
	 *
	 * @param WC_Product $product
	 * @param float       $qty
	 * @param integer     $ruleId
	 *
	 * @throws Exception
	 */
	public function __construct( $product, $qty, $ruleId ) {
		if ( ! ( $product instanceof WC_Product ) ) {
			throw new Exception( sprintf( "Unsupported class of the product: %s", gettype( $product ) ) );
		}

		$this->product         = $product;
		$this->qty             = floatval( $qty );
		$this->ruleId          = $ruleId;
		$this->qtyAlreadyInWcCart = 0;
		$this->replaceWithCoupon = false;
		$this->replaceCouponCode = '';

		$this->initialPrice = floatval( $product->get_price( '' ) );
		$this->initialTax   = floatval( 0 );
	}

	public function setReplaceWithCoupon($replace) {
		$this->replaceWithCoupon = boolval($replace);
	}

	public function isReplaceWithCoupon() {
		return $this->replaceWithCoupon;
	}

	public function setQtyAlreadyInWcCart($qty) {
		$this->qtyAlreadyInWcCart = $qty;
	}

	public function getRuleId() {
		return $this->ruleId;
	}

	/**
	 * @return bool
	 */
	public function getQtyAlreadyInWcCart() {
		return $this->qtyAlreadyInWcCart;
	}

	/**
	 * @return WC_Product
	 */
	public function getProduct() {
		return $this->product;
	}

	/**
	 * @param float $initialPrice
	 * @param float $initialTax
	 */
	public function installInitialPrices( $initialPrice, $initialTax ) {
		$this->initialPrice = floatval( $initialPrice );
		$this->initialTax   = floatval( $initialTax );
	}

	public function getInitialPrice() {
		return $this->initialPrice;
	}

	public function getInitialTax() {
		return $this->initialTax;
	}

	public function hash() {
		$data = array(
			$this->product->get_id(),
			$this->product->get_parent_id(),
			$this->ruleId,
//			$this->initialPrice,
			$this->replaceWithCoupon,
			$this->replaceCouponCode,
		);

		return md5( json_encode( $data ) );
	}

	/**
	 * @param int $pos
	 */
	public function setPos( $pos ) {
		$this->pos = $pos;
	}

	/**
	 * @return int
	 */
	public function getPos() {
		return $this->pos;
	}

	/**
	 * @return float
	 */
	public function getQty() {
		return $this->qty;
	}

	/**
	 * @return string
	 */
	public function getReplaceCouponCode() {
		return $this->replaceCouponCode;
	}

	/**
	 * @param string $replaceCouponCode
	 */
	public function setReplaceCouponCode( $replaceCouponCode ) {
		$this->replaceCouponCode = $replaceCouponCode;
	}
}
