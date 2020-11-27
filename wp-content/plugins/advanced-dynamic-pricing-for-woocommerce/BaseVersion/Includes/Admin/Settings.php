<?php

namespace ADP\BaseVersion\Includes\Admin;

use ADP\BaseVersion\Includes\Common\Database;
use ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\CacheHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Settings {
	static $activation_notice_option = 'advanced-dynamic-pricing-for-woocommerce-activation-notice-shown';
	public static $disabled_rules_option_name = 'wdp_rules_disabled_notify';

	/**
	 * @var Context
	 */
	protected $context;

	public function __construct( $context ) {
		$this->context = $context;

		add_filter( 'woocommerce_hidden_order_itemmeta', function ( $keys ) {
			$keys[] = '_wdp_initial_cost';
			$keys[] = '_wdp_initial_tax';
//			$keys[] = '_wdp_rules'; // duplicate
			$keys[] = '_wdp_free_shipping';

			return $keys;
		}, 10, 1 );

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'wdp_settings' ) {
			if ( isset( $_GET['from_notify'] ) ) {
				update_option( self::$disabled_rules_option_name, array() );
			}
		}

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

		add_action( 'woocommerce_admin_order_preview_end', array( $this, 'print_applied_discounts_order_preview' ) );
		add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'add_applied_discounts_data' ),
			10, 2 );

		//do once
		if ( ! get_option( self::$activation_notice_option ) ) {
			add_action( 'admin_notices', array( $this, 'display_plugin_activated_message' ) );
        }

        if ( $this->context->get_option('hide_coupon_word_in_totals') ) {
            /**
             * Same hook is added in Frontend::__construct().
             * In this case hook is fired during ajax requests.
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
             * Code is copied from Frontend to work with wc_ajax
             * @var WC_Coupon $coupon
             */
            if ( $coupon->get_virtual() && $coupon->get_meta( 'adp', true ) ) {
                $coupon_html = preg_replace('#<a(.*?)class="woocommerce-remove-coupon"(.*?)</a>#', '', $coupon_html);
            }
            return $coupon_html;
        }, 10, 3 );

		add_action( 'admin_notices', array( $this, 'notify_rule_disabled' ), 10 );

