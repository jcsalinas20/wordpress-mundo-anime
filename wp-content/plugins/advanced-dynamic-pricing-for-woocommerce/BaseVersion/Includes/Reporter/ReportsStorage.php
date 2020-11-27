<?php

namespace ADP\BaseVersion\Includes\Reporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ReportsStorage {
	/**
	 * @var string
	 */
	protected $importKey;

	protected $expirationTimeInSeconds = 1200;

	/**
	 * @param string $importKey
	 */
	public function __construct( $importKey ) {
		$this->importKey = $importKey;
	}

	public function getReport( $reportKey ) {
		return get_transient( $this->getReportTransientKey( $reportKey ) );
	}

	public function storeReport( $reportKey, $data ) {
		set_transient( $this->getReportTransientKey( $reportKey ), $data, $this->expirationTimeInSeconds );
	}

	private function getReportTransientKey( $report_key ) {
		return sprintf( "wdp_profiler_%s_%s", $report_key, $this->importKey );
	}

}
