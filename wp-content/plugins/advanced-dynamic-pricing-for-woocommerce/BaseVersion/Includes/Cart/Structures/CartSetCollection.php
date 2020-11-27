<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartSetCollection {
	/**
	 * @var CartSet[]
	 */
	private $sets = array();

	public function __clone() {
		$new_sets = array();
		foreach ( $this->sets as $set ) {
			$new_sets[] = clone $set;
		}

		$this->sets = $new_sets;
	}

	public function __construct() {
	}

	/**
	 * @param $set_to_add CartSet
	 *
	 * @return boolean
	 */
	public function add( $set_to_add ) {
		$added = false;
		foreach ( $this->sets as &$set ) {
			/**
			 * @var $set CartSet
			 */
			if ( $set->get_hash() === $set_to_add->get_hash() ) {
				$set->inc_qty( $set_to_add->get_qty() );
				$added = true;
				break;
			}
		}

		if ( ! $added ) {
			$this->sets[] = $set_to_add;
		}

		/**
		 * Do use sorting here!
		 * It breaks positional discounts like 'Tier discount'.
		 */

		return true;
	}

	public function is_empty() {
		return empty( $this->sets );
	}

	/**
	 * @return CartSet[]
	 */
	public function get_sets() {
		return $this->sets;
	}

	public function get_hash() {
		$sets = array();
		foreach ( $this->sets as $set ) {
			$sets[] = clone $set;
		}

		usort( $sets, function ( $set_a, $set_b ) {
			/**
			 * @var $set_a CartSet
			 * @var $set_b CartSet
			 */
			return strnatcmp( $set_a->get_hash(), $set_b->get_hash() );
		} );

		$sets_hashes = array_map( function ( $set ) {
			/**
			 * @var $set CartSet
			 */
			return $set->get_hash();
		}, $sets );
		$encoded     = json_encode( $sets_hashes );
		$hash        = md5( $encoded );

		return $hash;
	}

	public function purge() {
		$this->sets = array();
	}

	public function get_total_sets_qty() {
		$count = 0;

		foreach ( $this->sets as $set ) {
			$count += $set->get_qty();
		}

		return $count;
	}

	public function get_set_by_hash( $hash ) {
		foreach ( $this->sets as $set ) {
			if ( $set->get_hash() === $hash ) {
				$new_set = clone $set;

				return $new_set;
			}
		}

		return null;
	}


}
