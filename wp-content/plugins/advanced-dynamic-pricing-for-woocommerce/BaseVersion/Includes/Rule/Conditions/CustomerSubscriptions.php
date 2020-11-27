<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\External\CacheHelper;
use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\ListComparisonCondition;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CustomerSubscriptions extends AbstractCondition implements ListComparisonCondition {
	const AT_LEAST_ONE = 'at_least_one';
	const ALL = 'all';
	const ONLY = 'only';
	const NONE = 'none';

	const AVAILABLE_COMP_METHODS = array(
		self::AT_LEAST_ONE,
		self::ALL,
		self::ONLY,
		self::NONE,
	);

	/**
	 * @var array
	 */
	protected $comparison_list;
	/**
	 * @var string
	 */
	protected $comparison_method;

	public function check( $cart ) {
		$comparison_method = $this->comparison_method;
		$comparison_list   = empty( $this->comparison_list ) ? array() : $this->comparison_list;

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) OR ! is_user_logged_in() ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions();

		$product_ids = array();
		foreach ( $subscriptions as $subscription_key => $subscription ) {
			if ( $subscription->has_status( 'active' ) ) {
				foreach ( $subscription->get_items() as $item_key => $item ) {
					/**
					 * @var $item WC_Order_Item_Product
					 */
					$product_id = $item->get_product_id();
					$product    = CacheHelper::getWcProduct( $product_id );
					if ( $product->is_type( array(
						'subscription',
						'subscription_variation',
						'variable-subscription'
					) ) ) {
						$product_ids[] = $product_id;
					}
				}
			}
		}
		$product_ids = array_unique( $product_ids );

		return $this->compare_lists( $product_ids, $comparison_list, $comparison_method );
	}

	public static function getType() {
		return 'subscriptions';
	}

	public static function getLabel() {
		return __( 'Active subscriptions', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/customer/subscriptions.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_CUSTOMER;
	}

	/**
	 * @param array $comparison_list
	 */
	public function setComparisonList( $comparison_list ) {
		gettype($comparison_list) === 'array' ? $this->comparison_list = $comparison_list : $this->comparison_list = null;
	}

	/**
	 * @param string $comparison_method
	 */
	public function setListComparisonMethod( $comparison_method ) {
		in_array($comparison_method, self::AVAILABLE_COMP_METHODS) ? $this->comparison_method = $comparison_method : $this->comparison_method = null;
	}

	public function getComparisonList()
	{
		return $this->comparison_list;
	}

	public function getListComparisonMethod() {
		return $this->comparison_method;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return !is_null( $this->comparison_method ) AND !is_null( $this->comparison_list );
	}
}