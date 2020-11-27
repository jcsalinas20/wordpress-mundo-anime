<?php

namespace ADP\BaseVersion\Includes\External\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Updater {
	const DB_VERSION_KEY = "wdp_db_version";

	private static $db_updates = array(
		'2.2.3' => array(
			'migrate_to_2_2_3',
		),
//		'3.0.0' => array(
//			'migrate_to_3_0_0',
//		),
	);

	public static function update() {
		$current_version = get_option( self::DB_VERSION_KEY, "" );

		foreach ( self::$db_updates as $version => $update_callbacks ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					UpdateFunctions::call_update_function( $update_callback );
				}
				update_option( self::DB_VERSION_KEY, $version, false );
			}
		}
	}
}
