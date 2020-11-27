<?php

namespace ADP\BaseVersion\Includes\External\AdminPage\Tabs;

use ADP\BaseVersion\Includes\Common\Helpers;
use ADP\BaseVersion\Includes\Admin\Importer;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\External\AdminPage\Interfaces\AdminTabInterface;
use ADP\Factory;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Tools implements AdminTabInterface {
	const IMPORT_TYPE_OPTIONS = 'options';
	const IMPORT_TYPE_RULES = 'rules';

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var array
	 */
	protected $groups;

	/**
	 * @var array
	 */
	protected $import_data_types;

	public function __construct( $context ) {
		$this->context = $context;
		$this->title   = self::get_title();

		$this->import_data_types = array(
			self::IMPORT_TYPE_OPTIONS => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
			self::IMPORT_TYPE_RULES   => __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	public function handle_submit_action() {
		if ( isset( $_POST['wdp-import'] ) && ! empty( $_POST['wdp-import-data'] ) && ! empty( $_POST['wdp-import-type'] ) ) {
			$data             = json_decode( str_replace( '\\', '', wp_unslash( $_POST['wdp-import-data'] ) ), true );
			$import_data_type = $_POST['wdp-import-type'];
			$this->action_groups( $data, $import_data_type );
			wp_redirect( $_SERVER['HTTP_REFERER'] );
		}
	}

	public function get_view_variables() {
		$this->prepare_export_groups();
		$groups            = $this->groups;
		$import_data_types = $this->import_data_types;

		return compact( 'groups', 'import_data_types' );
	}

	public static function get_relative_view_path() {
		return 'admin_page/tabs/tools.php';
	}

	public static function get_header_display_priority() {
		return 60;
	}

	public static function get_key() {
		return 'tools';
	}

	public static function get_title() {
		return __( 'Tools', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public function enqueue_scripts() {
//		$is_settings_page = isset( $_GET['page'] ) && $_GET['page'] == 'wdp_settings';
//		// Load backend assets conditionally
//		if ( ! $is_settings_page ) {
//			return;
//		}

		$baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";
		wp_enqueue_script( 'wdp-tools', $baseVersionUrl . 'assets/js/tools.js', array(), WC_ADP_VERSION, true );
	}

	protected function action_groups( $data, $import_data_type ) {
		$this->action_options_group( $data, $import_data_type );
		$this->action_rules_group( $data, $import_data_type );
	}

	protected function action_options_group( $data, $import_data_type ) {
		if ( $import_data_type !== self::IMPORT_TYPE_OPTIONS ) {
			return;
		}

		$settings = $this->context->get_settings();

		foreach ( array_keys( $settings->getOptions() ) as $key ) {
			$option = $settings->tryGetOption( $key );

			if ( $option ) {
				if ( isset( $data[ $key ] ) ) {
					$option->set( $data[ $key ] );
				}
			}
		}

		$settings->save();
	}

	protected function prepare_export_groups() {
		$this->prepare_options_group();
		$this->prepare_export_group();
	}

	protected function prepare_options_group() {
		$options = $this->context->get_settings()->getOptions();

		$options_group = array(
			'label' => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
			'data'  => $options,
		);

		$this->groups['options'] = array(
			'label' => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
			'items' => array( 'options' => $options_group ),
		);
	}

	protected function action_rules_group( $data, $import_data_type ) {
		if ( $import_data_type !== self::IMPORT_TYPE_RULES ) {
			return;
		}

		Importer::import_rules( $data, $_POST['wdp-import-data-reset-rules'] );
	}

	protected function prepare_export_group() {
		$export_items = array();

		$exporter = Factory::get("Admin_Exporter", $this->context );
		$rules = $exporter->exportRules();

		foreach ( $rules as &$rule ) {
			unset( $rule['id'] );

			if ( ! empty( $rule['filters'] ) ) {
				foreach ( $rule['filters'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = $this->convert_elements_from_id_to_name( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['get_products']['value'] ) ) {
				foreach ( $rule['get_products']['value'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = $this->convert_elements_from_id_to_name( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as &$item ) {
					foreach ( $item['options'] as &$option_item ) {
						if ( is_array( $option_item ) ) {
							$converted = null;
							try {
								$converted = $this->convert_elements_from_id_to_name( $option_item, $item['type'] );
							} catch ( Exception $e ) {

							}

							if ( $converted ) {
								$option_item = $converted;
							}
						}
					}
				}
				unset( $item );
			}
		}
		unset( $rule );

		$export_items['all'] = array(
			'label' => __( 'All', 'advanced-dynamic-pricing-for-woocommerce' ),
			'data'  => $rules,
		);

		foreach ( $rules as $rule ) {
			$export_items[] = array(
				'label' => "{$rule['title']}",
				'data'  => array( $rule ),
			);
		}

		$this->groups['rules'] = array(
			'label' => __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
			'items' => $export_items
		);
	}

	protected function convert_elements_from_id_to_name( $items, $type ) {
		if ( empty( $items ) ) {
			return $items;
		}
		foreach ( $items as &$value ) {
			if ( 'products' === $type ) {
				$value = Helpers::get_product_name( $value );
			} elseif ( 'product_categories' === $type ) {
				$value = Helpers::get_category_title( $value );
			} elseif ( 'product_tags' === $type ) {
				$value = Helpers::get_tag_title( $value );
			} elseif ( 'product_attributes' === $type ) {
				$value = Helpers::get_attribute_title( $value );
			}
		}

		return $items;
	}

	public function register_ajax(){

	}
}
