<?php

namespace ADP\BaseVersion\Includes;

use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\External\WC\WcCartItemFacade;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Frontend {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;

		add_action( 'wp_print_styles', array( $this, 'load_frontend_assets' ) );

		if ( apply_filters( 'wdp_checkout_update_order_review_process_enabled', true ) ) {
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'woocommerce_checkout_update_order_review' ), 100 );
		}

		add_filter( 'woocommerce_cart_id', array( $this, 'woocommerce_cart_id' ), 10, 5 );
		add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart',
			array( $this, 'woocommerce_add_to_cart_sold_individually_found_in_cart' ), 10, 5 );

		add_filter( 'woocommerce_order_again_cart_item_data', function ( $cart_item, $item, $order ) {
			$load_as_immutable = apply_filters( 'wdp_order_again_cart_item_load_with_order_deals', false, $cart_item,
				$item, $order );

			if ( $load_as_immutable ) {
				$rules = $item->get_meta( '_wdp_rules' );
				if ( ! empty( $rules ) ) {
					$cart_item['wdp_rules']     = $rules;
					$cart_item['wdp_immutable'] = true;
				}
			}

			return $cart_item;
		}, 10, 3 );

		add_action( 'woocommerce_checkout_create_order_line_item_object',
			array( $this, 'save_initial_price_to_order_item' ), 10, 4 );


		if ( $this->context->get_option('hide_coupon_word_in_totals') ) {
            /**
             * Same hook is added by Settings::__construct().
             * In this case hook is fired during http/https requests.
             */
			add_filter( 'woocommerce_cart_totals_coupon_label', function ( $html, $coupon ) {
				/**
				 * @var WC_Coupon $coupon
				 */
				if ( $coupon->get_virtual() && ( $adp_meta = $coupon->get_meta( 'adp', true ) ) ) {
                    if ( ! empty( $adp_meta['parts'] ) && count( $adp_meta['parts'] ) < 2  ) {
                        $adp_coupon = array_pop( $adp_meta['parts'] );
                        $html = $adp_coupon->getLabel();
                    } else {
					$html = $coupon->get_code();
                    }
				}

				return $html;
			}, 5, 2 );
		}

		add_filter( 'woocommerce_cart_totals_coupon_html', function ( $coupon_html, $coupon, $discount_amount_html ) {
			/**
			 * @var WC_Coupon $coupon
			 */
			if ( $coupon->get_virtual() && $coupon->get_meta( 'adp', true ) ) {
				$coupon_html = preg_replace('#<a(.*?)class="woocommerce-remove-coupon"(.*?)</a>#', '', $coupon_html);
			}
			return $coupon_html;
		}, 10, 3 );

		/** Additional css class for free item line */
		add_filter( 'woocommerce_cart_item_class', function ( $str_classes, $cart_item, $cart_item_key ) {
			$classes = explode( ' ', $str_classes );
			if ( ! empty( $cart_item['wdp_gifted'] ) ) {
				$classes[] = 'wdp_free_product';
			}

			if ( ! empty( $cart_item['wdp_rules'] ) && (float) $cart_item['data']->get_price() == 0 ) {
				$classes[] = 'wdp_zero_cost_product';
			}

			return implode( ' ', $classes );
		}, 10, 3 );
	}

	/**
	 * @param $item WC_Order_Item_Product
	 * @param $cart_item_key string
	 * @param $values array
	 * @param $order WC_Order
	 *
	 * @return WC_Order_Item_Product
	 */
	public function save_initial_price_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['wdp_rules'] ) ) {
			$item->add_meta_data( '_wdp_rules', $values['wdp_rules'] );
		}

		return $item;
	}

	public static function wdp_get_template( $template_name, $args = array(), $template_path = '' ) {
		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args );
		}

		$full_template_path = trailingslashit( WC_ADP_PLUGIN_TEMPLATES_PATH );

		if ( $template_path ) {
			$full_template_path .= trailingslashit( $template_path );
		}

		$full_external_template_path = locate_template( array(
			'advanced-dynamic-pricing-for-woocommerce/' . trailingslashit( $template_path ) . $template_name,
			'advanced-dynamic-pricing-for-woocommerce/' . $template_name,
		) );

		if ( $full_external_template_path ) {
			$full_template_path = $full_external_template_path;
		} else {
			$full_template_path .= $template_name;
		}

		ob_start();
		include $full_template_path;
		$template_content = ob_get_clean();

		return $template_content;
	}

	public function load_frontend_assets() {
		$context = $this->context;
		$baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";

		wp_enqueue_style( 'wdp_pricing-table', $baseVersionUrl . 'assets/css/pricing-table.css', array(), WC_ADP_VERSION );
		wp_enqueue_style( 'wdp_deals-table', $baseVersionUrl . 'assets/css/deals-table.css', array(), WC_ADP_VERSION );

		if ( $context->is( $context::WC_PRODUCT_PAGE ) || $context->is( $context::PRODUCT_LOOP ) ) {
			wp_enqueue_script( 'wdp_deals', $baseVersionUrl . 'assets/js/frontend.js', array(), WC_ADP_VERSION );
		}

		if ( Database::is_condition_type_active( array( 'customer_shipping_method' ) ) ) {
			wp_enqueue_script( 'wdp_update_cart', $baseVersionUrl . 'assets/js/update-cart.js', array( 'wc-cart' ), WC_ADP_VERSION );
		}

		$script_data = array(
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'update_price_with_qty' => $context->get_option('update_price_with_qty') && ! $context->get_option('do_not_modify_price_at_product_page'),
			'js_init_trigger'       => apply_filters( 'wdp_bulk_table_js_init_trigger', "" ),
		);

		wp_localize_script( 'wdp_deals', 'script_data', $script_data );
	}

	private $last_variation = array();
	private $last_variation_hash = array();

	/**
	 * The only way to snatch $variation before woocommerce_add_to_cart_sold_individually_found_in_cart()
	 *
	 * @param string $hash
	 * @param int    $product_id
	 * @param int    $variation_id
	 * @param array  $variation
	 * @param array  $cart_item_data
	 *
	 * @return string
	 */
	public function woocommerce_cart_id( $hash, $product_id, $variation_id, $variation, $cart_item_data ) {
		$this->last_variation      = $variation;
		$this->last_variation_hash = $hash;

		return $hash;
	}

	public function woocommerce_add_to_cart_sold_individually_found_in_cart(
		$found,
		$product_id,
		$variation_id,
		$cart_item_data,
		$cart_id
	) {
		// already found in cart
		if ( $found ) {
			return true;
		}

		$variation = array();
		if ( $this->last_variation_hash && $this->last_variation_hash === $cart_id ) {
			$variation = $this->last_variation;
		}

		$wdp_keys           = array(
			'wdp_rules',
			'wdp_gifted',
			'wdp_original_price',
			WcCartItemFacade::KEY_ADP,
		);
		$cart_item_data     = array_filter( $cart_item_data, function ( $key ) use ( $wdp_keys ) {
			return ! in_array( $key, $wdp_keys );
		}, ARRAY_FILTER_USE_KEY );
		$no_pricing_cart_id = WC()->cart->generate_cart_id( $product_id, $variation_id, $variation, $cart_item_data );
		if ( ! $no_pricing_cart_id ) {
			return $found;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $no_pricing_cart_id === $this->calculate_cart_item_hash_without_pricing( $cart_item ) ) {
				return true;
			}
		}

		return $found;
	}

	private function calculate_cart_item_hash_without_pricing( $cart_item_data ) {
		$product_id = isset( $cart_item_data['product_id'] ) ? $cart_item_data['product_id'] : 0;

		if ( ! $product_id ) {
			return false;
		}

		$variation_id = isset( $cart_item_data['variation_id'] ) ? $cart_item_data['variation_id'] : 0;
		$variation    = isset( $cart_item_data['variation'] ) ? $cart_item_data['variation'] : array();

		$wdp_keys = array(
			'wdp_rules',
			'wdp_gifted',
			'wdp_original_price',
		);

		$default_keys = array(
			'key',
			'product_id',
			'variation_id',
			'variation',
			'quantity',
			'data',
			'data_hash',
			'line_tax_data',
			'line_subtotal',
			'line_subtotal_tax',
			'line_total',
			'line_tax',
		);

		$cart_item_data = array_filter( $cart_item_data, function ( $key ) use ( $wdp_keys, $default_keys ) {
			return ! in_array( $key, array_merge( $wdp_keys, $default_keys ) );
		}, ARRAY_FILTER_USE_KEY );

		return WC()->cart->generate_cart_id( $product_id, $variation_id, $variation, $cart_item_data );
	}
}
