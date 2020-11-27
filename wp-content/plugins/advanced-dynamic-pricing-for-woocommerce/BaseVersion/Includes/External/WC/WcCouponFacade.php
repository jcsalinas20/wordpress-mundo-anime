<?php

namespace ADP\BaseVersion\Includes\External\WC;

use ADP\BaseVersion\Includes\Cart\Structures\Coupon;
use ADP\BaseVersion\Includes\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WcCouponFacade {
	const TYPE_PERCENT = 'percent';
	const TYPE_FIXED_CART = 'fixed_cart';
	const TYPE_FIXED_PRODUCT = 'fixed_product';

	const TYPE_CUSTOM_PERCENT_WITH_LIMIT = 'wdp_percent_limit_coupon';

	const KEY_ADP = 'adp';
	const KEY_ADP_PARTS = 'parts';

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var \WC_Coupon
	 */
	public $coupon;

	/**
	 * @var Coupon[]
	 */
	protected $parts;

	/**
	 * @param Context    $context
	 * @param \WC_Coupon $coupon
	 */
	public function __construct( $context, $coupon ) {
		$this->context = $context;
		$this->coupon  = $coupon;

		$this->parts = array();
		$this->fetchData();
	}

	protected function fetchData() {
		$adpMeta = $this->coupon->get_meta( self::KEY_ADP, true );

		$this->parts = isset( $adpMeta[ self::KEY_ADP_PARTS ] ) ? $adpMeta[ self::KEY_ADP_PARTS ] : array();
	}

	/**
	 * @return Coupon[]
	 */
	public function getParts() {
		return $this->parts;
	}

	/**
	 * @param Coupon[] $parts
	 */
	public function setParts( $parts ) {
		$this->parts = array();

		foreach ( $parts as $part ) {
			if ( $part instanceof Coupon ) {
				$this->parts[] = $part;
			}
		}
	}

	public function updateCoupon() {
		$this->coupon->update_meta_data( self::KEY_ADP, array(
			self::KEY_ADP_PARTS => $this->parts,
		) );
	}
}