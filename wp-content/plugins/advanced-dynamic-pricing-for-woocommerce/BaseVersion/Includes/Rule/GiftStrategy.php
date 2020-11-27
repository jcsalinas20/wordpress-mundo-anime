<?php

namespace ADP\BaseVersion\Includes\Rule;

use ADP\BaseVersion\Includes\Cart\CartTotals;
use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\Cart\Structures\CartItem;
use ADP\BaseVersion\Includes\Cart\Structures\CartItemsCollection;
use ADP\BaseVersion\Includes\Cart\Structures\CartSet;
use ADP\BaseVersion\Includes\Cart\Structures\CartSetCollection;
use ADP\BaseVersion\Includes\Cart\Structures\FreeCartItem;
use ADP\BaseVersion\Includes\External\CacheHelper;
use ADP\BaseVersion\Includes\Rule\Interfaces\Rule;
use ADP\BaseVersion\Includes\Rule\Structures\Gift;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;
use Exception;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class GiftStrategy {
	/**
	 * @var Rule
	 */
	protected $rule;

	/**
	 * @param Rule $rule
	 */
	public function __construct( $rule ) {
		$this->rule = $rule;
	}

	public function canGift() {
		return method_exists( $this->rule, 'getGifts' ) && boolval( $this->rule->getGifts() );
	}

	public function canItemGifts() {
		return method_exists( $this->rule, 'getItemGifts' ) && boolval( $this->rule->getItemGifts() );
	}

	/**
	 * TODO implement!
	 * Not requires without frontend implementation
	 *
	 * @param Cart  $cart
	 * @param array $usedQty
	 */
	public function addGifts( &$cart, $usedQty ) {
		$rule  = $this->rule;
		$gifts = $rule->getGifts();
	}

	/**
	 * @param Cart                $cart
	 * @param CartItemsCollection $collection
	 * @param float[]             $usedQty
	 */
	public function addCartItemGifts( &$cart, $collection, $usedQty ) {
		/** @var SingleItemRule $rule */
		$rule  = $this->rule;
		$isReplace = $rule->isReplaceItemGifts();
		$replaceCode = $rule->getReplaceItemGiftsCode();

		$totalQty = floatval( 0 );

		/**
		 * @var array $itemIndexes
		 * Cheap solution to fetch product with which we add free product.
		 * Needed for gifting products from collection.
		 *
		 * ItemIndexes is a list with items and indexes. Index in nutshell is number of attempt at which we begin to use item.
		 * For example:
		 *  We have 'single item' rule with collection 3 apple and 2 bananas and 1 orange.
		 *  So, itemIndexes will be
		 *      array(
		 *          array( 'index' => 1, 'item' => apple ),
		 *          array( 'index' => 4 (1 + qty of apple), 'item' => banana ),
		 *          array( 'index' => 6 (1 + qty of apple + qty of bananas ), 'item' => orange ),
		 *      )
		 *  When we try to iterate on attempts, we get an item
		 *  Attempt count | item
		 *  1   | apple
		 *  2   | apple
		 *  3   | apple
		 *  4   | banana
		 *  5   | banana
		 *  6   | orange
		 */
		$itemIndexes = array();
		foreach ( $collection->get_items() as $item ) {
			$itemIndexes[] = array(
				'index' => $totalQty + 1,
				'item'  => $item,
			);
			$totalQty      += $item->getQty();
		}

		$attemptCount = 0;
		if ( $rule->getItemGiftStrategy() === $rule::BASED_ON_LIMIT_ITEM_GIFT_STRATEGY ) {
			$attemptCount = min( $totalQty, $rule->getItemGiftLimit() );
		} elseif ( $rule->getItemGiftStrategy() === $rule::BASED_ON_SUBTOTAL_ITEM_GIFT_STRATEGY ) {
			if ( $rule->getItemGiftSubtotalDivider() ) {
				$tmpCart = clone $cart;
				foreach ( $collection->get_items() as $item ) {
					$tmpCart->addToCart( $item );
				}
				$itemsSubtotals = ( new CartTotals( $tmpCart ) )->getSubtotal();
				$attemptCount = min( $totalQty, intval( $itemsSubtotals / $rule->getItemGiftSubtotalDivider() ) );
			}
		}

		$index = 0;
		while ( $index < $attemptCount ) {
			$index++;
			$item = null;
			foreach ( $itemIndexes as $key => $data ) {
				if ( $data['index'] <= $index ) {
					$item = $data['item'];
				}
			}

			if ( ! $item ) {
				continue;
			}

			/** @var CartItem $item */

			foreach ( $rule->getItemGifts() as $gift ) {
				if ( $gift->getType() === $gift::TYPE_CLONE_ADJUSTED ) {
					$product    = $item->getWcItem()->getProduct();

					$tmpUsedQty = isset( $usedQty[ $product->get_id() ] ) ? $usedQty[ $product->get_id() ] : floatval( 0 );
					$qtyToAdd   = $this->getQtyAvailableForSale( $product, $tmpUsedQty, $gift->getQty() );

					try {
						$freeItem = new FreeCartItem( $product, $qtyToAdd, $this->rule->getId() );

						if ( $isReplace && $replaceCode ) {
							$freeItem->setReplaceWithCoupon( $isReplace );
							$freeItem->setReplaceCouponCode( $replaceCode );
						}

						$cart->addToCart( $freeItem );
					} catch ( Exception $e ) {
						continue;
					}
				} else {
					$this->applyGifts( $cart, $usedQty, $gift, $isReplace, $replaceCode );
				}
			}
		}
	}

	/**
	 * @param Cart              $cart
	 * @param CartSetCollection $collection
	 * @param float[]           $usedQty
	 */
	public function addCartSetGifts( &$cart, $collection, $usedQty ) {
		/** @var SingleItemRule $rule */
		$rule  = $this->rule;
		$isReplace = $rule->isReplaceItemGifts();
		$replaceCode = $rule->getReplaceItemGiftsCode();

		$totalQty = floatval( 0 );

		$setIndexes = array();
		foreach ( $collection->get_sets() as $set ) {
			$setIndexes[] = array(
				'index' => $totalQty + 1,
				'set'  => $set,
			);
			$totalQty      += $set->getQty();
		}

		$attemptCount = 0;
		if ( $rule->getItemGiftStrategy() === $rule::BASED_ON_LIMIT_ITEM_GIFT_STRATEGY ) {
			$attemptCount = min( $totalQty, $rule->getItemGiftLimit() );
		} elseif ( $rule->getItemGiftStrategy() === $rule::BASED_ON_SUBTOTAL_ITEM_GIFT_STRATEGY ) {
			if ( $rule->getItemGiftSubtotalDivider() ) {
				$tmpCart = clone $cart;
				foreach ( $collection->get_sets() as $set ) {
					foreach ( $set->get_items() as $item ) {
						$tmpCart->addToCart( $item );
					}
				}
				$itemsSubtotals = ( new CartTotals( $tmpCart ) )->getSubtotal();
				$attemptCount   = min( $totalQty, intval($itemsSubtotals / $rule->getItemGiftSubtotalDivider()) );
			}
		}

		$index = 0;
		while ( $index < $attemptCount ) {
			$index++;
			$set = null;
			foreach ( $setIndexes as $key => $data ) {
				if ( $data['index'] <= $index ) {
					$set = $data['set'];
				}
			}

			if ( ! $set ) {
				continue;
			}

			/** @var CartSet $set */

			foreach ( $rule->getItemGifts() as $gift ) {
				if ( $gift->getType() === $gift::TYPE_CLONE_ADJUSTED ) {
					foreach ( $set->get_items() as $item ) {
						$product = $item->getWcItem()->getProduct();

						$tmpUsedQty = isset( $usedQty[ $product->get_id() ] ) ? $usedQty[ $product->get_id() ] : floatval( 0 );
						$qtyToAdd   = $this->getQtyAvailableForSale( $product, $tmpUsedQty, $gift->getQty() );

						try {
							$freeItem = new FreeCartItem( $product, $qtyToAdd, $this->rule->getId() );

							if ( $isReplace && $replaceCode ) {
								$freeItem->setReplaceWithCoupon( $isReplace );
								$freeItem->setReplaceCouponCode( $replaceCode );
							}

							$cart->addToCart( $freeItem );
						} catch ( Exception $e ) {
							continue;
						}
					}
				} else {
					$this->applyGifts( $cart, $usedQty, $gift, $isReplace, $replaceCode );
				}
			}
		}
	}

	/**
	 * @param Cart    $cart
	 * @param array   $usedQty
	 * @param Gift    $gift
	 * @param boolean $isReplace
	 * @param string  $replaceCode
	 */
	protected function applyGifts( $cart, $usedQty, $gift, $isReplace, $replaceCode ) {
		$qty = $gift->getQty();
		foreach ( $gift->getValues() as $productId ) {
			$product    = CacheHelper::getWcProduct( $productId );
			$tmpUsedQty = isset( $usedQty[ $productId ] ) ? $usedQty[ $productId ] : floatval( 0 );
			$qtyToAdd   = $this->getQtyAvailableForSale( $product, $tmpUsedQty, $qty );

			if ( $qtyToAdd > 0 ) {
				try {
					$freeItem = new FreeCartItem( $product, $qtyToAdd, $this->rule->getId() );

					if ( $isReplace && $replaceCode ) {
						$freeItem->setReplaceWithCoupon( $isReplace );
						$freeItem->setReplaceCouponCode( $replaceCode );
					}

					$cart->addToCart( $freeItem );
				} catch ( Exception $e ) {
					continue;
				}

				if ( isset( $usedQty[ $productId ] ) ) {
					$usedQty[ $productId ] += $qtyToAdd;
				} else {
					$usedQty[ $productId ] = $qtyToAdd;
				}

				$qty -= $qtyToAdd;
			}
		}
	}

	/**
	 * Collect already gifted products
	 *
	 * @return array
	 *
	 * @var Cart $cart
	 *
	 */
	public function calculateUsedQtyFreeItems( $cart ) {
		$usedQty = array();

		foreach ( $cart->getFreeItems() as $item ) {
			$product    = $item->getProduct();
			$product_id = $product->get_id();

			if ( ! isset( $usedQty[ $product_id ] ) ) {
				$usedQty[ $product_id ] = 0;
			}
			$usedQty[ $product_id ] += $item->getQty();
		}

		return $usedQty;
	}

	/**
	 * @param $product WC_Product
	 * @param $qtyUsed integer
	 * @param $qtyRequired integer
	 *
	 * @return float
	 */
	protected function getQtyAvailableForSale( $product, $qtyUsed, $qtyRequired ) {
		if ( $product->managing_stock() ) {
			if ( $product->backorders_allowed() ) {
				$qty = $qtyRequired;
			} else {
				$available_for_now = $product->get_stock_quantity() - $qtyUsed;
				$qty               = $available_for_now >= $qtyRequired ? $qtyRequired : $available_for_now;
			}
		} else {
			$qty = $qtyRequired;
		}

		return floatval( $qty );
	}
}
