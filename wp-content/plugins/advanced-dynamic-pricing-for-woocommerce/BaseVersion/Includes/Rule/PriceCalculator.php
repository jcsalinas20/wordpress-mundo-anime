<?php

namespace ADP\BaseVersion\Includes\Rule;

use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\Cart\Structures\CartItem;
use ADP\BaseVersion\Includes\Cart\Structures\CartItemsCollection;
use ADP\BaseVersion\Includes\Cart\Structures\CartSet;
use ADP\BaseVersion\Includes\Cart\Structures\CouponCart;
use ADP\BaseVersion\Includes\Cart\Structures\CouponCartItem;
use ADP\BaseVersion\Includes\Cart\Structures\Fee;
use ADP\BaseVersion\Includes\Rule\Structures\ItemDiscount;
use ADP\BaseVersion\Includes\Rule\Structures\Discount;
use ADP\BaseVersion\Includes\Rule\Interfaces\Rule;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentTotal;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentSplit;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\PackageRangeAdjustments;
use ADP\BaseVersion\Includes\Rule\Structures\RoleDiscount;
use ADP\BaseVersion\Includes\Rule\Structures\SetDiscount;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule\ProductsAdjustment;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule\ProductsRangeAdjustments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PriceCalculator {
	/**
	 * @var Rule
	 */
	protected $rule;

	/**
	 * @var Discount
	 */
	protected $discount;

	/**
	 * @var float
	 */
	protected $discountTotalLimit;

	/**
	 * @param Rule       $rule
	 * @param Discount   $discount
	 * @param float|null $discountTotalLimit
	 */
	public function __construct( $rule, $discount, $discountTotalLimit = null ) {
		$this->rule               = $rule;
		$this->discount           = $discount;
		$this->discountTotalLimit = $discountTotalLimit;
	}

	/**
	 * @param CartItemsCollection $collection
	 */
	public function applyToAllItemsInCollection( &$collection ) {
		foreach ( $collection->get_items() as &$item ) {
			$this->applyToItem( $item );
		}
	}

	/**
	 * @param CartItem $item
	 */
	public function applyToItem( &$item ) {
		$price       = $item->getPrice();
		$adjustments = $item->trdPartyPriceAdj;

		$price = $this->calculateSinglePrice( $price );

		$discount = $this->discount;
		if ( $discount::TYPE_FIXED_VALUE === $this->discount->getType() ) {
			$price += $adjustments;
		}

		$item->setPrice( $this->rule->getId(), $price );
	}

	public function calculatePrice( $item, $cart ) {
		$globalContext = $cart->get_context()->getGlobalContext();
		$discount      = $this->discount;

		if ( $discount instanceof SetDiscount ) {
			return null;
		}

		if ( $globalContext->get_option( 'apply_discount_to_original_price' ) && Discount::TYPE_PERCENTAGE === $discount->getType() ) {
			$price   = $item->getOriginalPrice();
		} else {
			$price = $item->getPrice();
		}

		$newPrice = $this->calculateSinglePrice( $price );
		if ( $discount::TYPE_FIXED_VALUE === $discount->getType() ) {
			$newPrice += $item->trdPartyPriceAdj;
		}

		return $newPrice;
	}

	/**
	 * @param CartItem $item
	 * @param Cart $cart
	 * @param ProductsAdjustment|ProductsRangeAdjustments|ProductsAdjustmentTotal|ProductsAdjustmentSplit|PackageRangeAdjustments|RoleDiscount $handler
	 */
	public function applyItemDiscount( &$item, &$cart, $handler ) {
		$globalContext = $cart->get_context()->getGlobalContext();
		$discount      = $this->discount;

		if ( $discount instanceof SetDiscount ) {
			return;
		}

		$flags = array();

		if ( $globalContext->get_option( 'apply_discount_to_original_price' ) && Discount::TYPE_PERCENTAGE === $discount->getType() ) {
			$price   = $item->getOriginalPrice();
			$flags[] = CartItem::FLAG_DISCOUNT_ORIGINAL;
		} else {
			$price = $item->getPrice();
		}

		$newPrice = $this->calculateSinglePrice( $price );
		if ( $discount::TYPE_FIXED_VALUE === $discount->getType() ) {
			$newPrice += $item->trdPartyPriceAdj;
		}
		$amount = ( $price - $newPrice ) * $item->getQty();

		if ( $handler->isReplaceWithCartAdjustment() ) {
			$flags[]        = CartItem::FLAG_IGNORE;
			$adjustmentCode = $handler->getReplaceCartAdjustmentCode();

			if ( $amount > 0 ) {
				$cart->addCoupon(
					new CouponCartItem(
						$globalContext,
						CouponCartItem::TYPE_ITEM_DISCOUNT,
						$adjustmentCode,
						$amount / $item->getQty(),
						$this->rule->getId(),
						$item->getWcItem()
					)
				);
			} elseif ( $amount < 0 ) {
				$cart->addFee( new Fee( $globalContext, Fee::TYPE_ITEM_OVERPRICE, $adjustmentCode, ( - 1 ) * $amount, null, $this->rule->getId() ) );
			}
		} elseif ( $globalContext->get_option( 'item_adjustments_as_coupon', false ) && $globalContext->get_option( 'item_adjustments_coupon_name', false ) ) {
			$flags[]        = CartItem::FLAG_IGNORE;
			$adjustmentCode = $globalContext->get_option( 'item_adjustments_coupon_name' );

			if ( $amount > 0 ) {
				$cart->addCoupon(
					new CouponCartItem(
						$globalContext,
						CouponCartItem::TYPE_ITEM_DISCOUNT,
						$adjustmentCode,
						$amount / $item->getQty(),
						$this->rule->getId(),
						$item->getWcItem()
					)
				);
			} elseif ( $amount < 0 ) {
				$cart->addFee( new Fee( $globalContext, Fee::TYPE_ITEM_OVERPRICE, $adjustmentCode, ( - 1 ) * $amount,
					null, $this->rule->getId() ) );
			}
		}

		$item->setPrice( $this->rule->getId(), $newPrice, $flags );

		if ( $handler instanceof ProductsAdjustment ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_SINGLE_ITEM_SIMPLE, $newPrice );
		} elseif ( $handler instanceof ProductsRangeAdjustments ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_SINGLE_ITEM_RANGE, $newPrice );
		} elseif ( $handler instanceof ProductsAdjustmentTotal ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_SIMPLE, $newPrice );
		} elseif ( $handler instanceof ProductsAdjustmentSplit ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_SPLIT, $newPrice );
		} elseif ( $handler instanceof PackageRangeAdjustments ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_RANGE, $newPrice );
		} elseif ( $handler instanceof RoleDiscount ) {
			$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_ROLE, $newPrice );
		} else {
			return;
		}

		$discount->setRuleId( $this->rule->getId() );
		foreach ( $flags as $flag ) {
			$discount->addFlag( $flag );
		}

		// todo add range

		$item->setPriceNew( $discount );
	}

	/**
	 * @param $price float
	 *
	 * @return float
	 */
	public function calculateSinglePrice( $price ) {
		$old_price = floatval( $price );

		$operationType  = $this->discount->getType();
		$operationValue = $this->discount->getValue();

		if ( Discount::TYPE_FREE === $operationType ) {
			$new_price = $this->makeFree();
		} elseif ( Discount::TYPE_AMOUNT === $operationType ) {
			if ( $operationValue > 0 ) {
				$new_price = $this->makeDiscountAmount( $price, $operationValue );
			} else {
				$new_price = $this->makeOverpriceAmount( $price, (- 1) * $operationValue );
			}
		} elseif ( Discount::TYPE_PERCENTAGE === $operationType ) {
			$new_price = $this->makeDiscountPercentage( $old_price, $operationValue );
		} elseif ( Discount::TYPE_FIXED_VALUE === $operationType ) {
			$new_price = $this->makePriceFixed( $old_price, $operationValue );
		} else {
			$new_price = $old_price;
		}

		return (float) $new_price;
	}

	/**
	 * @param $list_of_items CartItem[]|CartSet|CartItemsCollection
	 *
	 * @return float|int
	 */
	private function calculateAdjustmentsLeft( $list_of_items ) {
		$discountType = $this->discount->getType();

		$items = array();
		if ( is_array( $list_of_items ) ) {
			foreach ( $list_of_items as $item ) {
				if ( $item instanceof CartItem ) {
					$items[] = $item;
				}
			}
		} elseif ( $list_of_items instanceof CartSet || $list_of_items instanceof CartItemsCollection ) {
			$items = $list_of_items->get_items();
		}

		$price_total = 0.0;
		foreach ( $items as $item ) {
			$price_total += $item->getTotalPrice();
		}

		$third_party_adjustments = 0.0;
		foreach ( $items as $item ) {
			$third_party_adjustments += $item->trdPartyPriceAdj;
		}

		$adjustments_left = 0.0;
		if ( Discount::TYPE_PERCENTAGE === $discountType ) {
			foreach ( $items as $item ) {
				/**
				 * @var $item CartItem
				 */
				if ( $item->hasAttr( $item::ATTR_READONLY_PRICE ) ) {
					continue;
				}
				$new_price        = $this->makeDiscountPercentage( $item->getTotalPrice(), $this->discount->getValue() );
				$adjustments_left += $item->getTotalPrice() - $new_price;
			}
		} elseif ( Discount::TYPE_FIXED_VALUE === $discountType || Discount::TYPE_AMOUNT === $discountType ) {
			if ( ! empty( $price_total ) ) {
				if ( Discount::TYPE_FIXED_VALUE === $discountType ) {
					$adjustments_left = $price_total - $this->discount->getValue() - $third_party_adjustments;
				} else {
					$adjustments_left = $this->discount->getValue();
				}
			}
		}

		return $adjustments_left;
	}

	private function checkAdjustmentTotal( $adjustment_total ) {
		// check only for discount
		if ( $this->discountTotalLimit === null || $adjustment_total < 0 ) {
			return $adjustment_total;
		}

		return $adjustment_total > $this->discountTotalLimit ? $this->discountTotalLimit : $adjustment_total;
	}

	/**
	 * @param CartSet $set
	 * @param Cart $cart
	 * @param ProductsAdjustment|ProductsRangeAdjustments|ProductsAdjustmentTotal|ProductsAdjustmentSplit|PackageRangeAdjustments|RoleDiscount $handler
	 *
	 * @return CartSet
	 */
	public function calculatePriceForSet( $set, $cart, $handler ) {
		$globalContext = $cart->get_context()->getGlobalContext();

		$price_total = 0;
		foreach ( $set->get_items() as $item ) {
			/**
			 * @var $item CartItem
			 */
			if ( ! $item->hasAttr( $item::ATTR_READONLY_PRICE ) ) {
				$price_total += $item->getTotalPrice();
			}
		}

		$adjustments_left = $this->checkAdjustmentTotal( $this->calculateAdjustmentsLeft( $set->get_items() ) );
//		$adjustments_left = $this->calculateAdjustmentsLeft( $set->get_items() );

		$overprice        = $adjustments_left < 0;
		$adjustments_left = $overprice ? - $adjustments_left : $adjustments_left;
		$diff             = 0.0;
		if ( $adjustments_left > 0 && $price_total > 0 ) {
			$diff = $adjustments_left / $price_total;
		}

		foreach ( $set->get_positions() as $position ) {
			foreach ( $set->get_items_by_position( $position ) as $item ) {
				/**
				 * @var $item CartItem
				 */

				if ( $item->hasAttr( $item::ATTR_READONLY_PRICE ) ) {
					continue;
				}

				$price             = $item->getPrice();
				$adjustment_amount = min( $price * $diff, $adjustments_left );
				if ( $overprice ) {
					$new_price = $this->makeOverpriceAmount( $price, $adjustment_amount );
				} else {
					$new_price = $this->makeDiscountAmount( $price, $adjustment_amount );
				}

				$flags = array();
				$amount = ( $price - $new_price ) * $item->getQty();

				if ( $handler->isReplaceWithCartAdjustment() ) {
					$flags[]        = CartItem::FLAG_IGNORE;
					$adjustmentCode = $handler->getReplaceCartAdjustmentCode();

					if ( $amount > 0 ) {
						$cart->addCoupon(
							new CouponCartItem(
								$globalContext,
								CouponCartItem::TYPE_ITEM_DISCOUNT,
								$adjustmentCode,
								$amount / $item->getQty(),
								$this->rule->getId(),
								$item->getWcItem()
							)
						);
					} elseif ( $amount < 0 ) {
						$cart->addFee( new Fee( $globalContext, Fee::TYPE_ITEM_OVERPRICE, $adjustmentCode, ( - 1 ) * $amount, null, $this->rule->getId() ) );
					}
				} elseif ( $globalContext->get_option( 'item_adjustments_as_coupon', false ) && $globalContext->get_option( 'item_adjustments_coupon_name', false ) ) {
					$flags[]        = CartItem::FLAG_IGNORE;
					$adjustmentCode = $globalContext->get_option( 'item_adjustments_coupon_name' );

					if ( $amount > 0 ) {
						$cart->addCoupon(
							new CouponCartItem(
								$globalContext,
								CouponCartItem::TYPE_ITEM_DISCOUNT,
								$adjustmentCode,
								$amount / $item->getQty(),
								$this->rule->getId(),
								$item->getWcItem()
							)
						);
					} elseif ( $amount < 0 ) {
						$cart->addFee( new Fee( $globalContext, Fee::TYPE_ITEM_OVERPRICE, $adjustmentCode,
							( - 1 ) * $amount, null, $this->rule->getId() ) );
					}
				}

				$item->setPrice( $this->rule->getId(), $new_price, $flags );

				if ( $handler instanceof ProductsAdjustment ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_SINGLE_ITEM_SIMPLE, $new_price );
				} elseif ( $handler instanceof ProductsRangeAdjustments ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_SINGLE_ITEM_RANGE, $new_price );
				} elseif ( $handler instanceof ProductsAdjustmentTotal ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_SIMPLE, $new_price );
				} elseif ( $handler instanceof ProductsAdjustmentSplit ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_SPLIT, $new_price );
				} elseif ( $handler instanceof PackageRangeAdjustments ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_PACKAGE_RANGE, $new_price );
				} elseif ( $handler instanceof RoleDiscount ) {
					$discount = new ItemDiscount( $globalContext, ItemDiscount::SOURCE_ROLE, $new_price );
				} else {
					continue;
				}

				$discount->setRuleId( $this->rule->getId() );
				foreach ( $flags as $flag ) {
					$discount->addFlag( $flag );
				}

				$item->setPriceNew( $discount );

				$adjustments_left -= $adjustment_amount;
				if ( $adjustments_left <= 0 ) {
					break;
				}
			}
		}

		return $set;
	}

	/**
	 * @param float $price
	 * @param float $percentage
	 *
	 * @return float
	 */
	protected function makeDiscountPercentage( $price, $percentage ) {
		if ( $percentage < 0 ) {
			return $this->checkOverprice( $price, (float) $price * ( 1 - (float) $percentage / 100 ) );
		}

		return $this->checkDiscount( $price, (float) $price * ( 1 - (float) $percentage / 100 ) );
	}

	/**
	 * @param float $price
	 * @param float $percentage
	 *
	 * @return float
	 */
	protected function makeOverpricePercentage( $price, $percentage ) {
		return $this->checkOverprice( $price, (float) $price * ( 1 + (float) $percentage / 100 ) );
	}

	/**
	 * @param float $price
	 * @param float $discount_amount
	 *
	 * @return float
	 */
	private function makeDiscountAmount( $price, $discount_amount ) {
		return $this->checkDiscount( $price, (float) $price - (float) $discount_amount );
	}

	private function makeOverpriceAmount( $price, $overprice_amount ) {
		return $this->checkOverprice( $price, (float) $price + (float) $overprice_amount );
	}

	/**
	 * @param float $price
	 * @param float $value
	 *
	 * @return float
	 */
	protected function makePriceFixed( $price, $value ) {
		$value = floatval( $value );
		if ( $price < $value ) {
			return $this->checkOverprice( $price, $value );
		}

		return $this->checkDiscount( $price, $value );
	}

	/**
	 * @return float
	 */
	protected function makeFree() {
		return 0.0;
	}

	/**
	 * @param float $old_price
	 * @param float $new_price
	 *
	 * @return float
	 */
	private function checkDiscount( $old_price, $new_price ) {
		$new_price = max( $new_price, 0.0 );
		$new_price = min( $new_price, $old_price );

		if ( $this->discountTotalLimit !== null && $this->discountTotalLimit > 0 && $old_price - $new_price > $this->discountTotalLimit ) {
			$new_price = $old_price - $this->discountTotalLimit;
		}

		return (float) $new_price;
	}

	/**
	 * @param float $old_price
	 * @param float $new_price
	 *
	 * @return float
	 */
	private function checkOverprice( $old_price, $new_price ) {
		$new_price = max( $new_price, 0.0 );
		$new_price = max( $new_price, $old_price );

		return (float) $new_price;
	}
}
