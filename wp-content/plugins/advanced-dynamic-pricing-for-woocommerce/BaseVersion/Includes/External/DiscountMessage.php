<?php

namespace ADP\BaseVersion\Includes\External;

use ADP\BaseVersion\Includes\Cart\Structures\CouponCart;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\Customizer\Customizer;
use ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\External\WC\WcTotalsFacade;
use ADP\BaseVersion\Includes\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class DiscountMessage {
	const PANEL_KEY = 'discount_message';

	const CONTEXT_CART = 'cart';
	const CONTEXT_MINI_CART = 'mini-cart';
	const CONTEXT_CHECKOUT = 'checkout';

	protected $amount_saved_label;

	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context            = $context;
		$this->amount_saved_label = __( "Amount Saved", 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $customizer Customizer
	 */
	public function set_theme_options_email( $customizer ) {
		return;
	}

	/**
	 * @param $customizer Customizer
	 */
	public function set_theme_options( $customizer ) {
		// wait until filling get_theme_mod()
		add_action( 'wp_loaded', function () use ( $customizer ) {
			$contexts = array(
				self::CONTEXT_CART      => array( $this, 'output_cart_amount_saved' ),
				self::CONTEXT_MINI_CART => array( $this, 'output_mini_cart_amount_saved' ),
				self::CONTEXT_CHECKOUT  => array( $this, 'output_checkout_amount_saved' ),
			);

			$this->install_message_hooks( $customizer, $contexts );
		} );
	}

	/**
	 * @param Customizer $customizer
	 * @param array      $contexts
	 *
	 */
	protected function install_message_hooks( $customizer, $contexts ) {
		$theme_options = $customizer->get_theme_options();

		if ( ! isset( $theme_options[ self::PANEL_KEY ] ) ) {
			return;
		}

		$theme_options = $theme_options[ self::PANEL_KEY ];

		if ( isset( $theme_options['global']['amount_saved_label'] ) ) {
			$this->amount_saved_label = _x( $theme_options['global']['amount_saved_label'], "theme option 'amount saved label", 'advanced-dynamic-pricing-for-woocommerce' );
		}

		foreach ( $contexts as $context => $callback ) {
			if ( ! isset( $theme_options[ $context ]['enable'], $theme_options[ $context ]['position'] ) ) {
				continue;
			}

			if ( $theme_options[ $context ]['enable'] ) {
				if ( has_action( "wdp_{$context}_discount_message_install" ) ) {
					do_action( "wdp_{$context}_discount_message_install", $this,
						$theme_options[ $context ]['position'] );
				} else {
					add_action( $theme_options[ $context ]['position'], $callback, 10 );
				}
			}
		}
	}

	public function get_option( $option, $default = false ) {
		return $this->context->get_option( $option );
	}

	public function output_cart_amount_saved() {
		$includeTax   = 'incl' === $this->context->get_tax_display_cart_mode();
		$amount_saved = $this->getAmountSaved( $includeTax );

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_CART, $amount_saved );
		}
	}

	public function output_mini_cart_amount_saved() {
		$includeTax   = 'incl' === $this->context->get_tax_display_cart_mode();
		$amount_saved = $this->getAmountSaved( $includeTax );

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_MINI_CART, $amount_saved );
		}
	}

	public function output_checkout_amount_saved() {
		$includeTax   = 'incl' === $this->context->get_tax_display_cart_mode();
		$amount_saved = $this->getAmountSaved( $includeTax );

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_CHECKOUT, $amount_saved );
		}
	}

	public function output_amount_saved( $context, $amount_saved ) {
		switch ( $context ) {
			case self::CONTEXT_CART:
				$template = 'cart-totals.php';
				break;
			case self::CONTEXT_MINI_CART:
				$template = 'mini-cart.php';
				break;
			case self::CONTEXT_CHECKOUT:
				$template = 'cart-totals-checkout.php';
				break;
			default:
				$template = null;
				break;
		}

		if ( is_null( $template ) ) {
			return;
		}

		echo Frontend::wdp_get_template( $template, array(
			'amount_saved' => $amount_saved,
			'title'        => $this->amount_saved_label,
		), 'amount-saved' );
	}

	public function getAmountSaved( $includeTax ) {
		$cartItems    = WC()->cart->cart_contents;
		$totalsFacade = new WcTotalsFacade( $this->context, WC()->cart );

		$amount_saved = floatval( 0 );

		foreach ( $cartItems as $cartItemKey => $cartItem ) {
			$facade = new WcCartItemFacade( $this->context, $cartItem );

			if ( $includeTax ) {
				$original = ( $facade->getOriginalPriceWithoutTax() + $facade->getOriginalPriceTax() ) * $facade->getQty();
				$current  = $facade->getSubtotal() + $facade->getSubtotalTax();
			} else {
				$original = $facade->getOriginalPriceWithoutTax() * $facade->getQty();
				$current  = $facade->getSubtotal();
			}

			$amount_saved += $original - $current;
		}

		foreach ( WC()->cart->get_coupons() as $wcCoupon ) {
			$code    = $wcCoupon->get_code();
			$adpData = $wcCoupon->get_meta( 'adp', true, 'edit' );
			$coupon  = isset( $adpData['parts'] ) ? reset( $adpData['parts'] ) : null;

			if ( $coupon ) {
				/** @var $coupon CouponCart */
				$amount_saved += WC()->cart->get_coupon_discount_amount( $code, ! $includeTax );
			}
		}

		foreach ( $totalsFacade->getFees() as $fee ) {
			foreach ( WC()->cart->get_fees() as $cartFee ) {
				if ( $fee->getName() === $cartFee->name ) {
					if ( $includeTax ) {
						$amount_saved -= $cartFee->total + $cartFee->tax;
					} else {
						$amount_saved -= $cartFee->total;
					}
				}
			}
		}

		return floatval( apply_filters( 'wdp_amount_saved', $amount_saved, $cartItems ) );
	}

}
