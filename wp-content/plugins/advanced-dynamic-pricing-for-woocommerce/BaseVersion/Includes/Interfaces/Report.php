<?php

namespace ADP\BaseVersion\Includes\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface Report {
	/**
	 * @return string
	 */
	public function get_title();

	/**
	 * @return string
	 */
	public function get_subtitle();

	/**
	 * @return string
	 */
	public function get_type();

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	public function get_data( $params );
}