//		add_action( 'admin_notices', array ($this, 'notify_coupons_disabled'), 10 );

		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'edit_rules_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'edit_rules_panel' ) );
	}

	public function edit_rules_tab() {
		?>
        <li class="edit_rules_tab"><a href="#edit_rules_data"><span><?php _e( 'Pricing rules', 'advanced-dynamic-pricing-for-woocommerce' ); ?></span></a></li><?php
	}

	public function edit_rules_panel() {
		global $post, $thepostid, $product_object;

		/** Some boys like to purge global variables. We will not allow ourselves to be beaten. */
		if ( $product_object instanceof \WC_Product ) {
			$product = CacheHelper::getWcProduct( $product_object );
		} elseif ( is_numeric( $thepostid ) ) {
			$product = CacheHelper::getWcProduct( $thepostid );
		} elseif ( $post instanceof \WP_Post ) {
			$product = CacheHelper::getWcProduct( $post );
		} else {
			$product = null;
		}

		if( ! $product ) {
			?>
			<div id="edit_rules_data" class="panel woocommerce_options_panel">
				<h4><?php _e( 'Product wasn\'t returned', 'advanced-dynamic-pricing-for-woocommerce' ); ?></h4>
			</div>
			<?php
			return;
		}

		$list_rules_url = add_query_arg( array(
			'product'      => $product->get_id(),
			'action_rules' => 'list',
		), menu_page_url( 'wdp_settings', false ) );

		$add_rules_url = add_query_arg( array(
			'product'      => $product->get_id(),
			'action_rules' => 'add',
		), menu_page_url( 'wdp_settings', false ) );
		$rules_args    = array( 'product' => $product->get_id(), 'active_only' => true );
		$rules         = Database::get_rules( $rules_args );
		$count_rules   = count( $rules ) != 0 ? count( $rules ) : '';
		?>
        <div id="edit_rules_data" class="panel woocommerce_options_panel">
			<?php if ( count( $rules ) != 0 ): ?>
                <button type="button" class="button" onclick="window.open('<?php echo $list_rules_url ?>')"
                        style="margin: 5px;">
					<?php printf( __( 'View %s rules for the product', 'advanced-dynamic-pricing-for-woocommerce' ),
						$count_rules ); ?></button>
			<?php endif; ?>
            <button type="button" class="button" onclick="window.open('<?php echo $add_rules_url ?>')"
                    style="margin: 5px;">
				<?php _e( 'Add rule for the product', 'advanced-dynamic-pricing-for-woocommerce' ); ?></button>
        </div>
		<?php
	}

	public static function print_applied_discounts_order_preview() {
		PreviewOrderAppliedDiscountRules::render();
	}

	public static function add_applied_discounts_data( $export_data, $order ) {
		$export_data = PreviewOrderAppliedDiscountRules::add_data( $export_data, $order );

		return $export_data;
	}

	public function display_plugin_activated_message() {
		?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Advanced Dynamic Pricing for WooCommerce is available <a href="admin.php?page=wdp_settings">on this page</a>.', 'advanced-dynamic-pricing-for-woocommerce' ); ?></p>
        </div>
		<?php
		update_option( self::$activation_notice_option, true );
	}

	public function add_meta_boxes() {
		MetaBoxOrderAppliedDiscountRules::init();
	}

	private function get_current_tab() {
		return isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : $this->get_default_tab();
	}

	private function get_default_tab() {
		return 'rules';
	}

	public static function get_ids_for_filter_titles( $rules ) {
		// make array of filters splitted by type
		$filters_by_type = array(
			'products'              => array(),
			'giftable_products'     => array(),
			'product_tags'          => array(),
			'product_categories'    => array(),
			'product_category_slug' => array(),
			'product_attributes'    => array(),
			'product_sku'           => array(),
			'product_sellers'		=> array(),
			'product_custom_fields' => array(),
			'users_list'            => array(),
			'coupons'               => array(),
			'subscriptions'         => array(),
		);
		foreach ( array_keys( Helpers::get_custom_product_taxonomies() ) as $tax_name ) {
			$filters_by_type[ $tax_name ] = array();
		}
		$filters_by_type = apply_filters( 'wdp_ids_for_filter_titles', $filters_by_type, $rules );
		foreach ( $rules as $rule ) {
			foreach ( $rule['filters'] as $filter ) {
				if ( ! empty( $filter['value'] ) ) {
					$type  = $filter['type'];
					$value = $filter['value'];

					if ( isset( $filters_by_type[ $type ] ) ) {
						$filters_by_type[ $type ] = array_merge( $filters_by_type[ $type ], (array) $value );
					}
				}

				if ( isset( $filter['product_exclude']['values'] ) ) {
					foreach ( $filter['product_exclude']['values'] as $product_id ) {
						$filters_by_type['products'][] = $product_id;
					}
				}
			}

			if ( isset( $rule['get_products']['value'] ) ) {
				foreach ( $rule['get_products']['value'] as $filter ) {
					if ( ! isset( $filter['value'] ) ) {
						continue;
					}
					$type  = $filter['type'];
					$value = $filter['value'];

					$filters_by_type[ $type ] = array_merge( $filters_by_type[ $type ], (array) $value );
				}
			}

			if ( isset( $rule['bulk_adjustments']['selected_categories'] ) ) {
				$filters_by_type['product_categories'] = array_merge( $filters_by_type['product_categories'],
					(array) $rule['bulk_adjustments']['selected_categories'] );
			}

			if ( isset( $rule['bulk_adjustments']['selected_products'] ) ) {
				$filters_by_type['products'] = array_merge( $filters_by_type['products'],
					(array) $rule['bulk_adjustments']['selected_products'] );
			}

			if ( isset( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as $condition ) {
					if ( $condition['type'] === 'specific' && isset( $condition['options'][2] ) ) {
						$value                         = $condition['options'][2];
						$filters_by_type['users_list'] = array_merge( $filters_by_type['users_list'], (array) $value );
					} elseif ( $condition['type'] === 'product_attributes' && isset( $condition['options'][2] ) ) {
						$value                                 = $condition['options'][2];
						$filters_by_type['product_attributes'] = array_merge( $filters_by_type['product_attributes'],
							(array) $value );
					} elseif ( $condition['type'] === 'product_custom_fields' && isset( $condition['options'][2] ) ) {
						$value                                    = $condition['options'][2];
						$filters_by_type['product_custom_fields'] = array_merge( $filters_by_type['product_custom_fields'],
							(array) $value );
					} elseif ( $condition['type'] === 'product_categories' && isset( $condition['options'][2] ) ) {
						$value                                 = $condition['options'][2];
						$filters_by_type['product_categories'] = array_merge( $filters_by_type['product_categories'],
							(array) $value );
					} elseif ( $condition['type'] === 'product_category_slug' && isset( $condition['options'][2] ) ) {
						$value                                    = $condition['options'][2];
						$filters_by_type['product_category_slug'] = array_merge( $filters_by_type['product_category_slug'],
							(array) $value );
					} elseif ( $condition['type'] === 'product_tags' && isset( $condition['options'][2] ) ) {
						$value                           = $condition['options'][2];
						$filters_by_type['product_tags'] = array_merge( $filters_by_type['product_tags'],
							(array) $value );
					} elseif ( $condition['type'] === 'products' && isset( $condition['options'][2] ) ) {
						$value                       = $condition['options'][2];
						$filters_by_type['products'] = array_merge( $filters_by_type['products'], (array) $value );
					} elseif ( $condition['type'] === 'cart_coupons' && isset( $condition['options'][1] ) ) {
						$value                      = $condition['options'][1];
						$filters_by_type['coupons'] = array_merge( $filters_by_type['coupons'], (array) $value );
					} elseif ( $condition['type'] === 'subscriptions' && isset( $condition['options'][1] ) ) {
						$value                            = $condition['options'][1];
						$filters_by_type['subscriptions'] = array_merge( $filters_by_type['subscriptions'],
							(array) $value );
					} elseif ( in_array( $condition['type'],
							array_keys( Helpers::get_custom_product_taxonomies() ) ) && isset( $condition['options'][2] ) ) {
						$value                                 = $condition['options'][2];
						$filters_by_type[ $condition['type'] ] = array_merge( $filters_by_type[ $condition['type'] ],
							(array) $value );
					}
				}

			}

		}

		return $filters_by_type;
	}


	/**
	 * Retrieve from get_ids_for_filter_titles function filters all products, tags, categories, attributes and return titles
	 *
	 * @param array $filters_by_type
	 *
	 * @return array
	 */
	public static function get_filter_titles( $filters_by_type ) {
		$result = array();

		// type 'products'
		$result['products'] = array();
		foreach ( $filters_by_type['products'] as $id ) {
			$result['products'][ $id ] = '#' . $id . ' ' . Helpers::get_product_title( $id );
		}

		if ( isset( $_GET['product'] ) ) {
			$id                        = $_GET['product'];
			$result['products'][ $id ] = '#' . $id . ' ' . Helpers::get_product_title( $id );
		}

		// type 'giftable_products'
		$result['giftable_products'] = array();
		foreach ( $filters_by_type['giftable_products'] as $id ) {
			$result['giftable_products'][ $id ] = '#' . $id . ' ' . Helpers::get_product_title( $id );
		}

		$result['product_sku'] = array();
		foreach ( $filters_by_type['product_sku'] as $sku ) {
			$result['product_sku'][ $sku ] = 'SKU: ' . $sku;
		}

		$result['product_sellers'] = array();
		foreach( $filters_by_type['product_sellers'] as $id ) {
			$users = Helpers::get_users( array ( $id ) );
			$result['product_sellers'][ $id ] = $users[0]['text'];
		}

		// type 'product_tags'
		$result['product_tags'] = array();
		foreach ( $filters_by_type['product_tags'] as $id ) {
			$result['product_tags'][ $id ] = Helpers::get_tag_title( $id );
		}

		// type 'product_categories'
		$result['product_categories'] = array();
		foreach ( $filters_by_type['product_categories'] as $id ) {
			$result['product_categories'][ $id ] = Helpers::get_category_title( $id );
		}

		// type 'product_category_slug'
		$result['product_category_slug'] = array();
		foreach ( $filters_by_type['product_category_slug'] as $slug ) {
			$result['product_category_slug'][ $slug ] = __( 'Slug', 'advanced-dynamic-pricing-for-woocommerce' ) . ': ' . $slug;
		}

		// product_taxonomies
		foreach ( Helpers::get_custom_product_taxonomies() as $tax ) {
			$result[ $tax->name ] = array();
			foreach ( $filters_by_type[ $tax->name ] as $id ) {
				$result[ $tax->name ][ $id ] = Helpers::get_product_taxonomy_term_title( $id, $tax->name );
			}
		}

		// type 'product_attributes'
		$attributes                   = Helpers::get_product_attributes( array_unique( $filters_by_type['product_attributes'] ) );
		$result['product_attributes'] = array();
		foreach ( $attributes as $attribute ) {
			$result['product_attributes'][ $attribute['id'] ] = $attribute['text'];
		}

		// type 'product_custom_fields'
		$customfields                    = array_unique( $filters_by_type['product_custom_fields'] ); // use as is!
		$result['product_custom_fields'] = array();
		foreach ( $customfields as $customfield ) {
			$result['product_custom_fields'][ $customfield ] = $customfield;
		}

		// type 'users_list'
		$attributes           = Helpers::get_users( $filters_by_type['users_list'] );
		$result['users_list'] = array();
		foreach ( $attributes as $attribute ) {
			$result['users_list'][ $attribute['id'] ] = $attribute['text'];
		}

		// type 'cart_coupons'
		$result['coupons'] = array();
		foreach ( array_unique( $filters_by_type['coupons'] ) as $code ) {
			$result['coupons'][ $code ] = $code;
		}

		// type 'subscriptions'
		$result['subscriptions'] = array();
		foreach ( $filters_by_type['subscriptions'] as $id ) {
			$result['subscriptions'][ $id ] = '#' . $id . ' ' . Helpers::get_product_title( $id );
		}

		return apply_filters( 'wdp_filter_titles', $result, $filters_by_type );
	}

	public function notify_rule_disabled() {
		$disabled_rules = get_option( self::$disabled_rules_option_name, array() );

		if ( $disabled_rules ) {
			$disabled_count_common    = 0;
			$disabled_count_exclusive = 0;
			foreach ( $disabled_rules as $rule ) {
				$is_exclusive = $rule['is_exclusive'];

				if ( $is_exclusive ) {
					$disabled_count_exclusive ++;
				} else {
					$disabled_count_common ++;
				}
			}

			$rule_edit_url = add_query_arg( array(
				'page'        => 'wdp_settings',
				'from_notify' => '1'
			), admin_url( 'admin.php' ) );
			$rule_edit_url = add_query_arg( 'tab', 'rules', $rule_edit_url );

			$format = "<p>%s %s <a href='%s'>%s</a></p>";

			if ( $disabled_count_common ) {
				$notice_message = "";
				$notice_message .= '<div class="notice notice-success is-dismissible">';
				if ( 1 === $disabled_count_common ) {
					$notice_message .= sprintf( $format, "",
						__( "The common rule was turned off, it was running too slow.",
							'advanced-dynamic-pricing-for-woocommerce' ), $rule_edit_url,
						__( "Edit rule", 'advanced-dynamic-pricing-for-woocommerce' ) );
				} else {
					$notice_message .= sprintf( $format, $disabled_count_common,
						__( "common rules were turned off, it were running too slow.",
							'advanced-dynamic-pricing-for-woocommerce' ), $rule_edit_url,
						__( "Edit rule", 'advanced-dynamic-pricing-for-woocommerce' ) );
				}

				$notice_message .= '</div>';

				echo $notice_message;
			}

			if ( $disabled_count_exclusive ) {
				$notice_message = "";
				$notice_message .= '<div class="notice notice-success is-dismissible">';
				if ( 1 === $disabled_count_exclusive ) {
					$notice_message .= sprintf( $format, "",
						__( "The exclusive rule was turned off, it was running too slow.",
							'advanced-dynamic-pricing-for-woocommerce' ), $rule_edit_url,
						__( "Edit rule", 'advanced-dynamic-pricing-for-woocommerce' ) );
				} else {
					$notice_message .= sprintf( $format, $disabled_count_exclusive,
						__( "exclusive rules were turned off, it were running too slow.",
							'advanced-dynamic-pricing-for-woocommerce' ), $rule_edit_url,
						__( "Edit rule", 'advanced-dynamic-pricing-for-woocommerce' ) );
				}
				$notice_message .= '</div>';

				echo $notice_message;
			}
		}
	}

	public function notify_coupons_disabled() {
		if( !$this->context->is_woocommerce_coupons_enabled() ) {
			$notice_message = "";
			$notice_message .= '<div class="notice notice-warning is-dismissible"><p>';
			$notice_message .= __( "Please enable coupons (cart adjustments won't work)", 'advanced-dynamic-pricing-for-woocommerce' );
			$notice_message .= '</p></div>';
			echo $notice_message;
		}
	}
}
