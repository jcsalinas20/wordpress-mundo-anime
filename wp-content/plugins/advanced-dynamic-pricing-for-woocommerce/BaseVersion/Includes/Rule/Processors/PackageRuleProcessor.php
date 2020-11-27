<?php

namespace ADP\BaseVersion\Includes\Rule\Processors;

use ADP\BaseVersion\Includes\Cart\Structures\CartItem;
use ADP\BaseVersion\Includes\Cart\Structures\CartSet;
use ADP\BaseVersion\Includes\Cart\Structures\CartItemsCollection;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\Rule\CartAdjustmentsApplyStrategy;
use ADP\BaseVersion\Includes\Rule\GiftStrategy;
use ADP\BaseVersion\Includes\Rule\Interfaces\RuleProcessor;
use ADP\BaseVersion\Includes\Rule\ConditionsCheckStrategy;
use ADP\BaseVersion\Includes\Rule\RoleDiscountStrategy;
use ADP\BaseVersion\Includes\Rule\RuleTimer;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule;
use ADP\BaseVersion\Includes\Rule\Structures\RangeDiscount;
use ADP\BaseVersion\Includes\Rule\Exceptions\RuleExecutionTimeout;
use ADP\BaseVersion\Includes\Rule\LimitsCheckStrategy;
use ADP\BaseVersion\Includes\Rule\PriceCalculator;
use ADP\BaseVersion\Includes\Rule\RuleSetCollector;
use ADP\BaseVersion\Includes\Cart\Structures\CartSetCollection;
use ADP\BaseVersion\Includes\Rule\Structures\Discount;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentSplit;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentTotal;
use ADP\BaseVersion\Includes\Rule\Structures\SetDiscount;
use ADP\BaseVersion\Includes\Rule\TierUpItems;
use ADP\Factory;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PackageRuleProcessor implements RuleProcessor {
	const STATUS_OUT_OF_TIME = - 2;
	const STATUS_UNEXPECTED_ERROR = - 1;
	const STATUS_NO_INFO = 0;
	const STATUS_STARTED = 1;
	const STATUS_DISABLED_WITH_FORCE = 2;
	const STATUS_LIMITS_NOT_PASSED = 3;
	const STATUS_CONDITIONS_NOT_PASSED = 4;
	const STATUS_FILTERS_NOT_PASSED = 5;

	protected $status;
	protected $lastUnexpectedErrorMessage;

	/**
	 * @var float Rule start timestamp
	 */
	protected $execRuleStart;

	/**
	 * @var float Rule start timestamp
	 */
	protected $lastExecTime;

	/**
	 * @var PackageRule
	 */
	protected $rule;

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * The way how we check conditions
	 * @var ConditionsCheckStrategy
	 */
	protected $conditionsCheckStrategy;

	/**
	 * The way how we check limits
	 * @var LimitsCheckStrategy
	 */
	protected $limitsCheckStrategy;

	/**
	 * The way how we apply cart adjustments
	 * @var CartAdjustmentsApplyStrategy
	 */
	protected $cartAdjustmentsApplyStrategy;

	/**
	 * @var RuleTimer
	 */
	protected $ruleTimer;

	/**
	 * The way how we gift items
	 * @var GiftStrategy
	 */
	protected $giftStrategy;

	/**
	 * @var RoleDiscountStrategy
	 */
	protected $roleDiscountStrategy;

	/**
	 * @param Context $context
	 * @param PackageRule    $rule
	 *
	 * @throws Exception
	 */
	public function __construct( $context, $rule ) {
		$this->context = $context;

		if ( ! ( $rule instanceof PackageRule ) ) {
			$context->handle_error( new Exception( "Wrong rule type" ) );
		}

		$this->rule = $rule;

		$this->conditionsCheckStrategy      = new ConditionsCheckStrategy( $rule );
		$this->limitsCheckStrategy          = new LimitsCheckStrategy( $rule );
		$this->cartAdjustmentsApplyStrategy = new CartAdjustmentsApplyStrategy( $rule );
		$this->ruleTimer                    = new RuleTimer( $context, $rule );
		$this->giftStrategy                 = new GiftStrategy( $rule );
		$this->roleDiscountStrategy         = new RoleDiscountStrategy( $rule );
	}

	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return PackageRule
	 */
	public function getRule() {
		return $this->rule;
	}

	/**
	 * @inheritDoc
	 */
	public function applyToCart( $cart ) {
		$this->ruleTimer->start();

		global $wp_filter;
		$current_wp_filter = $wp_filter;

		try {
			$this->process( $cart );
		} catch ( RuleExecutionTimeout $e ) {
			$this->status = self::STATUS_OUT_OF_TIME;
			$this->ruleTimer->handleOutOfTime();
		}

		$wp_filter = $current_wp_filter;

		$this->ruleTimer->finish();

		return true;
	}

	/**
	 * @param Cart $cart
	 *
	 * @throws RuleExecutionTimeout
	 *
	 */
	protected function process( $cart ) {
		$this->status = self::STATUS_STARTED;

//		$this->rule = apply_filters( 'adp_before_apply_single_item_rule', $this->rule, $this, $cart );

		if ( apply_filters( 'adp_force_disable_single_item_rule', false, $this->rule, $this, $cart ) ) {
			$this->status = self::STATUS_DISABLED_WITH_FORCE;

			return;
		}

		if ( ! $this->isRuleMatchedCart( $cart ) ) {
			return;
		}
		$this->ruleTimer->checkExecutionTime();

		$setCollection = $this->create_sets( $cart );

		if ( ! $setCollection || ! $setCollection->get_sets() ) {
			$this->status = $this::STATUS_FILTERS_NOT_PASSED;

			return;
		}

		$this->applyProductAdjustment( $cart, $setCollection );

		$this->addFreeProducts( $cart, $setCollection );
		$this->ruleTimer->checkExecutionTime();

		$this->addGifts( $cart, $setCollection );
		$this->ruleTimer->checkExecutionTime();

		$this->applyCartAdjustments( $cart, $setCollection );

		$this->ruleTimer->checkExecutionTime();

		$this->applyChangesToCart( $cart, $setCollection );
	}

	/**
	 * @param $cart
	 *
	 * @return bool
	 */
	public function isRuleMatchedCart( $cart ) {
		if ( ! $this->checkLimits( $cart ) ) {
			$this->status = $this::STATUS_LIMITS_NOT_PASSED;

			return false;
		}

		if ( ! $this->checkConditions( $cart ) ) {
			$this->status = $this::STATUS_CONDITIONS_NOT_PASSED;

			return false;
		}

		return true;
	}

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	protected function checkLimits( $cart ) {
		return $this->limitsCheckStrategy->check( $cart );
	}

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	protected function checkConditions( $cart ) {
		return $this->conditionsCheckStrategy->check( $cart );
	}

	/**
	 * @param Cart $cart
	 * @param CartSetCollection $setCollection
	 */
	protected function applyCartAdjustments( $cart, $setCollection ) {
		$this->cartAdjustmentsApplyStrategy->applyToCartWithSets( $cart, $setCollection );
	}

	/**
	 * @param $cart Cart
	 *
	 * @return CartSetCollection|false
	 * @throws RuleExecutionTimeout
	 * @throws \Exception
	 */
	protected function create_sets( &$cart ) {
		if ( ! $cartMutableItems = $cart->getMutableItems() ) {
			return false;
		}

		uasort( $cartMutableItems, array( $this, 'sortItems' ) );
		$cartMutableItems = array_values( $cartMutableItems );

		$cart->purgeMutableItems();

		return $this->collect_sets( $cartMutableItems, $cart );
	}

	protected function sortItems( $item1, $item2 ) {
		$rule = $this->rule;

		if ( $rule::APPLY_FIRST_AS_APPEAR === $this->rule->getApplyFirstTo() ) {
			return 0;
		}

		/**
		 * @var $item1 CartItem
		 * @var $item2 CartItem
		 */
		$price1 = $item1->getOriginalPrice();
		$price2 = $item2->getOriginalPrice();

		if ( $rule::APPLY_FIRST_TO_CHEAP === $this->rule->getApplyFirstTo() ) {
			return $price1 - $price2;
		} elseif ( $rule::APPLY_FIRST_TO_EXPENSIVE === $this->rule->getApplyFirstTo() ) {
			return $price2 - $price1;
		}

		return 0;
	}

	/**
	 * @param CartItem[] $cart_items
	 * @param Cart            $cart
	 *
	 * @return CartSetCollection
	 * @throws \Exception
	 */
	protected function collect_sets( $cart_items, $cart ) {
		/**
		 * @var RuleSetCollector $set_collector
		 */
		$set_collector = Factory::get( "Rule_RuleSetCollector", $this->rule );

		$set_collector->register_check_execution_time_function( array( $this->ruleTimer, 'checkExecutionTime' ), $cart->get_context() );
		$set_collector->add_items( $cart_items );
		$set_collector->apply_filters( $cart );

		return $set_collector->collect_sets( $cart );
	}

	/**
	 * @param Cart $cart
	 * @param CartSetCollection $collection
	 */
	protected function applyProductAdjustment( &$cart, &$collection ) {
		$handler = $this->rule->getProductAdjustmentHandler();

		if ( $handler instanceof ProductsAdjustmentTotal ) {
			/** @var ProductsAdjustmentTotal $handler */
			$priceCalculator = new PriceCalculator( $this->rule, $handler->getDiscount(), $handler->getMaxAvailableAmount() );
			foreach ( $collection->get_sets() as $set ) {
				// todo implement replace with coupon/fee
				$priceCalculator->calculatePriceForSet( $set, $cart, $handler );
			}
		} elseif ( $handler instanceof ProductsAdjustmentSplit ) {
			foreach ( $collection->get_sets() as $set ) {
				foreach ( array_values( $set->get_positions() ) as $position ) {
					foreach ( $set->get_items_by_position( $position ) as $item ) {
						/**
						 * @var ProductsAdjustmentSplit $handler
						 * @var CartItem                $item
						 */
						$priceCalculator = new PriceCalculator( $this->rule, $handler->getDiscount( $position ), $handler->getMaxAvailableAmount() );

						/**
						 * Temporary change the item qty.
						 * In $priceCalculator->applyItemDiscount(), we can replace discount with a coupon, so the full
						 * amount of the discount is required.
						 */
						$baseQty = $item->getQty();
						$item->setQty( $baseQty * $set->getQty() );
						$priceCalculator->applyItemDiscount( $item, $cart, $handler );
						$item->setQty( $baseQty );
					}
				}
			}
		}

		if ( ! $this->rule->getRoleDiscounts() && ! $this->rule->getProductRangeAdjustmentHandler() ) {
			return;
		} elseif ( ! $this->rule->getRoleDiscounts() ) {
			$this->applyRangeDiscounts( $cart, $collection );
		} elseif ( ! $this->rule->getProductRangeAdjustmentHandler() ) {
			$this->roleDiscountStrategy->processSets( $cart, $collection );
		} elseif ( $this->rule->getSortableApplyMode() === 'consistently' ) {
			$rolesApplied            = false;
			$doNotApplyBulkAfterRole = $this->rule->isDontApplyBulkIfRolesMatched();
			$initialCollection       = clone $collection;
			foreach ( $this->rule->getSortableBlocksPriority() as $blockName ) {
				if ( 'roles' == $blockName ) {
					$this->roleDiscountStrategy->processSets( $cart, $collection );
					$rolesApplied = $initialCollection->get_hash() !== $collection->get_hash();
				} elseif ( 'bulk-adjustments' == $blockName ) {
					if ( $doNotApplyBulkAfterRole && $rolesApplied ) {
						continue;
					}

					$this->applyRangeDiscounts( $cart, $collection );
				}
			}
		} elseif ( $this->rule->getSortableApplyMode() === 'min_price_between' || $this->rule->getSortableApplyMode() === 'max_price_between' ) {
			$roleSetCollection = clone $collection;
			$this->roleDiscountStrategy->processSets( $cart, $roleSetCollection );

			$discountRangeSetCollection = clone $collection;
			$this->applyRangeDiscounts( $cart, $discountRangeSetCollection );

			$discountRangeItems = $discountRangeSetCollection->get_sets();

			$collection->purge();
			foreach ( $roleSetCollection->get_sets() as $roleItem ) {
				$matched = false;
				foreach ( $discountRangeItems as $index => $discountRangeItem ) {
					if ( $roleItem->get_hash() !== $discountRangeItem->get_hash() ) {
						continue;
					}

					$comparison = $this->rule->getSortableApplyMode() === 'min_price_between' ? "min" : "max";

					if ( $comparison( $roleItem->get_total_price(),
							$discountRangeItem->get_total_price() ) === $roleItem->get_total_price() ) {
						$collection->add( $roleItem );
					} else {
						$collection->add( $discountRangeItem );
					}

					unset( $discountRangeItems[ $index ] );
					$matched = true;
					break;
				}

				if ( ! $matched ) {
					$collection->add( $roleItem );
				}
			}
		}
	}

	/**
	 * @param Cart              $cart
	 * @param CartSetCollection $collection
	 */
	protected function applyRangeDiscounts( &$cart, &$collection ) {
		if ( ! ( $handler = $this->rule->getProductRangeAdjustmentHandler() ) ) {
			return;
		}

		/**
		 * @var CartSet[] $sets
		 */
		$sets = $collection->get_sets();
		if ( $handler::TYPE_BULK === $handler->getType() ) {
			$totalQty = 0;

			if ( $handler::GROUP_BY_DEFAULT === $handler->getGroupBy() ) {
				$totalQty = array_sum( array_map( function ( $set ) {
					/**
					 * @var CartSet $set
					 */
					$totalQty_set = 0;
					foreach ( $set->get_items() as $item ) {
						$totalQty_set += $item->getQty() * $set->getQty();
					}

					return $totalQty_set;
				}, $collection->get_sets() ) );
			} elseif ( $handler::GROUP_BY_PRODUCT === $handler->getGroupBy() ) {
				$products = array();
				$totalQty = array_sum( array_map( function ( $set ) use ( &$products ) {
					/**
					 * @var CartSet    $set
					 * @var CartItem[] $items
					 */
					$items        = $set->get_items();
					$totalQty_set = 0;

					foreach ( $items as $item ) {
						if ( ! in_array( $item->getWcItem()->getProductId(), $products ) ) {
							$products[]   = $item->getWcItem()->getProductId();
							$totalQty_set += $item->getQty() * $set->getQty();
						}
					}

					return $totalQty_set;
				}, $collection->get_sets() ) );
			} elseif ( $handler::GROUP_BY_VARIATION === $handler->getGroupBy() ) {
				$variations = array();
				$totalQty   = array_sum( array_map( function ( $set ) use ( &$variations ) {
					/**
					 * @var CartSet    $set
					 * @var CartItem[] $items
					 */
					$items        = $set->get_items();
					$totalQty_set = 0;

					foreach ( $items as $item ) {
						if ( ! in_array( $item->getWcItem()->getVariationId(), $variations ) ) {
							$variations[] = $item->getWcItem()->getVariationId();
							$totalQty_set += $item->getQty() * $set->getQty();
						}
					}

					return $totalQty_set;
				}, $collection->get_sets() ) );
			} elseif ( $handler::GROUP_BY_CART_POSITIONS === $handler->getGroupBy() ) {
				$totalQty = array_sum( array_map( function ( $set ) {
					/**
					 * @var CartSet $set
					 */
					$totalQty_set = 0;

					foreach ( $set->get_items() as $item ) {
						/**
						 * @var CartItem $item
						 */
						$totalQty_set += $item->getQty() * $set->getQty();
					}

					return $totalQty_set;
				}, $collection->get_sets() ) );
			} elseif ( $handler::GROUP_BY_SETS === $handler->getGroupBy() ) {
				$totalQty = array_sum( array_map( function ( $set ) {
					/**
					 * @var CartSet $set
					 */
					$totalQty_set = $set->get_qty();

					return $totalQty_set;
				}, $collection->get_sets() ) );
			} elseif ( $handler::GROUP_BY_ALL_ITEMS_IN_CART === $handler->getGroupBy() ) {
				$totalQty = array_map( function ( $set ) {
					/**
					 * @var CartSet $set
					 */
					$totalQty_set = 0;
					foreach ( $set->get_items() as $item ) {
						$totalQty_set += $item->getQty() * $set->getQty();
					}

					return $totalQty_set;
				}, $collection->get_sets() );

				$totalQty = array_sum($totalQty);

				$totalQty += array_sum( array_map( function ( $item ) {
					$facade = $item->getWcItem();

					return $facade->isVisible() ? $item->getQty() : floatval( 0 );
				}, $cart->getItems() ) );
			} elseif ( $handler::GROUP_BY_PRODUCT_CATEGORIES === $handler->getGroupBy() ) {
				$usedCategoryIds = array();
				$totalQty = 0;
				foreach ( $collection->get_sets() as $set ) {
					foreach ( $set->get_items() as $item ) {
						$usedCategoryIds += $item->getWcItem()->getProduct()->get_category_ids();
						$totalQty += $item->getQty() * $set->getQty();
					}
				}
				$usedCategoryIds = array_unique( $usedCategoryIds );

				if ( $usedCategoryIds ) {
					foreach ( $cart->getItems() as $item ) {
						$facade = $item->getWcItem();
						if ( ! $facade->isVisible() ) {
							continue;
						}

						$product = $facade->getProduct();

						if ( count( array_intersect( $product->get_category_ids(), $usedCategoryIds ) ) ) {
							$totalQty += $item->getQty();
						}
					}
				}
			} elseif ( $handler::GROUP_BY_PRODUCT_SELECTED_PRODUCTS === $handler->getGroupBy() ) {
				$selectedProductIds = $handler->getSelectedProductIds();

				$totalQty = 0;
				if ( $selectedProductIds ) {
					foreach ( $collection->get_sets() as $set ) {
						foreach ( $set->get_items() as $item ) {
							$facade = $item->getWcItem();

							if ( in_array( $facade->getProduct()->get_id(), $selectedProductIds ) ) {
								$totalQty += $facade->getQty() * $set->getQty();
							}
						}
					}

					foreach ( $cart->getItems() as $item ) {
						$facade = $item->getWcItem();
						if ( ! $facade->isVisible() ) {
							continue;
						}

						if ( in_array( $facade->getProduct()->get_id(), $selectedProductIds ) ) {
							$totalQty += $facade->getQty();
						}
					}
				}
			} elseif ( $handler::GROUP_BY_PRODUCT_SELECTED_CATEGORIES === $handler->getGroupBy() ) {
				$selectedCategoryIds = $handler->getSelectedCategoryIds();

				$totalQty = 0;
				if ( $selectedCategoryIds ) {
					foreach ( $collection->get_sets() as $set ) {
						foreach ( $set->get_items() as $item ) {
							$facade = $item->getWcItem();

							if ( count( array_intersect( $facade->getProduct()->get_category_ids(), $selectedCategoryIds ) ) ) {
								$totalQty += $facade->getQty() * $set->getQty();
							}
						}
					}

					foreach ( $cart->getItems() as $item ) {
						$facade = $item->getWcItem();
						if ( ! $facade->isVisible() ) {
							continue;
						}

						if ( count( array_intersect( $facade->getProduct()->get_category_ids(), $selectedCategoryIds ) ) ) {
							$totalQty += $facade->getQty();
						}
					}
				}
			} elseif ( $handler::GROUP_BY_META_DATA === $handler->getGroupBy() ) {
				$products_by_meta = array();
				$totalQty   = array_sum( array_map( function ( $set ) use ( &$products_by_meta ) {
					/**
					 * @var CartSet    $set
					 * @var CartItem[] $items
					 */
					$items        = $set->get_items();
					$totalQty_set = 0;

					foreach ( $items as $item ) {
						$facade = $item->getWcItem();

						$meta = $facade->getProduct()->get_meta_data();

						usort( $meta, function( $a, $b ) {
							return strcmp( $a->__get('key'), $b->__get('key') );
						} );

						$meta[] = $facade->getProductId();
						$meta[] = $facade->getVariationId();

						$hash = md5( json_encode( $meta ) );

						if ( ! in_array( $hash, $products_by_meta ) ) {
							$products_by_meta[] = $hash;
							$totalQty_set += $item->getQty() * $set->getQty();
						}
					}

					return $totalQty_set;
				}, $collection->get_sets() ) );
			}

			$ranges = $handler->getRanges();
			foreach ( $ranges as $range ) {
				/**
				 * @var RangeDiscount $range
				 */
				if ( $range->isIn( $totalQty ) ) {
					$discount        = $range->getData();
					$priceCalculator = new PriceCalculator( $this->rule, $discount );
					if ( $discount instanceof SetDiscount ) { //have to check child class first
						foreach ( $collection->get_sets() as $set ) {
							$priceCalculator->calculatePriceForSet( $set, $cart, $handler );
						}
					} elseif ( $discount instanceof Discount ) {
						foreach ( $collection->get_sets() as $set ) {
							foreach ( $set->get_items() as $item ) {
								$priceCalculator->applyItemDiscount( $item, $cart, $handler );
							}
						}
					}
					break;
				}
			}
		} elseif ( $handler::TYPE_TIER === $handler->getType() ) {
			if ( $handler::GROUP_BY_DEFAULT === $handler->getGroupBy() ) {
				$items = array();
				foreach ( $collection->get_sets() as $set ) {
					foreach ( $set->get_items() as $item ) {
						$newItem = clone $item;
						$newItem->setQty($item->getQty() * $set->getQty());
						$items[] = $newItem;
					}
				}

				$cal           = new TierUpItems( $this->rule, $cart );
				$newCollection = new CartItemsCollection( $this->rule->getId() );
				foreach ( $cal->executeItems( $items ) as $item ) {
					$newCollection->add( $item );
				}

				$collection = $this->collect_sets( $newCollection->get_items(), $cart );
			} elseif ( $handler::GROUP_BY_SETS === $handler->getGroupBy() ) {
				$cal           = new TierUpItems( $this->rule, $cart );
				$newCollection = new CartSetCollection();
				foreach ( $cal->executeSets( $sets ) as $item ) {
					$newCollection->add( $item );
				}

				$collection = $newCollection;
			} elseif ( $handler::GROUP_BY_PRODUCT === $handler->getGroupBy() ) {
				$groupedByProduct = array();
				foreach ( $collection->get_sets() as $set ) {
					foreach ( $set->get_items() as $item ) {
						$productId = $item->getWcItem()->getProductId();

						$newItem = clone $item;
						$newItem->setQty( $item->getQty() * $set->getQty() );

						if ( ! isset( $groupedByProduct[ $productId ] ) ) {
							$groupedByProduct[ $productId ] = array();
						}
						$groupedByProduct[ $productId ][] = $newItem;
					}
				}

				$cal           = new TierUpItems( $this->rule, $cart );
				$newCollection = new CartItemsCollection( $this->rule->getId() );
				foreach ( $groupedByProduct as $items ) {
					foreach ( $cal->executeItems( $items ) as $item ) {
						$newCollection->add( $item );
					}
				}

				$collection = $this->collect_sets( $newCollection->get_items(), $cart );
			} elseif ( $handler::GROUP_BY_VARIATION === $handler->getGroupBy() ) {
				$groupedByVariation = array();
				foreach ( $collection->get_sets() as $set ) {
					foreach ( $set->get_items() as $item ) {
						if ( $item->getWcItem()->getVariationId() ) {
							$productId = $item->getWcItem()->getVariationId();
						} else {
							$productId = $item->getWcItem()->getProductId();
						}

						$newItem = clone $item;
						$newItem->setQty( $item->getQty() * $set->getQty() );

						if ( ! isset( $groupedByVariation[ $productId ] ) ) {
							$groupedByVariation[ $productId ] = array();
						}
						$groupedByVariation[ $productId ][] = $newItem;
					}
				}

				$cal           = new TierUpItems( $this->rule, $cart );
				$newCollection = new CartItemsCollection( $this->rule->getId() );
				foreach ( $groupedByVariation as $items ) {
					foreach ( $cal->executeItems( $items ) as $item ) {
						$newCollection->add( $item );
					}
				}

				$collection = $this->collect_sets( $newCollection->get_items(), $cart );
			} elseif ( $handler::GROUP_BY_PRODUCT_SELECTED_PRODUCTS === $handler->getGroupBy() ) {
				$selectedProductIds = $handler->getSelectedProductIds();

				$totalQty = 0;
				if ( $selectedProductIds ) {
					$items = array();
					$notMatchedItems = array();
					foreach ( $collection->get_sets() as $set ) {
						foreach ( $set->get_items() as $item ) {
							$facade = $item->getWcItem();
							$newItem = clone $item;
							$newItem->setQty($item->getQty() * $set->getQty());

							if ( in_array( $facade->getProduct()->get_id(), $selectedProductIds ) ) {
								$items[] = $newItem;
								$totalQty += $newItem->getQty();
							} else {
								$notMatchedItems[] = $newItem;
							}
						}
					}

					foreach ( $cart->getItems() as $item ) {
						$facade = $item->getWcItem();
						if ( ! $facade->isVisible() ) {
							continue;
						}

						if ( in_array( $facade->getProduct()->get_id(), $selectedProductIds ) ) {
							$totalQty += $item->getQty();
						}
					}

					$cal           = new TierUpItems( $this->rule, $cart );
					$newCollection = new CartItemsCollection( $this->rule->getId() );
					foreach ( $cal->executeItemsWithCustomQty( $items, $totalQty ) as $item ) {
						$newCollection->add( $item );
					}

					foreach ( $notMatchedItems as $item ) {
						$newCollection->add( $item );
					}

					$collection = $this->collect_sets( $newCollection->get_items(), $cart );
				}

			} elseif ( $handler::GROUP_BY_PRODUCT_SELECTED_CATEGORIES === $handler->getGroupBy() ) {
				$selectedCategoryIds = $handler->getSelectedCategoryIds();

				$totalQty = 0;
				if ( $selectedCategoryIds ) {
					$items = array();
					$notMatchedItems = array();
					foreach ( $collection->get_sets() as $set ) {
						foreach ( $set->get_items() as $item ) {
							$facade = $item->getWcItem();
							$newItem = clone $item;
							$newItem->setQty($item->getQty() * $set->getQty());

							if ( count( array_intersect( $facade->getProduct()->get_category_ids(), $selectedCategoryIds ) ) ) {
								$items[] = $newItem;
								$totalQty += $newItem->getQty();
							} else {
								$notMatchedItems[] = $newItem;
							}
						}
					}

					foreach ( $cart->getItems() as $item ) {
						$facade = $item->getWcItem();
						if ( ! $facade->isVisible() ) {
							continue;
						}

						if ( count( array_intersect( $facade->getProduct()->get_category_ids(), $selectedCategoryIds ) ) ) {
							$totalQty += $item->getQty();
						}
					}

					$cal           = new TierUpItems( $this->rule, $cart );
					$newCollection = new CartItemsCollection( $this->rule->getId() );
					foreach ( $cal->executeItemsWithCustomQty( $items, $totalQty ) as $item ) {
						$newCollection->add( $item );
					}

					foreach ( $notMatchedItems as $item ) {
						$newCollection->add( $item );
					}

					$collection = $this->collect_sets( $newCollection->get_items(), $cart );
				}

			}
		}
	}

	/**
	 * @param $cart Cart
	 * @param $collection CartSetCollection
	 */
	protected function applyChangesToCart( &$cart, $collection ) {
		foreach ( $collection->get_sets() as $set ) {
			$setQty = $set->get_qty();

			foreach ( $set->get_items() as $item ) {
				$item->setQty($item->getQty() * $setQty);
				$cart->addToCart( $item );
			}
		}


		$cart->destroyEmptyItems();
	}

	/**
	 * @param Cart              $cart
	 * @param CartSetCollection $collection
	 */
	protected function addFreeProducts( $cart, $collection ) {
		if ( ! $this->giftStrategy->canItemGifts() ) {
			return;
		}

		// needs for calculate limit
		$setsCount = 0;
		foreach ( $collection->get_sets() as $set ) {
			$setsCount += $set->get_qty();
		}

		$usedQty = $this->calculateUsedQty( $cart, $collection );
		$this->giftStrategy->addCartSetGifts( $cart, $collection, $usedQty );
	}

	/**
	 * @param Cart              $cart
	 * @param CartSetCollection $collection
	 */
	protected function addGifts( $cart, $collection ) {
		if ( ! $this->giftStrategy->canGift() ) {
			return;
		}

		$usedQty = $this->calculateUsedQty( $cart, $collection );
		$this->giftStrategy->addGifts( $cart, $usedQty );
	}

	/**
	 * @param Cart              $cart
	 * @param CartSetCollection $collection
	 *
	 * @return array
	 */
	protected function calculateUsedQty( &$cart, &$collection ) {
		$usedQty = $this->giftStrategy->calculateUsedQtyFreeItems( $cart );

		foreach ( $cart->getItems() as $item ) {
			$wrapper    = $item->getWcItem();
			$product_id = $wrapper->getVariationId() ? $wrapper->getVariationId() : $wrapper->getProductId();

			if ( ! isset( $usedQty[ $product_id ] ) ) {
				$usedQty[ $product_id ] = 0;
			}
			$usedQty[ $product_id ] += $item->getQty();
		}

		foreach ( $collection->get_sets() as $set ) {
			foreach ( $set->get_items() as $item ) {
				$wrapper    = $item->getWcItem();
				$product_id = $wrapper->getVariationId() ? $wrapper->getVariationId() : $wrapper->getProductId();

				if ( ! isset( $usedQty[ $product_id ] ) ) {
					$usedQty[ $product_id ] = 0;
				}
				$usedQty[ $product_id ] += $item->getQty() * $set->get_qty();
			}
		}

		return $usedQty;
	}

	/**
	 * @return float
	 */
	public function getLastExecTime() {
		return $this->ruleTimer->getLastExecTime();
	}
}
