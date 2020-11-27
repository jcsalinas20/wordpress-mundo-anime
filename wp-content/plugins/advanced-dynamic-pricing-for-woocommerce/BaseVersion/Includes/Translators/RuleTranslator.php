<?php

namespace ADP\BaseVersion\Includes\Translators;

use ADP\BaseVersion\Includes\Rule\Structures\Discount;
use ADP\BaseVersion\Includes\Rule\Structures\NoItemRule;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentSplit;
use ADP\BaseVersion\Includes\Rule\Structures\PackageRule\ProductsAdjustmentTotal;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule;
use ADP\BaseVersion\Includes\Rule\Structures\SingleItemRule\ProductsAdjustment;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class RuleTranslator {
	/**
	 * @param SingleItemRule|PackageRule|NoItemRule $rule
	 *
	 * @return NoItemRule|PackageRule|SingleItemRule
	 * @return NoItemRule|PackageRule|SingleItemRule
	 */
    public static function setCurrency( $rule, $rate ) {
        //$this->currency = $currency;

		if ( $rule->hasProductAdjustment() ) {
            $product_adj = $rule->getProductAdjustmentHandler();
            if( $product_adj instanceof ProductsAdjustment OR
            $product_adj instanceof ProductsAdjustmentTotal ) {
                if( $product_adj->isMaxAvailableAmountExists() ) {
                    $product_adj->setMaxAvailableAmount( $product_adj->getMaxAvailableAmount() * $rate );
                }
                $discount = $product_adj->getDiscount();
                if( $discount->getType() !== Discount::TYPE_PERCENTAGE ) {
                    $discount->setValue( $discount->getValue() * $rate );
                }
                $product_adj->setDiscount( $discount );
            }
            elseif( $product_adj instanceof ProductsAdjustmentSplit ) {
                $discounts = $product_adj->getDiscounts();
                foreach( $discounts as &$discount ) {
                    if( $discount->getType() !== Discount::TYPE_PERCENTAGE ) {
                        $discount->setValue( $discount->getValue() * $rate );
                    }
                }
                $product_adj->setDiscounts( $discounts );
            }

            $rule->installProductAdjustmentHandler( $product_adj );
        }

        if( $rule->hasProductRangeAdjustment() ) {
            $product_adj = $rule->getProductRangeAdjustmentHandler();
            $ranges = $product_adj->getRanges();
            foreach( $ranges as &$range ) {
                $discount = $range->getData();
                if( $discount->getType() !== Discount::TYPE_PERCENTAGE ) {
                    $discount->setValue( $discount->getValue() * $rate );
                    $range->setData( $discount );
                }
            }
            $product_adj->setRanges( $ranges );

            $rule->installProductRangeAdjustmentHandler( $product_adj );
        }
        
        $role_discounts = array();
        if( $rule->getRoleDiscounts() !== null ) {
            foreach( $rule->getRoleDiscounts() as $role_discount ) {
                $discount = $role_discount->getDiscount();
                if( $discount->getType() !== Discount::TYPE_PERCENTAGE ) {
                    $discount->setValue( $discount->getValue() * $rate );
                }
                $role_discount->setDiscount( $discount );
                $role_discounts[] = $role_discount;
            }
            $rule->setRoleDiscounts( $role_discounts );
        }

        if( $rule->getCartAdjustments() ) {
            $cart_adjs = $rule->getCartAdjustments();
            foreach ( $cart_adjs as $cart_adjustment ) {
                $cart_adjustment->multiply_amounts( $rate );
            }
            $rule->setCartAdjustments( $cart_adjs );
        }

        if( $rule->getConditions() ) {
            $cart_conditions = $rule->getConditions();
            foreach ( $cart_conditions as $cart_condition ) {
                $cart_condition->multiplyAmounts( $rate );
            }
            $rule->setConditions( $cart_conditions );
        }

	    if ( $rule instanceof SingleItemRule || $rule instanceof PackageRule ) {
		    $rule->setItemGiftSubtotalDivider( $rule->getItemGiftSubtotalDivider() * $rate );
	    }

        return $rule;
    }

	/**
	 * @param SingleItemRule|PackageRule|NoItemRule $rule
	 *
	 * @return NoItemRule|PackageRule|SingleItemRule
	 * @return NoItemRule|PackageRule|SingleItemRule
	 */
    public static function translate( $rule, $language_code ) {
		$filter_translator = new FilterTranslator();

		if ( $rule instanceof SingleItemRule ) {
            $filters = array();
			foreach( $rule->getFilters() as $filter ) {
				$filter->setValue( $filter_translator->translateByType( $filter->getType(), $filter->getValue(), $language_code ) );
                $filter->setExcludeProductIds( $filter_translator->translateProduct( $filter->getExcludeProductIds(), $language_code ) );
                $filters[] = $filter;
            }
            $rule->setFilters( $filters );
        }
        elseif( $rule instanceof PackageRule ) {
            $packages = array();
            foreach( $rule->getPackages() as $package ) {
                $filters = array();
                foreach( $package->getFilters() as $filter ) {
                    $filter->setValue( $filter_translator->translateByType( $filter->getType(), $filter->getValue(), $language_code ) );
                    $filter->setExcludeProductIds( $filter_translator->translateProduct( $filter->getExcludeProductIds(), $language_code ) );
                    $filters[] = $filter;
                }
                $package->setFilters( $filters );
                $packages[] = $package;
            }
            $rule->setPackages( $packages );
        }

        if( $rule->hasProductRangeAdjustment() ) {
            $product_adj = $rule->getProductRangeAdjustmentHandler();
            $product_adj->setSelectedProductIds( $filter_translator->translateProduct( $product_adj->getSelectedCategoryIds(), $language_code ) );
            $product_adj->setSelectedCategoryIds( $filter_translator->translateCategory( $product_adj->getSelectedCategoryIds(), $language_code ) );
            $rule->installProductRangeAdjustmentHandler( $product_adj );
        }

        $cart_conditions = array();
		foreach ( $rule->getConditions() as $cart_condition ) {
            $cart_condition->translate( $language_code );
            $cart_conditions[] = $cart_condition;
        }
        $rule->setConditions( $cart_conditions );

        return $rule;
	}
}