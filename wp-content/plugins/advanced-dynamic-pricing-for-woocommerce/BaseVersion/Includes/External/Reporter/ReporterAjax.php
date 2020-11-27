<?php

namespace ADP\BaseVersion\Includes\External\Reporter;

use ADP\BaseVersion\Includes\Reporter\CalculationProfiler;
use ADP\BaseVersion\Includes\Reporter\ReportsStorage;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ReporterAjax {
	const ACTION_GET_USER_REPORT = 'get_user_report_data';
	const ACTION_DOWNLOAD_REPORT = 'download_report';

	const IMPORT_KEY_REQUEST_KEY = 'import_key';

	/**
	 * @var CalculationProfiler
	 */
	protected $profiler;

	/**
	 * @param CalculationProfiler $profiler
	 */
	public function __construct( $profiler ) {
		$this->profiler = $profiler;
	}

	public function register() {
		add_action( 'wp_ajax_' . self::ACTION_GET_USER_REPORT, array( $this, 'get_user_report_data' ) );
		add_action( 'wp_ajax_'  . self::ACTION_DOWNLOAD_REPORT, array( $this, 'handle_download_report' ) );
	}

	public function get_user_report_data() {
		$import_key = isset( $_REQUEST[ self::IMPORT_KEY_REQUEST_KEY ] ) ? $_REQUEST[ self::IMPORT_KEY_REQUEST_KEY ] : false;

//		if ( $import_key === $this->profiler->get_import_key() ) {
			$data = $this->make_response_data( $import_key );
			if ( $data ) {
				wp_send_json_success( $data );
			} else {
				wp_send_json_error( __( 'Import key not found', 'advanced-dynamic-pricing-for-woocommerce' ) );
			}
//		} else {
//			wp_send_json_error( __( 'Wrong import key', 'advanced-dynamic-pricing-for-woocommerce' ) );
//		}
	}

	private function make_response_data( $import_key ) {
		$storage = new ReportsStorage( $import_key );

		$required_keys = array(
			'processed_cart',
			'processed_products',
			'rules_timing',
			'rules'
		);
		$data          = array();

		foreach ( $required_keys as $key ) {
			$data[ $key ] = $storage->getReport( $key );
		}

		$rules_data = array();
		foreach ( $data['rules'] as $rule ) {
			$rules_data[ $rule['id'] ] = array(
				'title'         => $rule['title'],
				'edit_page_url' => $rule['edit_page_url'],
			);
		}
		$data['rules'] = $rules_data;

		return $data;
	}

	public function handle_download_report() {
		$import_key = isset( $_REQUEST[ self::IMPORT_KEY_REQUEST_KEY ] ) ? $_REQUEST[ self::IMPORT_KEY_REQUEST_KEY ] : false;

		if ( ! $import_key ) {
			wp_send_json_error( __( 'Import key not provided', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}

		if ( ! is_super_admin( get_current_user_id() ) ) {
//			if ( $import_key !== $this->profiler->get_import_key() ) {
				wp_send_json_error( __( 'Wrong import key', 'advanced-dynamic-pricing-for-woocommerce' ) );
//			}
		}

		if ( empty( $_REQUEST['reports'] ) ) {
			wp_send_json_error( __( 'Wrong value for parameter "reports"', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}

		$storage = new ReportsStorage( $import_key );
		$reports = explode( ',', $_REQUEST['reports'] );
		$keys    = array(
			'initial_cart',
			'processed_cart',
			'processed_products',
			'rules_timing',
			'options',
			'additions',
			'active_hooks',
			'rules',
		);

		if ( ! in_array( 'all', $reports ) ) {
			$keys = array_intersect( $keys, $reports );
		}

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = $storage->getReport( $key );
		}

		$tmp_dir  = ini_get( 'upload_tmp_dir' ) ? ini_get( 'upload_tmp_dir' ) : sys_get_temp_dir();
		$filepath = @tempnam( $tmp_dir, 'wdp' );
		$handler  = fopen( $filepath, 'a' );
		fwrite( $handler, json_encode( $data, JSON_PRETTY_PRINT ) );
		fclose( $handler );

		$this->kill_buffers();
		header( 'Content-type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filepath ) . '.json' . '"' );
		$this->send_contents_delete_file( $filepath );

		wp_die();
	}

	private function kill_buffers() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	private function send_contents_delete_file( $filename ) {
		if ( ! empty( $filename ) ) {
			if ( ! $this->function_disabled( 'readfile' ) ) {
				readfile( $filename );
			} else {
				// fallback, emulate readfile
				$file = fopen( $filename, 'rb' );
				if ( $file !== false ) {
					while ( ! feof( $file ) ) {
						echo fread( $file, 4096 );
					}
					fclose( $file );
				}
			}
			unlink( $filename );
		}
	}

	private function function_disabled( $function ) {
		$disabled_functions = explode( ',', ini_get( 'disable_functions' ) );

		return in_array( $function, $disabled_functions );
	}
}
