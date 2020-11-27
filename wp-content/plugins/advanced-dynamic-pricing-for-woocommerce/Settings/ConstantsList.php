<?php

namespace ADP\Settings;

use ADP\Settings\Constants\Constant;
use ADP\Settings\Exceptions\KeyNotFound;

Class ConstantsList {
	/**
	 * @var Constant[]
	 */
	protected $list;

	/**
	 * @param Constant[] $constants
	 */
	public function register( ...$constants ) {
		foreach ( $constants as $constant ) {
			if ( $constant instanceof Constant ) {
				$this->list[ $constant->getId() ] = $constant;
			}
		}
	}

	/**
	 * @param string $key
	 *
	 * @return Constant
	 * @throws KeyNotFound
	 */
	public function getByKey( $key ) {
		if ( ! isset( $this->list[ $key ] ) ) {
			throw new KeyNotFound( $key );
		}

		return $this->list[ $key ];
	}
}
