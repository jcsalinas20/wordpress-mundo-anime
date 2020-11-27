<?php

namespace ADP\BaseVersion\Includes\Settings;

use ADP\Settings\Exceptions\KeyNotFound;
use ADP\Settings\Interfaces\StoreStrategyInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class StoreStrategy implements StoreStrategyInterface {
	const OPTION_KEY = 'wdp_settings';

	public function save( $optionsList ) {
		if ( $optionsList->getOptionsArray() ) {
			update_option( self::OPTION_KEY, $optionsList->getOptionsArray() );
			wp_cache_flush();
		}
	}

	public function load( $optionsList ) {
		$options = get_option( self::OPTION_KEY, array() );

		foreach ( $options as $key => $value ) {
			try {
				$option = $optionsList->getByKey( $key );
				$option->set( $value );
			} catch ( KeyNotFound $exception ) {

			}
		}
	}
}
