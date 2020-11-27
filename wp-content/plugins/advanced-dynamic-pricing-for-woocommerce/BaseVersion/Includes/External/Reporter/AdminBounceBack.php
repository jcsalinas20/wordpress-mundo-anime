<?php

namespace ADP\BaseVersion\Includes\External\Reporter;

use ADP\BaseVersion\Includes\Reporter\CalculationProfiler;
use WC_Session_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AdminBounceBack {
	const REQUEST_KEY = 'wdp_bounce_back';
	const REQUEST_KEY_VALUE = '1';

	const LAST_IMPORT_KEY_SESSION_KEY = 'wdp_last_import_key';

	/**
	 * @var CalculationProfiler
	 */
	protected $profiler;

	public function __construct( $profiler ) {
		$this->profiler = $profiler;
	}

	public function catch_bounce_event() {
		if ( ! empty( $_REQUEST[ self::REQUEST_KEY ] ) ) {
			$this->action_bounce_back();
		}
	}

	/**
	 * We wait until page fully loaded
	 */
	protected function action_bounce_back() {
		if ( did_action( 'wp_print_scripts' ) ) {
			_doing_it_wrong( __FUNCTION__,
				sprintf( __( '%1$s should not be called earlier the %2$s action.', 'woocommerce' ),
					'action_bounce_back', 'wp_print_scripts' ), WC_ADP_VERSION );

			return null;
		}

		add_action( "wp_print_scripts", function () {
			$referer = wp_get_referer();
			$referer = $referer ?: admin_url();
			WC()->session->set( self::LAST_IMPORT_KEY_SESSION_KEY, $this->profiler->get_import_key() );

			?>
            <meta http-equiv="refresh" content="0; url=<?php echo $referer; ?>">
			<?php
		} );
	}

	public static function generate_bounce_back_url() {
		return add_query_arg( self::REQUEST_KEY, self::REQUEST_KEY_VALUE, get_permalink( wc_get_page_id( 'shop' ) ) );
	}

	public static function get_bounce_back_report_download_url() {
		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		$session       = new $session_class();
		/**
		 * @var WC_Session_Handler $session
		 */
		$session->init();

		if ( isset( $session->wdp_last_import_key ) ) {
			$import_key = $session->get( self::LAST_IMPORT_KEY_SESSION_KEY );
			$session->__unset( self::LAST_IMPORT_KEY_SESSION_KEY );
			$session->save_data();
		} else {
			$import_key = false;
		}

		return ! $import_key ? "" : add_query_arg( array(
			'action'                             => 'download_report',
			ReporterAjax::IMPORT_KEY_REQUEST_KEY => $import_key,
			'reports'                            => 'all',
		), admin_url( "admin-ajax.php" ) );
	}
}
