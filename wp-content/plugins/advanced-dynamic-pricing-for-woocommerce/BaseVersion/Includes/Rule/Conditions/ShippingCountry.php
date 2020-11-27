<?php

namespace ADP\BaseVersion\Includes\Rule\Conditions;

use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\Interfaces\Conditions\ListComparisonCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ShippingCountry extends AbstractCondition implements ListComparisonCondition {
	const IN_LIST = 'in_list';
	const NOT_IN_LIST = 'not_in_list';

	const AVAILABLE_COMP_METHODS = array(
		self::IN_LIST,
		self::NOT_IN_LIST,
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
		$country = $cart->get_context()->getCustomer()->getShippingCountry();

		$comparison_list   = (array) $this->comparison_list;
		$comparison_method = $this->comparison_method;

		return $this->compare_value_with_list( $country, $comparison_list, $comparison_method );
	}

	public static function getType() {
		return 'shipping_country';
	}

	public static function getLabel() {
		return __( 'Country', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public static function getTemplatePath() {
		return WC_ADP_PLUGIN_VIEWS_PATH . 'conditions/shipping/country.php';
	}

	public static function getGroup() {
		return ConditionsLoader::GROUP_SHIPPING;
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