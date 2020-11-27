<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartItemsCollection {
	/**
	 * @var CartItem[]
	 */
	private $items = array();

	/**
	 * @var int
	 */
	private $rule_id;

	public function __construct( $rule_id ) {
		$this->rule_id = $rule_id;
	}

	public function __clone() {
		$newItems = array();
		foreach ( $this->items as $item ) {
			$newItems[] = clone $item;
		}

		$this->items = $newItems;
	}

	/**
	 * @param $item_to_add CartItem
	 *
	 * @return boolean
	 */
	public function add( $item_to_add ) {
		$added = false;
		foreach ( $this->items as $item ) {
			/**
			 * @var $item CartItem
			 */
			if ( $item->getHash() === $item_to_add->getHash() && ( $item->getOriginalPrice() === $item_to_add->getOriginalPrice() ) ) {
				$item->setQty( $item->getQty() + $item_to_add->getQty() );
				$added = true;
				break;
			}
		}

		if ( ! $added ) {
			$this->items[] = $item_to_add;
		}

		$this->sort_items();

		return true;
	}

	private function sort_items() {
		return;
		usort( $this->items, function ( $item_a, $item_b ) {
			/**
			 * @var $item_a CartItem
			 * @var $item_b CartItem
			 */
			if ( ! $item_a->hasAttr( $item_a::ATTR_TEMP ) && $item_b->hasAttr( $item_b::ATTR_TEMP ) ) {
				return - 1;
			}

			if ( $item_a->hasAttr( $item_a::ATTR_TEMP ) && ! $item_b->hasAttr( $item_b::ATTR_TEMP ) ) {
				return 1;
			}

			return 0;
		} );

	}

	public function is_empty() {
		return empty( $this->items );
	}

	/**
	 * @return CartItem[]
	 */
	public function get_items() {
		return $this->items;
	}

	public function get_hash() {
		$hashes = array_map( function ( $item ) {
			return $item->getHash();
		}, $this->items );

		return md5( json_encode( $hashes ) );
	}

	public function purge() {
		$this->items = array();
	}

	public function get_count() {
		return count( $this->items );
	}

	public function get_total_qty() {
		$totalQty = 0;
		foreach( $this->items as $item ) {
			$totalQty += $item->getQty();
		}

		return $totalQty;
	}

	public function get_item_by_hash( $hash ) {
		foreach ( $this->items as $item ) {
			if ( $item->getHash() === $hash ) {
				$new_item = clone $item;

				return $new_item;
			}
		}

		return null;
	}

	public function get_not_empty_item_with_reference_by_hash( $hash ) {
		foreach ( $this->items as $item ) {
			if ( $item->getHash() === $hash && $item->getQty() > 0 ) {
				return $item;
			}
		}

		return null;
	}

	public function remove_item_by_hash( $hash ) {
		foreach ( $this->items as $index => $item ) {
			if ( $item->getHash() === $hash ) {
				unset( $this->items[ $index ] );
				$this->items = array_values( $this->items );

				return true;
			}
		}

		return false;
	}

	public function set_price_for_item( $hash, $price, $qty = null ) {
		foreach ( $this->items as &$item ) {
			if ( $item->getHash() === $hash ) {
				if ( $qty && $item->getQty() > $qty ) {
					$new_item = clone $item;
					$new_item->setQty( $qty );
					$new_item->setPrice( $this->rule_id, $price );
					$this->items[] = $new_item;

					$item->setQty( $item->getQty() - $qty );
					$this->sort_items();
				} else {
					$item->setPrice( $this->rule_id, $price );
				}

				$this->get_hash();

				return;
			}
		}
	}

	public function make_items_immutable() {
		foreach ( $this->items as &$item ) {
			$item->addAttr( $item::ATTR_IMMUTABLE );
		}
	}


}
