<?php

namespace ADP\BaseVersion\Includes\External\AdminPage\Tabs;

use ADP\BaseVersion\Includes\Admin\Settings;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\AdminPage;
use ADP\BaseVersion\Includes\External\AdminPage\Interfaces\AdminTabInterface;
use ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\External\AdminPage\Paginator;
use ADP\BaseVersion\Includes\External\CacheHelper;
use ADP\BaseVersion\Includes\Rule\CartAdjustmentsLoader;
use ADP\BaseVersion\Includes\Rule\ConditionsLoader;
use ADP\BaseVersion\Includes\Rule\LimitsLoader;
use ADP\BaseVersion\Includes\Admin\Ajax;
use ADP\Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Rules implements AdminTabInterface {
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var Paginator
	 */
	protected $paginator;

	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context   = $context;
		$this->title     = self::get_title();
		$this->paginator = new Paginator();
	}

	public function handle_submit_action() {
		// do nothing
	}

	public static function get_relative_view_path() {
		return 'admin_page/tabs/rules.php';
	}

	public static function get_header_display_priority() {
		return 10;
	}

	public static function get_key() {
		return 'rules';
	}

	public static function get_title() {
		return __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public function get_view_variables() {
		$context = $this->context;

		$conditionsLoader 	= Factory::get( "Rule_ConditionsLoader" );
		$limitsLoader     	= Factory::get( "Rule_LimitsLoader" );
		$cartAdjLoader      = Factory::get( 'Rule_CartAdjustmentsLoader' );

		/**
		 * @var ConditionsLoader   $conditionsLoader
		 * @var LimitsLoader          $limitsLoader
		 * @var CartAdjustmentsLoader  $cartAdjLoader
		 */

		// $conditions_templates = $condition_registry->get_templates_content();
		// $conditions_titles    = $condition_registry->get_titles();

		$conditions_templates = array();
		$conditions_titles = array();
		foreach( $conditionsLoader->getAsList() as $group => $items ) {
			foreach ( $items as $item ) {
				$key          = $item[ $conditionsLoader::LIST_TYPE_KEY ];
				$label        = $item[ $conditionsLoader::LIST_LABEL_KEY ];
				$templatePath = $item[ $conditionsLoader::LIST_TEMPLATE_PATH_KEY ];
				if( $key == 'custom_taxonomy' OR $key == 'amount_custom_taxonomy' ) {
					$taxonomy = $item['taxonomy'];
				}

				ob_start();
				include $templatePath;
				$conditions_templates[ $key ] = ob_get_clean();

				$conditions_titles[ $conditionsLoader->getGroupLabel($group) ][ $key ] = $label;
			}
		}

		$limits_templates = array();
		$limits_titles    = array();
		foreach( $limitsLoader->getAsList() as $group => $items ) {
			foreach ( $items as $item ) {
				$key          = $item[ $limitsLoader::LIST_TYPE_KEY ];
				$label        = $item[ $limitsLoader::LIST_LABEL_KEY ];
				$templatePath = $item[ $limitsLoader::LIST_TEMPLATE_PATH_KEY ];

				ob_start();
				include $templatePath;
				$limits_templates[ $key ] = ob_get_clean();

				$limits_titles[ $limitsLoader->getGroupLabel($group) ][ $key ] = $label;
			}
		}

		$cart_templates = array();
		$cart_titles    = array();
		foreach ( $cartAdjLoader->getAsList() as $group => $items ) {
			foreach ( $items as $item ) {
				$key          = $item[ $cartAdjLoader::LIST_TYPE_KEY ];
				$label        = $item[ $cartAdjLoader::LIST_LABEL_KEY ];
				$templatePath = $item[ $cartAdjLoader::LIST_TEMPLATE_PATH_KEY ];

				ob_start();
				include $templatePath;
				$cart_templates[ $key ] = ob_get_clean();

				$cart_titles[ $cartAdjLoader->getGroupLabel($group) ][ $key ] = $label;
			}
		}

		$options       = $this->context->get_settings();
		$pagination    = $this->get_pagination_html();
		$tab           = self::get_key();
		$page          = AdminPage::SLUG;
		$hide_inactive = $this->get_is_hide_inactive();

		return compact( 'conditions_templates', 'conditions_titles', 'limits_templates', 'limits_titles',
			'cart_templates', 'cart_titles', 'options', 'pagination', 'page', 'hide_inactive', 'tab' );
	}

	public function get_tab_rules() {
		return Database::get_rules( $this->make_get_rules_args() );
	}

	protected function get_pagination_html() {
		$rules_per_page = $this->context->get_option( 'rules_per_page' );

		$rules_count = Database::get_rules_count( $this->make_get_rules_args() );
		$total_pages = (int) ceil( $rules_count / $rules_per_page );

		$this->paginator->set_total_items( $rules_count );
		$this->paginator->set_total_pages( $total_pages );

		return $this->paginator->make_html();
	}

	protected function get_is_hide_inactive() {
		return ! empty( $_GET['hide_inactive'] );
	}

	protected function make_get_rules_args() {
		$args = array();

		if ( ! empty( $_GET['product'] ) ) {
			$args['product'] = (int) $_GET['product'];

			return $args;
		}

		if ( ! empty( $_GET['rule_id'] ) ) {
			$args = array( 'id' => (int) $_GET['rule_id'] );

			return $args;
		}

		if ( $this->get_is_hide_inactive() ) {
			$args['active_only'] = true;
		}

		$page = call_user_func( array( $this->paginator, 'get_page_num' ) );
		if ( $page < 1 ) {
			return array();
		}

		$rules_per_page = $this->context->get_option( 'rules_per_page' );
		$args['limit']  = array( ( $page - 1 ) * $rules_per_page, $rules_per_page );

		$args['exclusive'] = 0;

		return $args;
	}

	public function enqueue_scripts() {
		$baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";

		wp_enqueue_script( 'wdp_settings-scripts', $baseVersionUrl . 'assets/js/rules.js', array(
			'jquery',
			'jquery-ui-sortable',
			'wdp_select2',
		), WC_ADP_VERSION );

		$rules = $this->get_tab_rules();
		$paged = $this->paginator->get_page_num();

		$preloaded_lists = array(
			'payment_methods'   => Helpers::get_payment_methods(),
			'shipping_methods'  => Helpers::get_shipping_methods(),
			'shipping_class'    => Helpers::get_shipping_classes(),
			'countries'         => Helpers::get_countries(),
			'states'            => Helpers::get_states(),
			'user_roles'        => Helpers::get_user_roles(),
			'user_capabilities' => Helpers::get_user_capabilities(),
			'weekdays'          => Helpers::get_weekdays(),
		);

		foreach ( $preloaded_lists as $list_key => &$list ) {
			$list = apply_filters( 'wdp_preloaded_list_' . $list_key, $list );
		}

		$context = $this->context;

		$wdp_data = array(
			'page'          => self::get_key(),
			'rules'         => $rules,
			'titles'        => Settings::get_filter_titles( Settings::get_ids_for_filter_titles( $rules ) ),
			'labels'        => array(
				'select2_no_results'     => _x( 'no results', 'select2 msg when results wasn\'t found', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_input_too_short' => _x( 'Please enter %d or more characters', 'select2 msg when input is too short', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_input_too_long'	=> _x( 'Please delete %d character', 'select2 msg when input is too long', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_error_loading' => _x( 'The results could not be loaded', 'select2 msg when it get error while loading', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_loading_more' => _x( 'Loading more results…', 'select2 msg when loading more', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_maximum_selected' => _x( 'You can only select %d item', 'select2 msg when max items selected', 'advanced-dynamic-pricing-for-woocommerce' ),
				'select2_searching' => _x( 'Searching…', 'select2 msg when searching', 'advanced-dynamic-pricing-for-woocommerce' ),
				'confirm_remove_rule'    => __( 'Remove rule?', 'advanced-dynamic-pricing-for-woocommerce' ),
				'currency_symbol'        => get_woocommerce_currency_symbol(),
				'fixed_discount'         => __( 'Fixed discount for item', 'advanced-dynamic-pricing-for-woocommerce' ),
				'fixed_price'            => __( 'Fixed price for item', 'advanced-dynamic-pricing-for-woocommerce' ),
				'fixed_discount_for_set' => __( 'Fixed discount for set', 'advanced-dynamic-pricing-for-woocommerce' ),
				'fixed_price_for_set'    => __( 'Fixed price for set', 'advanced-dynamic-pricing-for-woocommerce' ),
			),
			'lists'         => $preloaded_lists,
			'selected_rule' => isset( $_GET['rule_id'] ) ? (int) $_GET['rule_id'] : - 1,
			'product'       => isset( $_GET['product'] ) ? (int) $_GET['product'] : - 1,
			'product_title' => isset ( $_GET['product'] ) ? CacheHelper::getWcProduct( $_GET['product'] )->get_title() : - 1,
			'action_rules'  => isset( $_GET['action_rules'] ) ? $_GET['action_rules'] : - 1,
			'bulk_rule'     => self::getAllAvailableTypes(),
			'options'       => array(
				'enable_product_exclude' => $context->get_option( 'allow_to_exclude_products' ),
				'rules_per_page'         => $context->get_option( 'rules_per_page' ),
			),
			'paged'         => $paged,
			'security' => wp_create_nonce( Ajax::SECURITY_ACTION ),
            'security_query_arg' => Ajax::SECURITY_QUERY_ARG,
		);
		wp_localize_script( 'wdp_settings-scripts', 'wdp_data', $wdp_data );
	}

	public function register_ajax() {

	}

	protected static function getAllAvailableTypes() {
		return array(
			'bulk' => array(
				'all' => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __( 'Qty based on all matched products', 'advanced-dynamic-pricing-for-woocommerce' ),
				),
				'total_qty_in_cart'           => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on all items in the cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'product_categories'          => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on product categories in all cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'product_selected_categories' => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on selected categories in all cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'selected_products'           => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on selected products in all cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'sets'                        => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::set_discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on sets', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'product'                     => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on product', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'variation'                   => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on variation', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'cart_position'               => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on cart position', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'meta_data'                => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on meta data', 'advanced-dynamic-pricing-for-woocommerce'),
				),
			),
			'tier' =>  array(
				'all' => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __( 'Qty based on all matched products', 'advanced-dynamic-pricing-for-woocommerce' ),
				),
				'product_selected_categories' => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on selected categories in all cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'selected_products'           => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on selected products in all cart', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'sets'                        => array(
					'items' => self::format_output( array(
						self::set_discount_amount(),
						self::discount_percentage(),
						self::set_price_fixed(),
					) ),
					'label' => __('Qty based on sets', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'product'                     => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on product', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'variation'                   => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on variation', 'advanced-dynamic-pricing-for-woocommerce'),
				),
				'cart_position'               => array(
					'items' => self::format_output( array(
						self::discount_amount(),
						self::discount_percentage(),
						self::price_fixed(),
					) ),
					'label' => __('Qty based on cart position', 'advanced-dynamic-pricing-for-woocommerce'),
				),
			),
		);
	}

	private static function discount_amount() {
		return array(
			'key'   => 'discount__amount',
			'label' => __( 'Fixed discount for item', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function set_discount_amount() {
		return array(
			'key'   => 'set_discount__amount',
			'label' => __( 'Fixed discount for set', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function discount_percentage() {
		return array(
			'key'   => 'discount__percentage',
			'label' => __( 'Percentage discount', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function price_fixed() {
		return array(
			'key'   => 'price__fixed',
			'label' => __( 'Fixed price for item', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function set_price_fixed() {
		return array(
			'key'   => 'set_price__fixed',
			'label' => __( 'Fixed price for set', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function format_output( $types ) {
		return array_combine( array_column( $types, 'key' ), array_column( $types, 'label' ) );
	}
}
