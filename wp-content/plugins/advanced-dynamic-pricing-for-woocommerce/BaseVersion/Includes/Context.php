<?php

namespace ADP\BaseVersion\Includes;

use ADP\BaseVersion\Includes\External\AdminPage\AdminPage;
use ADP\BaseVersion\Includes\External\Cmp\WoocsCmp;
use ADP\Factory;
use ADP\Settings\OptionsManager;
use Exception;
use WC_Tax;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Context {
	const CUSTOMIZER = 'customizer';
	const ADMIN = 'admin';
	const AJAX = 'ajax';
	const REST_API = 'rest_api';
	const WP_CRON = 'wp_cron';
	const PHPUNIT = 'phpunit';

	/**
	 * Props which can be accessed anyway
	 *
	 * @var callable[]
	 */
	protected $firstBornPropsCallbacks = array();

	const PRODUCT_LOOP = 'product_loop';
	const SHOP_LOOP = 'shop_loop';
	const WC_PRODUCT_PAGE = 'wc_product_page';
	const WC_CATEGORY_PAGE = 'wc_category_page';
	const WC_CART_PAGE = 'wc_cart_page';
	const WC_CHECKOUT_PAGE = 'wc_checkout_page';
	const WC_SHOP_PAGE = 'wc_shop_page';

	const ADP_PLUGIN_PAGE = 'adp_admin_plugin_page';

	/**
	 * Props which can be accessed only after parsing the main WordPress query, so
	 * in __construct we should wait until it happens (if needed ofc)
	 *
	 * @var callable[]
	 */
	protected $queryPropsCallbacks = array();
	protected $adminQueryPropsCallbacks = array();

	const MODE_DEBUG = 'debug';
	const MODE_PRODUCTION = 'prod';

	/**
	 * @var string
	 */
	protected $mode;

	/**
	 * @var OptionsManager
	 */
	protected $settings;

	/**
	 * @var array
	 */
	protected $props = array();

	/**
	 * @var array
	 */
	protected $changed_props = array();

	/**
	 * @var WP_User
	 */
	protected $current_user;

	/**
	 * @var bool
	 */
	protected $user_logged_in;

	protected $availableTaxClassSlugs = array();

	/**
	 * @var bool
	 */
	protected $wcLoaded;

	/**
	 * @var PriceSettings
	 */
	public $priceSettings;

	/**
	 * @var CurrencyController
	 */
	public $currencyController;

	/**
	 * @param OptionsManager|null $settings
	 */
	public function __construct( $settings = null ) {
		if ( isset( $settings ) && $settings instanceof OptionsManager ) {
			$this->settings = $settings;
		} else {
			$optionsManager = Factory::callStaticMethod( "OptionsInstaller", "install" );
			/** @var $optionsManager OptionsManager */
			$this->settings = $optionsManager;
		}

		$this->firstBornPropsCallbacks = array(
			self::ADMIN      => 'is_admin',
			self::CUSTOMIZER => 'is_customize_preview',
			self::AJAX       => 'wp_doing_ajax',
			self::REST_API   => array( $this, 'is_request_to_rest_api' ),
			self::WP_CRON    => 'wp_doing_cron',
			self::PHPUNIT    => array( $this, 'isDoingPhpUnit' ),
		);

		$this->queryPropsCallbacks = array(
			self::PRODUCT_LOOP     => array( $this, 'is_woocommerce_product_loop' ),
			self::SHOP_LOOP        => array( $this, 'is_woocommerce_shop_loop' ),
			self::WC_PRODUCT_PAGE  => 'is_product',
			self::WC_CATEGORY_PAGE => 'is_product_category',
			self::WC_CART_PAGE     => 'is_cart',
			self::WC_CHECKOUT_PAGE => 'is_checkout',
			self::WC_SHOP_PAGE     => 'is_shop',
		);

		$this->adminQueryPropsCallbacks = array(
			self::ADP_PLUGIN_PAGE => array( $this, 'is_adp_admin_page' ),
		);

		foreach ( $this->firstBornPropsCallbacks as $prop => $callback ) {
			$this->props[ $prop ] = $callback();
		}

		if ( did_action( 'wp' ) ) {
			$this->fetch_query_props();
		} else {
			add_action( 'wp', array( $this, 'fetch_query_props' ), 10, 0 );
		}

		if ( did_action( 'admin_init' ) ) {
			$this->fetch_admin_query_props();
		} else {
			add_action( 'admin_init', array( $this, 'fetch_admin_query_props' ), 10, 0 );
		}

		$this->user_logged_in         = is_user_logged_in();
		$this->current_user           = wp_get_current_user();
		$this->availableTaxClassSlugs = array( 'standard' );
		$this->wcLoaded               = class_exists( "WooCommerce" );

		if ( $this->wcLoaded && class_exists( "WC_Tax" ) ) {
			$this->availableTaxClassSlugs = array_merge( $this->availableTaxClassSlugs, WC_Tax::get_tax_class_slugs() );
		}

		$this->setUpPricesSettings();

		/** --- Currency ---  */
		$currencyCode = get_option( 'woocommerce_currency' );
		$symbols      = CurrencyController::getDefaultCurrencySymbols();

		$symbol                   = isset( $symbols[ $currencyCode ] ) ? $symbols[ $currencyCode ] : '';
		$this->currencyController = new CurrencyController( $this, new Currency( $currencyCode, $symbol, 1 ) );
		$this->currencyController->setCurrencySymbols( $symbols );

		$woocsCmp = new WoocsCmp( $this );
		if ( $woocsCmp->isActive() ) {
			$woocsCmp->modifyContext();
			$woocsCmp->prepareHooks();
		}
		/** --- End Currency ---  */
	}

	public function fetch_query_props() {
		foreach ( $this->queryPropsCallbacks as $prop => $callback ) {
			$this->props[ $prop ] = $callback();
		}
	}

	public function fetch_admin_query_props() {
		foreach ( $this->adminQueryPropsCallbacks as $prop => $callback ) {
			$this->props[ $prop ] = $callback();
		}
	}

	protected static function is_woocommerce_product_loop() {
		global $wp_query;

		return ( $wp_query->current_post + 1 < $wp_query->post_count ) || 'products' !== woocommerce_get_loop_display_mode();
	}

	protected static function is_woocommerce_shop_loop() {
		return ! empty( $GLOBALS['woocommerce_loop']['name'] );
	}

	protected static function is_adp_admin_page() {
		global $plugin_page;

		return $plugin_page === AdminPage::SLUG;
	}

	protected static function is_request_to_rest_api() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$wordpress   = ( false !== strpos( $request_uri, $rest_prefix ) );

		return $wordpress;
	}

	protected static function isDoingPhpUnit() {
		return defined( "PHPUNIT_COMPOSER_INSTALL" );
	}

	/**
	 * @param $new_props array
	 *
	 * @return $this
	 */
	public function set_props( $new_props ) {
		foreach ( $new_props as $key => $value ) {
			$this->changed_props[ $key ] = $value;
		}

		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_option( $key, $default = false ) {
		return $this->settings->getOption( $key );
	}

	public function get_settings() {
		return $this->settings;
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_prop( $key, $default = false ) {
		$value = $default;

		if ( isset( $this->props[ $key ] ) ) {
			$value = $this->props[ $key ];
		}

		if ( isset( $this->changed_props[ $key ] ) ) {
			$value = $this->changed_props[ $key ];
		}

		return $value;
	}

	public function is( $prop ) {
		return $this->get_prop( $prop, null );
	}

	public function is_catalog() {
		return ! $this->get_prop( self::WC_PRODUCT_PAGE ) || $this->get_prop( self::SHOP_LOOP );
	}

	public function is_plugin_admin_page() {
		return $this->get_prop( self::ADMIN ) && isset( $_GET['page'] ) && $_GET['page'] === AdminPage::SLUG;
	}

	public function is_woocommerce_coupons_enabled() {
		return wc_coupons_enabled();
	}

	/**
	 * @return WP_User
	 */
	public function get_current_user() {
		return $this->current_user;
	}

	/**
	 * @param WP_User $user
	 */
	public function set_current_user( $user ) {
		if ( $user instanceof WP_User ) {
			$this->current_user = $user;
		}
	}

	public function get_is_tax_enabled() {
		return wc_tax_enabled();
	}

	public function get_is_prices_include_tax() {
		return wc_prices_include_tax();
	}

	public function get_tax_display_shop_mode() {
		return get_option( 'woocommerce_tax_display_shop' );
	}

	public function get_tax_display_cart_mode() {
		return get_option( 'woocommerce_tax_display_cart' );
	}

	public function getPriceDecimals() {
		return $this->priceSettings->getDecimals();
	}

	public function getCurrencyCode() {
		return $this->currencyController->getCurrentCurrency()->getCode();
	}

	public function get_available_tax_class_slugs() {
		return $this->availableTaxClassSlugs;
	}

	public function set_mode( $mode ) {
		if ( self::MODE_PRODUCTION === $mode || self::MODE_DEBUG === $mode ) {
			$this->mode = $mode;
		}
	}

	/**
	 * @param string $mode
	 *
	 * @return bool
	 */
	public function is_mode( $mode ) {
		return $this->mode === $mode;
	}

	/**
	 * @return bool
	 */
	public function is_production_mode() {
		return $this->mode === self::MODE_PRODUCTION;
	}

	/**
	 * @return bool
	 */
	public function is_debug_mode() {
		return $this->mode === self::MODE_DEBUG;
	}

	/**
	 * TODO implement
	 *
	 * @param Exception $exception
	 */
	public function handle_error( $exception ) {
		return;
	}

	/**
	 * @return bool
	 */
	public function isUserLoggedIn() {
		return $this->user_logged_in;
	}

	public function isUsingGlobalPriceSettings() {
		return apply_filters( 'adp_feature_flag_use_global_price_settings', true );
	}

	public function isAllowExactApplicationOfReplacementCoupon() {
		return apply_filters( 'adp_feature_flag_allow_exact_application_of_replacement_coupon', false );
	}

	public function isDisableShippingCalculationDuringProcess() {
		return apply_filters( 'adp_feature_flag_disable_shipping_calculation_during_process', false );
	}

	protected function setUpPricesSettings() {
		$settings = new PriceSettings();

		if ( $this->isUsingGlobalPriceSettings() && $this->wcLoaded ) {
			$settings->setTaxEnabled( wc_tax_enabled() );
			$settings->setIncludeTax( wc_prices_include_tax() );
			$settings->setDecimals( wc_get_price_decimals() );
			$settings->setDecimalSeparator( wc_get_price_decimal_separator() );
			$settings->setThousandSeparator( wc_get_price_thousand_separator() );
			$settings->setPriceFormat( get_woocommerce_price_format() );
		} else {
			$settings->setTaxEnabled( get_option( 'woocommerce_calc_taxes' ) === 'yes' );
			$settings->setIncludeTax( get_option( 'woocommerce_prices_include_tax' ) === 'yes' );
			$settings->setDecimals( get_option( 'woocommerce_price_num_decimals', 2 ) );
			$settings->setDecimalSeparator( stripslashes( get_option( 'woocommerce_price_decimal_sep' ) ) );
			$settings->setThousandSeparator( stripslashes( get_option( 'woocommerce_price_thousand_sep' ) ) );

			switch ( get_option( 'woocommerce_currency_pos' ) ) {
				case 'left':
					$priceFormat = $settings::FORMAT_LEFT;
					break;
				case 'right':
					$priceFormat = $settings::FORMAT_RIGHT;
					break;
				case 'left_space':
					$priceFormat = $settings::FORMAT_LEFT_SPACE;
					break;
				case 'right_space':
					$priceFormat = $settings::FORMAT_RIGHT_SPACE;
					break;
				default:
					$priceFormat = null;
					break;
			}

			$settings->setPriceFormat( $priceFormat );
		}

		if ( ! $this->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			$settings->setDecimals( $settings->getDecimals() + 2 );
		}

		$this->priceSettings = $settings;
	}

}
