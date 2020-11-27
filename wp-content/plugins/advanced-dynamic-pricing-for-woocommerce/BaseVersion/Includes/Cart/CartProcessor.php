<?php

namespace ADP\BaseVersion\Includes\Cart;

use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\Cart\Structures\CartItem;
use ADP\BaseVersion\Includes\Cart\Structures\CouponCartItem;
use ADP\BaseVersion\Includes\Cart\Structures\FreeCartItem;
use ADP\BaseVersion\Includes\Cart\Structures\TaxExemptProcessor;
use ADP\BaseVersion\Includes\CompareStrategy;
use ADP\BaseVersion\Includes\Currency;
use ADP\BaseVersion\Includes\CurrencyController;
use ADP\BaseVersion\Includes\External\Cmp\PhoneOrdersCmp;
use ADP\BaseVersion\Includes\External\Cmp\WcsAttCmp;
use ADP\BaseVersion\Includes\External\Cmp\WcSubscriptionsCmp;
use ADP\BaseVersion\Includes\External\Cmp\WoocsCmp;
use ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\WC\WcNoFilterWorker;
use ADP\BaseVersion\Includes\External\WC\WcTotalsFacade;
use ADP\BaseVersion\Includes\OverrideCentsStrategy;
use ADP\BaseVersion\Includes\Reporter\CartCalculatorListener;
use ADP\Factory;
use ReflectionClass;
use ReflectionException;
use WC_Cart;
use WC_Cart_Totals;
use WC_Product;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartProcessor {
	/**
	 * @var WC_Cart
	 */
	protected $wcCart;

	/**
	 * @var Cart
	 */
	protected $cart;

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var WcNoFilterWorker
	 */
	protected $wcNoFilterWorker;

	/**
	 * @var CartCalculator
	 */
	protected $calc;

	/**
	 * @var CartCouponsProcessor
	 */
	protected $cartCouponsProcessor;

	/**
	 * @var CartFeeProcessor
	 */
	protected $cartFeeProcessor;

	/**
	 * @var CartShippingProcessor
	 */
	protected $shippingProcessor;

	/**
	 * @var TaxExemptProcessor
	 */
	protected $taxExemptProcessor;

	/**
	 * @var WcTotalsFacade
	 */
	protected $cartTotalsWrapper;

	/**
	 * @var CartBuilder
	 */
	protected $cartBuilder;

	/**
	 * @var CartCalculatorListener
	 */
	protected $listener;

	/**
	 * @var PhoneOrdersCmp
	 */
	protected $poCmp;

	/**
	 * @var OverrideCentsStrategy
	 */
	protected $overrideCentsStrategy;

	/**
	 * @var CompareStrategy
	 */
	protected $compareStrategy;

	/**
	 * @var WcSubscriptionsCmp
	 */
	protected $wcSubsCmp;

	/**
	 * @var WcsAttCmp
	 */
	protected $wcsAttCmp;

	/**
	 * CartProcessor constructor.
	 *
	 * @param Context        $context
	 * @param WC_Cart        $wcCart
	 * @param CartCalculator $calc
	 */
	public function __construct( $context, $wcCart, $calc = null ) {
		$this->context          = $context;
		$this->wcCart           = $wcCart;
		$this->wcNoFilterWorker = new WcNoFilterWorker();

		$this->listener = new CartCalculatorListener( $context );

		if ( $calc instanceof CartCalculator ) {
			$this->calc = $calc;
		} else {
			$this->calc = Factory::callStaticMethod( "Cart_CartCalculator", 'make', $context, $this->listener );
			/** @see CartCalculator::make() */
		}

		$this->cartCouponsProcessor  = Factory::get( "Cart_CartCouponsProcessor", $context );
		$this->cartFeeProcessor      = new CartFeeProcessor();
		$this->shippingProcessor     = Factory::get( "Cart_CartShippingProcessor", $context );;
		$this->taxExemptProcessor    = new TaxExemptProcessor( $context );
		$this->cartTotalsWrapper     = new WcTotalsFacade( $this->context, $wcCart );
		$this->cartBuilder           = new CartBuilder( $this->context );
		$this->poCmp                 = new PhoneOrdersCmp( $context );
		$this->overrideCentsStrategy = new OverrideCentsStrategy( $context );
		$this->compareStrategy       = new CompareStrategy( $context );
		$this->wcSubsCmp             = new WcSubscriptionsCmp( $context );
		$this->wcsAttCmp             = new WcsAttCmp( $context );
	}

	public function installActionFirstProcess() {
		$this->cartCouponsProcessor->setFilterToInstallCouponsData();
		$this->cartCouponsProcessor->setFiltersToSupportPercentLimitCoupon();
		$this->cartFeeProcessor->setFilterToCalculateFees();
		$this->shippingProcessor->setFilterToEditPackageRates();
		$this->shippingProcessor->setFilterToEditShippingMethodLabel();

		add_filter( 'woocommerce_update_cart_validation', array( $this, 'filterCheckCartItemExistenceBeforeUpdate' ), 10, 4 );
	}

	/**
	 * The main process function.
	 * WC_Cart -> Cart -> Cart processing -> New Cart -> modifying global WC_Cart
	 *
	 * @param bool $first
	 *
	 * @return Cart
	 */
	public function process( $first = false ) {
		$wcCart           = $this->wcCart;
		$wcNoFilterWorker = $this->wcNoFilterWorker;

		$this->syncCartItemHashes( $wcCart );

		$this->listener->processStarted( $wcCart );
		$this->taxExemptProcessor->maybeRevertTaxExempt( WC()->customer, WC()->session );
		$cart = $this->cartBuilder->create( WC()->customer, WC()->session );
		$this->listener->cartCreated( $cart );

		if ( ! $wcCart || $wcCart->is_empty() ) {
			return $cart;
		}

		// add previously added free items to internal Cart and remove them from WC_Cart
		$this->processFreeItems( $cart, $wcCart );
		$this->eliminateClones( $wcCart );

		$this->poCmp->sanitizeWcCart( $wcCart );

		// fill internal Cart from cloned WC_Cart
		// do not use global WC_Cart because we change prices to get correct initial subtotals
		$clonedWcCart = clone $wcCart;
		$currencySwitcher = $this->context->currencyController;

		if ( $currencySwitcher->isCurrencyChanged() ) {
			foreach ( $clonedWcCart->cart_contents as $cartKey => $wcCartItem ) {
				$facade  = new WcCartItemFacade( $this->context, $wcCartItem );
				$product = $facade->getProduct();

				$product->set_price( $currencySwitcher->getCurrentCurrencyProductPrice( $product ) );
				$salePrice = $currencySwitcher->getCurrentCurrencyProductSalePrice( $product );
				if ( $salePrice !== null ) {
					$product->set_sale_price( $salePrice );
				}
				$product->set_regular_price( $currencySwitcher->getCurrentCurrencyProductRegularPrice( $product ) );

				$price_mode = $this->context->get_option( 'discount_for_onsale' );

				if ( $product->is_on_sale( 'edit' ) ) {
					if ( 'sale_price' === $price_mode || 'discount_sale' === $price_mode ) {
						$price = $product->get_sale_price( 'edit' );
					} else {
						$price = $product->get_regular_price( 'edit' );
					}
				} else {
					$price = $product->get_price( 'edit' );
				}

				$product->set_price( $price );

				$facade->setCurrency( $currencySwitcher->getCurrentCurrency() );
				$clonedWcCart->cart_contents[ $cartKey ] = $facade->getData();
			}
		} else {
			foreach ( $clonedWcCart->cart_contents as $cartKey => $wcCartItem ) {
				$facade               = new WcCartItemFacade( $this->context, $wcCartItem );
				$product              = $facade->getProduct();
				$prodPropsWithFilters = $this->context->get_option( 'initial_price_context' ) === 'view';

				if ( $first ) {
					$facade->setInitialCustomPrice( null );
					if ( $prodPropsWithFilters && ! $this->compareStrategy->floatsAreEqual( $product->get_price( 'edit' ),
							$product->get_price( 'view' ) ) ) {
						$facade->setInitialCustomPrice( floatval( $product->get_price( 'view' ) ) );
					} elseif ( ! isset( $product->get_changes()['price'] ) ) {
						self::setProductPriceDependsOnPriceMode( $product );
					} else {
						$facade->setInitialCustomPrice( $product->get_price( 'edit' ) );
					}
				} else {
					if ( $prodPropsWithFilters && ! $this->compareStrategy->floatsAreEqual( $product->get_price( 'edit' ),
							$product->get_price( 'view' ) ) ) {
						self::setProductPriceDependsOnPriceMode( $product );
						$facade->setInitialCustomPrice( floatval( $product->get_price( 'view' ) ) );
					} elseif ( $this->poCmp->isCartItemCostUpdateManually( $facade ) ) {
						$product->set_price( $this->poCmp->getCartItemCustomPrice( $facade ) );
						$facade->addAttribute( $facade::ATTRIBUTE_IMMUTABLE );
					} elseif ( $facade->getInitialCustomPrice() !== null ) {
						$product->set_price( $facade->getInitialCustomPrice() );
					} /**
					 * Catch 3rd party price changes
					 * e.g. during action 'before calculate totals'
					 */ elseif ( $facade->getNewPrice() !== null && ! $this->compareStrategy->floatsAreEqual( $facade->getNewPrice(),
							$product->get_price( 'edit' ) ) ) {
						$facade->setInitialCustomPrice( $product->get_price( 'edit' ) );
						$product->set_price( $product->get_price( 'edit' ) );
					} else {
						self::setProductPriceDependsOnPriceMode( $product );
					}

				}

				$clonedWcCart->cart_contents[ $cartKey ] = $facade->getData();
			}
		}

		$flags = array();
		if ( $this->wcSubsCmp->isActive() && $this->wcsAttCmp->isActive() ) {
			$flags[] = $wcNoFilterWorker::FLAG_ALLOW_PRICE_HOOKS;
		}

		if ( $this->context->isDisableShippingCalculationDuringProcess() ) {
			$flags[] = $wcNoFilterWorker::FLAG_DISALLOW_SHIPPING_CALCULATION;
		}

		$wcNoFilterWorker->calculateTotals( $clonedWcCart, ...$flags );
		$this->cartBuilder->populateCart( $cart, $clonedWcCart );
		$this->listener->cartCompleted( $cart );
		// fill internal Cart from cloned WC_Cart ended

		// Delete all 'pricing' data from the cart
		$this->sanitizeWcCart( $wcCart );
		$this->cartCouponsProcessor->sanitize( $wcCart );
		$this->cartFeeProcessor->sanitize( $wcCart );
		$this->shippingProcessor->sanitize( $wcCart );

		/**
		 * Add flag 'FLAG_ALLOW_PRICE_HOOKS'
		 * because some plugins set price using 'get_price' hooks instead of modify WC_Product property.
		 */
		$flags = array( $wcNoFilterWorker::FLAG_ALLOW_PRICE_HOOKS );
		if ( $this->context->isDisableShippingCalculationDuringProcess() ) {
			$flags[] = $wcNoFilterWorker::FLAG_DISALLOW_SHIPPING_CALCULATION;
		}
		$wcNoFilterWorker->calculateTotals( $wcCart, ...$flags );
		// Delete all 'pricing' data from the cart ended

		$result = $this->calc->processCart( $cart );

		if ( $result ) {
			// todo replace $this
			do_action( 'wdp_before_apply_to_wc_cart', $this, $wcCart );

			//TODO Put to down items that are not filtered?

			// process free items
			$freeProducts = apply_filters( 'wdp_internal_free_products_before_apply', $cart->getFreeItems(), $this );
			/** @var $freeProducts FreeCartItem[] */

			$freeProductsMapping = array();
			foreach ( $freeProducts as $index => $freeItem ) {
				$product = $freeItem->getProduct();

				$product_id = $product->get_id();
				if ( $product instanceof WC_Product_Variation ) {
					/** @var WC_Product_Variation $product */
					$variationId = $product_id;
					$product_id  = $product->get_parent_id();
					$variation   = $product->get_variation_attributes();
				} else {
					$variationId = 0;
					$variation   = array();
				}

				if ( $cartItemKey = $wcNoFilterWorker->addToCart( $clonedWcCart, $product_id, $freeItem->qty,
					$variationId, $variation ) ) {

					if ( ! isset( $freeProductsMapping[ $cartItemKey ] ) ) {
						$freeProductsMapping[ $cartItemKey ] = array();
					}

					$freeProductsMapping[ $cartItemKey ][] = $freeItem;
				}
			}

			$flags = array( $wcNoFilterWorker::FLAG_ALLOW_PRICE_HOOKS );
			if ( $this->context->isDisableShippingCalculationDuringProcess() ) {
				$flags[] = $wcNoFilterWorker::FLAG_DISALLOW_SHIPPING_CALCULATION;
			}

			// Here we have an initial cart with full-price free products
			// Save the totals of the initial cart to show the difference
			// Use the flag 'FLAG_ALLOW_PRICE_HOOKS' to get filtered product prices
			if ( $currencySwitcher->isCurrencyChanged() ) {
				$wcNoFilterWorker->calculateTotals( $clonedWcCart );
			} else {
				$flags[] = $wcNoFilterWorker::FLAG_ALLOW_PRICE_HOOKS;
				$wcNoFilterWorker->calculateTotals( $clonedWcCart, ...$flags );
			}
			$initialTotals = $clonedWcCart->get_totals();

			foreach ( $freeProductsMapping as $loopCartItemKey => $freeItems ) {
				foreach ( $freeItems as $freeItem ) {
					$facade = new WcCartItemFacade( $this->context, $clonedWcCart->cart_contents[ $loopCartItemKey ] );

					$rules = array( $freeItem->getRuleId() => array( $freeItem->getInitialPrice() ) );

					$cartItemQty = $facade->getQty();
					$facade->setQty( $freeItem->getQty() );

					$facade->setOriginalPrice( $facade->getProduct()->get_price( 'edit' ) );

					$facade->addAttribute( $facade::ATTRIBUTE_FREE );

					/**
					 * @var CouponCartItem|null $coupon
					 *
					 * We must keep the reference, because the affected items are not yet known
					 */
					$coupon = null;

					if ( $freeItem->isReplaceWithCoupon() ) {
						// no need to change the price, it is already full
						$facade->setDiscounts( array() );

						if ( $this->context->priceSettings->isIncludeTax() ) {
							$couponAmount = $facade->getSubtotal() + $facade->getSubtotalTax();
						} else {
							$couponAmount = $facade->getSubtotal();
						}
						$couponAmount = ( $couponAmount / $cartItemQty ) * $freeItem->getQty();

						$coupon = new CouponCartItem(
							$this->context,
							CouponCartItem::TYPE_FREE_ITEM,
							$freeItem->getReplaceCouponCode(),
							$couponAmount / $freeItem->getQty(),
							$freeItem->getRuleId(),
							null
						);

						$cart->addCoupon( $coupon );

						$facade->setReplaceWithCoupon( true );
						$facade->setReplaceCouponCode( $freeItem->getReplaceCouponCode() );
					} elseif ( $this->context->get_option( 'free_products_as_coupon',
							false ) && $this->context->get_option( 'free_products_coupon_name', false ) ) {
						$facade->setDiscounts( array() );

						if ( $this->context->priceSettings->isIncludeTax() ) {
							$couponAmount = $facade->getSubtotal() + $facade->getSubtotalTax();
						} else {
							$couponAmount = $facade->getSubtotal();
						}
						$couponAmount = ( $couponAmount / $cartItemQty ) * $freeItem->getQty();

						$coupon = new CouponCartItem(
							$this->context,
							CouponCartItem::TYPE_FREE_ITEM,
							$this->context->get_option( 'free_products_coupon_name' ),
							$couponAmount / $freeItem->getQty(),
							$freeItem->getRuleId(),
							null
						);

						$cart->addCoupon( $coupon );

						$facade->setReplaceWithCoupon( true );
						$facade->setReplaceCouponCode( $this->context->get_option( 'free_products_coupon_name' ) );
					} else {
						$facade->setNewPrice( 0 );
						$facade->setDiscounts( $rules );
					}

					$facade->setOriginalPriceWithoutTax( $facade->getSubtotal() / $cartItemQty );
					$facade->setOriginalPriceTax( $facade->getSubtotalTax() / $cartItemQty );
					$facade->setHistory( $rules );

					$cartItemKey = $wcNoFilterWorker->addToCart( $wcCart, $facade->getProductId(), $facade->getQty(),
						$facade->getVariationId(), $facade->getVariation(), $facade->getCartItemData() );

					$newFacade = new WcCartItemFacade( $this->context, $wcCart->cart_contents[ $cartItemKey ] );
					$newFacade->setNewPrice( $facade->getProduct()->get_price('edit') );
					$wcCart->cart_contents[ $cartItemKey ] = $newFacade->getData();

					if ( isset( $coupon ) ) {
						$coupon->setAffectedCartItem( $newFacade );
					}
				}
			}

			$flags = array();
			if ( $this->context->isDisableShippingCalculationDuringProcess() ) {
				$flags[] = $wcNoFilterWorker::FLAG_DISALLOW_SHIPPING_CALCULATION;
			}

			$wcNoFilterWorker->calculateTotals( $wcCart, ...$flags );
			// process free items ended

			$this->addCommonItems( $cart, $wcCart );

			// handle option 'disable_external_coupons'
			$this->maybeRemoveOriginCoupons( $cart, $wcCart );

			$this->applyTotals( $cart, $wcCart );

			$this->taxExemptProcessor->installTaxExemptFromNewCart( $cart, WC()->customer, WC()->session );

			$flags = array();
			if (
				$this->wcSubsCmp->isActive() && $this->wcsAttCmp->isActive()
			) {
				$flags[] = $wcNoFilterWorker::FLAG_ALLOW_PRICE_HOOKS;
			}
			$wcNoFilterWorker->calculateTotals( $wcCart, ...$flags );
			$wcCart->set_session();

			$this->cartCouponsProcessor->updateTotals( $wcCart );
			$this->cartFeeProcessor->updateTotals( $wcCart );
			$this->shippingProcessor->updateTotals( $wcCart );
			$this->cartTotalsWrapper->insertInitialTotals( $initialTotals );

			if ( $this->context->get_option( 'show_message_after_add_free_product' ) ) {
				$this->notifyAboutAddedFreeItems( $cart );
			}

			do_action( 'wdp_after_apply_to_wc_cart', $this, $cart, $wcCart );
			$this->poCmp->forceToSkipFreeCartItems( $wcCart );
		}

		$this->listener->processFinished( $wcCart );

		return $cart;
	}

	/**
	 * Merge cloned items into the 'locomotive' item. Destroy them after.
	 * If the 'locomotive' item has been removed, promote the first clone.
	 *
	 * @param WC_Cart $wcCart
	 */
	protected function eliminateClones( $wcCart ) {
		foreach ( $wcCart->cart_contents as $cartKey => $wcCartItem ) {
			$wrapper = new WcCartItemFacade( $this->context, $wcCartItem );

			if ( $wrapper->getOriginalKey() ) {
				if ( isset( $wcCart->cart_contents[ $wrapper->getOriginalKey() ] ) ) {
					$originalWrapper = new WcCartItemFacade( $this->context,
						$wcCart->cart_contents[ $wrapper->getOriginalKey() ] );
					$originalWrapper->setQty( $originalWrapper->getQty() + $wrapper->getQty() );
					$wcCart->cart_contents[ $originalWrapper->getKey() ] = $originalWrapper->getData();
				} else {
					/** The 'locomotive' is not in cart. Promote the clone! */
					$wrapper->setKey( $wrapper->getOriginalKey() );
					$wrapper->setOriginalKey( null );
					$wcCart->cart_contents[ $wrapper->getKey() ] = $wrapper->getData();
				}

				/** do not forget to remove clone */
				unset( $wcCart->cart_contents[ $cartKey ] );
			}
		}
	}

	/**
	 * @param $cart Cart
	 * @param $wcCart WC_Cart
	 */
	protected function processFreeItems( $cart, $wcCart ) {
		$pos = 0;
		foreach ( $wcCart->cart_contents as $cartKey => $wcCartItem ) {
			$wrapper = new WcCartItemFacade( $this->context, $wcCartItem );
			if ( $wrapper->isFreeItem() ) {
				$item = $wrapper->createItem();
				$item->setPos( $pos );
				$cart->addToCart( $item );
				unset( $wcCart->cart_contents[ $cartKey ] );
			}

			$pos ++;
		}
	}

	/**
	 * @param WC_Cart $wcCart
	 */
	public function sanitizeWcCart( $wcCart ) {
		foreach ( $wcCart->cart_contents as $cartKey => $wcCartItem ) {
			$wrapper = new WcCartItemFacade( $this->context, $wcCartItem );
			$wrapper->sanitize();
			$wcCart->cart_contents[ $cartKey ] = $wrapper->getData();
		}
	}

	/**
	 * @param Cart    $cart
	 * @param WC_Cart $wcCart
	 *
	 */
	protected function addCommonItems( $cart, $wcCart ) {
		$cartContext = $cart->get_context();

		// todo replace $this
		$items = apply_filters( 'wdp_internal_cart_items_before_apply', $cart->getItems(), $this );
		/** @var $items CartItem[] */

		$processedItemKeys = array();

		foreach ( $items as $item ) {
			/** have to clone! because of split items are having the same WC_Product object */
			$facade             = clone $item->getWcItem();

			$productPrice = $item->getOriginalPrice();
			foreach ( $item->getDiscounts() as $ruleId => $amounts ) {
				$productPrice -= array_sum( $amounts );
			}
			if ( $cartContext->get_option( 'is_calculate_based_on_wc_precision' ) ) {
				$productPrice = round( $productPrice, wc_get_price_decimals() );
			}

			$facade->setOriginalPrice( $facade->getProduct()->get_price( 'edit' ) );
			$productPrice = $this->overrideCentsStrategy->maybeOverrideCents( $productPrice, $item->getWcItem()->getProduct() );

			$facade->setNewPrice( $productPrice );
			$facade->setHistory( $item->getHistory() );
			$facade->setDiscounts( $item->getDiscounts() );

			$facade->setOriginalPriceWithoutTax( $facade->getSubtotal() / $facade->getQty() );
			$facade->setOriginalPriceTax( $facade->getSubtotalTax() / $facade->getQty() );
			$facade->setQty( $item->getQty() );

			if ( in_array( $facade->getKey(), $processedItemKeys ) ) {
				$originalCartItemKey = $facade->getKey();
				$facade->setOriginalKey( $originalCartItemKey );

				$cart_item_key = $wcCart->generate_cart_id( $facade->getProductId(), $facade->getVariationId(),
					$facade->getVariation(), $facade->getCartItemData() );

				if ( isset( $wcCart->cart_contents[ $cart_item_key ] ) ) {
					$alreadyProcessedItemFacade = new WcCartItemFacade( $this->context, $wcCart->cart_contents[ $cart_item_key ] );
					$alreadyProcessedItemFacade->setQty( $alreadyProcessedItemFacade->getQty() + $facade->getQty() );
					$wcCart->cart_contents[ $cart_item_key ] = $alreadyProcessedItemFacade->getData();
					continue;
				}

				$facade->setKey( $cart_item_key );
			}

			$wcCart->cart_contents[ $facade->getKey() ] = $facade->getData();
			$processedItemKeys[] = $facade->getKey();
		}
	}

	/**
	 * @param Cart    $cart
	 * @param WC_Cart $wcCart
	 */
	public function applyTotals( $cart, $wcCart ) {
		$this->syncOriginCoupons( $cart, $wcCart );

		$this->cartCouponsProcessor->refreshCoupons( $cart );
		$this->cartCouponsProcessor->applyCoupons( $wcCart );

		$this->cartFeeProcessor->refreshFees( $cart );

		if ( ! $this->context->isDisableShippingCalculationDuringProcess() ) {
			$this->shippingProcessor->purgeCalculatedPackagesInSession();
		}
		$this->shippingProcessor->refresh( $cart );
	}

	/**
	 * @param Cart    $cart
	 * @param WC_Cart $wcCart
	 */
	protected function syncOriginCoupons( &$cart, &$wcCart ) {
		$wcCart->applied_coupons = $cart->getOriginCoupons();
	}

	/**
	 * @param Cart    $cart
	 * @param WC_Cart $wcCart
	 */
	protected function maybeRemoveOriginCoupons( $cart, $wcCart ) {
		if ( $this->context->get_option( 'disable_external_coupons' ) === 'if_any_rule_applied' ) {
			$cart->removeAllOriginCoupon();
			$this->suppressCouponAppliedNotice();
		} elseif ( $this->context->get_option( 'disable_external_coupons' ) === 'if_any_of_cart_items_updated' ) {
			$is_price_changed = false;

			foreach ( $wcCart->cart_contents as $wcCartItem ) {
				$wrapper = new WcCartItemFacade( $this->context, $wcCartItem );
				foreach ( $wrapper->getDiscounts() as $ruleId => $amounts ) {
					if ( array_sum( $amounts ) > 0 ) {
						$is_price_changed = true;
						break;
					}
				}
			}

			$is_price_changed = (bool) apply_filters( 'wdp_is_disable_external_coupons_if_items_updated',
				$is_price_changed, $this, $wcCart );

			if ( $is_price_changed ) {
				$cart->removeAllOriginCoupon();
			}
			$this->suppressCouponAppliedNotice();
		}
	}

	protected function suppressCouponAppliedNotice() {
		$new_notices = array();
		foreach ( wc_get_notices() as $notice_type => $notices ) {
			if ( ! isset( $new_notices[ $notice_type ] ) ) {
				$new_notices[ $notice_type ] = array();
			}

			foreach ( $notices as $notice ) {
				if ( isset( $notice['notice'] ) && __( 'Coupon code applied successfully.',
						'woocommerce' ) === $notice['notice'] ) {

					if ( ! isset( $new_notices['error'] ) ) {
						$new_notices['error'] = array();
					}

					$new_notices['error'][] = array(
						'notice' => __( 'Sorry, coupons are disabled for this products.',
							'advanced-dynamic-pricing-for-woocommerce' ),
						'data'   => array(),
					);

					continue;
				} else {
					$new_notices[ $notice_type ][] = $notice;
				}
			}
		}
		wc_set_notices( $new_notices );
	}

	/**
	 * @param Cart $cart
	 */
	public function notifyAboutAddedFreeItems( $cart ) {
		$freeItems = $cart->getFreeItems();
		foreach ( $freeItems as $freeItem ) {
			$freeItemTmp = clone $freeItem;
			$giftedQty   = $freeItemTmp->qty - $freeItem->getQtyAlreadyInWcCart();
			if ( $giftedQty > 0 ) {
				$this->addNoticeAddedFreeProduct( $freeItem->getProduct(), $giftedQty );
			} elseif ( $freeItemTmp->qty > 0 && $giftedQty < 0 ) {
				$this->addNoticeRemovedFreeProduct( $freeItem->getProduct(), - $giftedQty );
			}
		}
	}

	protected function addNoticeAddedFreeProduct( $product, $qty ) {
		$template  = $this->context->get_option( 'message_template_after_add_free_product' );
		$arguments = array(
			'{{qty}}'          => $qty,
			'{{product_name}}' => $product->get_name(),
		);
		$message   = str_replace( array_keys( $arguments ), array_values( $arguments ), $template );
		$type      = 'success';
		$data      = array( 'adp' => true );

		wc_add_notice( $message, $type, $data );
	}

	protected function addNoticeRemovedFreeProduct( $product, $qty ) {
		$template  = __( "Removed {{qty}} free {{product_name}}", 'advanced-dynamic-pricing-for-woocommerce' ); // todo replace with option?
		$arguments = array(
			'{{qty}}'          => $qty,
			'{{product_name}}' => $product->get_name(),
		);
		$message   = str_replace( array_keys( $arguments ), array_values( $arguments ), $template );
		$type      = 'success';
		$data      = array( 'adp' => true );

		wc_add_notice( $message, $type, $data );
	}

	/**
	 * @return CartCalculatorListener
	 */
	public function getListener() {
		return $this->listener;
	}

	/**
	 * @return WcNoFilterWorker
	 */
	public function getWcNoFilterWorker() {
		return $this->wcNoFilterWorker;
	}

	/**
	 * You can delete the item during \WC_Cart::set_quantity() if qty is set to 0.
	 * This action triggers \WC_Cart::calculate_totals() and calls our cart processor.
	 * After $this->eliminateClones() the hashes of the items may change and wc-form-handler will throw the error.
	 * e.g. you are removing the 'locomotive' item and the first clone becomes 'loco', so the hash of the clone item is replaced.
	 *
	 * To prevent this, we double check for existence.
	 *
	 * @param bool      $passed_validation
	 * @param string    $cart_item_key
	 * @param array     $values
	 * @param int|float $quantity
	 *
	 * @return bool
	 */
	public function filterCheckCartItemExistenceBeforeUpdate( $passed_validation, $cart_item_key, $values, $quantity ) {
		if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			$passed_validation = false;
		}

		return $passed_validation;
	}

	/**
	 * @param WC_Product $product
	 */
	protected function setProductPriceDependsOnPriceMode( $product ) {
		$price_mode = $this->context->get_option( 'discount_for_onsale' );

		try {
			$reflection = new ReflectionClass( $product );
			$property   = $reflection->getProperty( 'changes' );
			$property->setAccessible( true );
			$changes = $property->getValue( $product );
			unset( $changes['price'] );
			$property->setValue( $product, $changes );
		} catch ( ReflectionException $exception ) {
			$property = null;
		}

		if ( $product->is_on_sale( 'edit' ) ) {
			if ( 'sale_price' === $price_mode || 'discount_sale' === $price_mode ) {
				$price = $product->get_sale_price( 'edit' );
			} else {
				$price = $product->get_regular_price( 'edit' );
			}
		} else {
			$price = $product->get_price( 'edit' );
		}

		$product->set_price( $price );
	}

	/**
	 * In case if index of the $wcCart->cart_contents element is not equal value by index 'key' of element
	 *
	 * Scheme of $wcCart->cart_contents
	 *
	 * [
	 *   ['example_hash'] =>
	 *      [
	 *          'key' => 'example_hash_in_the_element'
	 *          ...
	 *      ]
	 * ]
	 *
	 * So, sometimes 'example_hash' does not equal 'example_hash_in_the_element', but it should!
	 * This method solves the problem.
	 *
	 * @param \WC_Cart $wcCart
	 */
	protected function syncCartItemHashes( $wcCart ) {
		if ( ! $wcCart || $wcCart->is_empty() ) {
			return;
		}

		foreach ( $wcCart->cart_contents as $cartItemHash => $cartItem ) {
			if ( isset( $this->wcCart->cart_contents[ $cartItemHash ][ WcCartItemFacade::KEY_KEY ] ) ) {
				$this->wcCart->cart_contents[ $cartItemHash ][ WcCartItemFacade::KEY_KEY ] = $cartItemHash;
			}
		}
	}
}
