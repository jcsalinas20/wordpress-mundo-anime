<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Importer {
	public static function import_rules( $data, $reset_rules ) {
		return \ADP\Factory::callStaticMethod(
			'Admin_Importer', 'import_rules', $data, $reset_rules
		);
	}
}
