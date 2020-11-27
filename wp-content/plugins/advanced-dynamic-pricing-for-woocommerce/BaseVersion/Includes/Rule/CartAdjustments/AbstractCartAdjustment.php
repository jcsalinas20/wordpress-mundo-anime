<?php

namespace ADP\BaseVersion\Includes\Rule\CartAdjustments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class AbstractCartAdjustment {
	protected $amount_indexes;

	/**
	 * @param float $rate
	 */
	public function multiply_amounts( $rate ) {
		foreach ( $this->amount_indexes as $index ) {
			/**
			 * @var string $index
			 */
			if ( isset( $this->$index ) ) {
				$amount = (float) $this->$index;
				$this->$index = $amount * $rate;
			}
		}
	}
}
