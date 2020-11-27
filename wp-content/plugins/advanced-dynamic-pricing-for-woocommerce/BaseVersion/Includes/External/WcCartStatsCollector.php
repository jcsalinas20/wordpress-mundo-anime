<?php

namespace ADP\BaseVersion\Includes\External;

use ADP\BaseVersion\Includes\Cart\Structures\ShippingAdjustment;
use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\WcTotalsFacade;
use \ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;
use WC_Cart;
use WC_Shipping_Rate;
use WooCommerce;
use function WC;

if( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WcCartStatsCollector {
	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context = $context;
	}

	public function setActionCheckoutOrderProcessed() {
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'checkout_order_processed' ), 10, 3 );
    }

    public function unsetActionCheckoutOrderProcessed() {
        remove_action( 'woocommerce_checkout_order_processed', array( $this, 'checkout_order_processed' ), 10 );
    }

    public function checkout_order_processed( $order_id, $posted_data, $order ) {
        if( ! isset( WC()->cart ) ) {
            return;
        }

		list( $order_stats, $product_stats ) = $this->collect_wc_cart_stats( WC() );

		$order_date = current_time( 'mysql' );

		foreach ( $order_stats as $rule_id => $stats_item ) {
			$stats_item = array_merge( array(
				'order_id'         => $order_id,
				'rule_id'          => $rule_id,
				'amount'           => 0,
				'extra'            => 0,
				'shipping'         => 0,
				'is_free_shipping' => 0,
				'gifted_amount'    => 0,
				'gifted_qty'       => 0,
				'date'             => $order_date,
			), $stats_item );
			Database::add_order_stats( $stats_item );
		}

		foreach ( $product_stats as $product_id => $by_rule ) {
			foreach ( $by_rule as $rule_id => $stats_item ) {
				$stats_item = array_merge( array(
					'order_id'      => $order_id,
					'product_id'    => $product_id,
					'rule_id'       => $rule_id,
					'qty'           => 0,
					'amount'        => 0,
					'gifted_amount' => 0,
					'gifted_qty'    => 0,
					'date'          => $order_date,
				), $stats_item );

				Database::add_product_stats( $stats_item );
			}
		}
	}

	/**
	 * @param WooCommerce $wc
	 *
	 * @return array
	 */
	private function collect_wc_cart_stats( $wc ) {
		$order_stats   = array();
		$product_stats = array();

		$wc_cart = $wc->cart;

		$cart_items = $wc_cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
            $itemFacade = new WcCartItemFacade( $this->context, $cart_item );
            $rules = $itemFacade->getDiscounts();

			if ( empty( $rules ) ) {
				continue;
			}

			$product_id = $itemFacade->getProductId();
			foreach ( $rules as $rule_id => $amounts ) {
                $amount = is_array( $amounts ) ? array_sum( $amounts ) : $amounts;
				//add stat rows
				if ( ! isset( $order_stats[ $rule_id ] ) ) {
					$order_stats[ $rule_id ] = array(
						'amount'           => 0,
						'qty'              => 0,
						'gifted_qty'       => 0,
						'gifted_amount'    => 0,
						'shipping'         => 0,
						'is_free_shipping' => 0,
						'extra'            => 0
					);
				}
				if ( ! isset( $product_stats[ $product_id ][ $rule_id ] ) ) {
					$product_stats[ $product_id ][ $rule_id ] = array(
						'amount'        => 0,
						'qty'           => 0,
						'gifted_qty'    => 0,
						'gifted_amount' => 0
					);
				}

				if( $itemFacade->isFreeItem() ) {
					$prefix = 'gifted_';
				} else {
					$prefix = '';
				}
				// order
				$order_stats[ $rule_id ][ $prefix . 'qty' ]    += $itemFacade->getQty();
				$order_stats[ $rule_id ][ $prefix . 'amount' ] += $amount * $itemFacade->getQty();
				// product
				$product_stats[ $product_id ][ $rule_id ][ $prefix . 'qty' ]    += $itemFacade->getQty();
				$product_stats[ $product_id ][ $rule_id ][ $prefix . 'amount' ] += $amount * $itemFacade->getQty();
			}
		}

		$this->inject_wc_cart_coupon_stats( $wc_cart, $order_stats );
		$this->inject_wc_cart_fee_stats( $wc_cart, $order_stats );
		$this->inject_wc_cart_shipping_stats( $wc, $order_stats );

		return array( $order_stats, $product_stats );
	}

	/**
	 * @param WC_Cart $wc_cart
	 * @param array    $order_stats
	 */
	private function inject_wc_cart_coupon_stats( $wc_cart, &$order_stats ) {
		$totalsFacade = new WcTotalsFacade( $this->context, $wc_cart );

		$singleCoupons  = $totalsFacade->getSingleCoupons();
		$groupedCoupons = $totalsFacade->getGroupedCoupons();

		if ( ! $singleCoupons && ! $groupedCoupons ) {
			return;
		}

		foreach ( $wc_cart->get_coupon_discount_totals() as $couponCode => $amount ) {
			if ( isset( $groupedCoupons[ $couponCode ] ) ) {
				foreach ( $groupedCoupons[ $couponCode ] as $coupon ) {
					$ruleId = $coupon->getRuleId();
					$value   = $coupon->getValue();

					if ( ! isset( $order_stats[ $ruleId ] ) ) {
						$order_stats[ $ruleId ] = array();
					}

					if ( ! isset( $order_stats[ $ruleId ]['extra'] ) ) {
						$order_stats[ $ruleId ]['extra'] = 0.0;
					}

					$order_stats[ $ruleId ]['extra'] += $value;
				}
			} elseif ( isset( $singleCoupons[ $couponCode ] ) ) {
				$coupon  = $singleCoupons[ $couponCode ];
				$ruleId = $coupon->getRuleId();

				if ( ! isset( $order_stats[ $ruleId ] ) ) {
					$order_stats[ $ruleId ] = array();
				}

				if ( ! isset( $order_stats[ $ruleId ]['extra'] ) ) {
					$order_stats[ $ruleId ]['extra'] = 0.0;
				}

				$order_stats[ $ruleId ]['extra'] += $amount;
			}
		}
	}

	/**
	 * @param WC_Cart $wc_cart
	 * @param array    $order_stats
	 */
	private function inject_wc_cart_fee_stats( $wc_cart, &$order_stats ) {
		$totalsFacade = new WcTotalsFacade( $this->context, $wc_cart );

		$fees = $totalsFacade->getFees();

		if ( ! $fees ) {
			return;
		}

		foreach ( $fees as $fee ) {
			$ruleId = $fee->getRuleId();

			if ( ! isset( $order_stats[ $ruleId ] ) ) {
				$order_stats[ $ruleId ] = array();
			}

			if ( ! isset( $order_stats[ $ruleId ]['extra'] ) ) {
				$order_stats[ $ruleId ]['extra'] = 0.0;
			}

			$order_stats[ $ruleId ]['extra'] -= $fee->getAmount();
		}
	}

	/**
	 * @param WooCommerce $wc
	 * @param array        $order_stats
	 */
	private function inject_wc_cart_shipping_stats( $wc, &$order_stats ) {
		$shippings = $wc->session->get( 'chosen_shipping_methods' );
		if ( empty( $shippings ) ) {
			return;
		}

		$applied_rules_key    = 'adp_adjustments';

		foreach ( $shippings as $package_id => $shipping_rate_key ) {
			$packages = $wc->shipping()->get_packages();
			if ( isset( $packages[ $package_id ]['rates'][ $shipping_rate_key ] ) ) {
				/** @var WC_Shipping_Rate $sh_rate */
				$sh_rate      = $packages[ $package_id ]['rates'][ $shipping_rate_key ];
				$sh_rate_meta = $sh_rate->get_meta_data();

				$is_free_shipping = isset( $sh_rate_meta['adp_type'] ) && $sh_rate_meta['adp_type'] === "free"; //notice
				$adp_rules        = isset( $sh_rate_meta[ $applied_rules_key ] ) ? $sh_rate_meta[ $applied_rules_key ] : false;

				if ( ! empty( $adp_rules ) && is_array( $adp_rules ) ) {
					foreach ( $adp_rules as $rule ) {
                        /**
                         * @var ShippingAdjustment $rule
                         */
                        $rule_id = $rule->getRuleId();
                        $amount = $rule->getAmount();
						if ( ! isset( $order_stats[ $rule_id ] ) ) {
							$order_stats[ $rule_id ] = array();
						}

						if ( ! isset( $order_stats[ $rule_id ]['shipping'] ) ) {
							$order_stats[ $rule_id ]['shipping'] = 0.0;
						}

						$order_stats[ $rule_id ]['shipping']         += $amount;
						$order_stats[ $rule_id ]['is_free_shipping'] = $is_free_shipping;
					}
				}

			}
		}
	}
}
