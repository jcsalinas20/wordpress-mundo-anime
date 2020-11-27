<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartSet {
	/**
	 * @var string
	 */
	private $hash;

	/**
	 * @var CartItem[]
	 */
	private $items;

	/**
	 * @var integer
	 */
	private $qty;

	/**
	 * @var int
	 */
	private $rule_id;

	/**
	 * @var array
	 */
	private $item_positions;

	/**
	 * @var array
	 */
	protected $marks;

	/**
	 * @param $rule_id int
	 * @param $cart_items CartItem[]
	 * @param $qty int
	 */
	public function __construct( $rule_id, $cart_items, $qty = 1 ) {
		$this->rule_id = $rule_id;

		$plain_items = array();
		foreach ( array_values( $cart_items ) as $index => $item ) {
			if ( $item instanceof CartItem ) {
				$plain_items[] = array(
					'pos'  => $index,
					'item' => $item,
				);
			} elseif ( is_array( $item ) ) {
				foreach ( $item as $sub_item ) {
					if ( $sub_item instanceof CartItem ) {
						$plain_items[] = array(
							'pos'  => $index,
							'item' => $sub_item,
						);
					}
				}
			}
		}

		usort( $plain_items, function ( $plain_item_a, $plain_item_b ) {
			$item_a = $plain_item_a['item'];
			$item_b = $plain_item_b['item'];
			/**
			 * @var $item_a CartItem
			 * @var $item_b CartItem
			 */

			$tmp_a = $item_a->hasAttr($item_a::ATTR_TEMP);
			$tmp_b = $item_b->hasAttr($item_a::ATTR_TEMP);

			if ( ! $tmp_a && $tmp_b ) {
				return - 1;
			}

			if ( $tmp_a && ! $tmp_b ) {
				return 1;
			}

			return 0;
		} );

		$this->items          = array_column( $plain_items, 'item' );
		$this->item_positions = array_column( $plain_items, 'pos' );

		$this->recalculate_hash();
		$this->hash  = $this->get_hash();
		$this->qty   = $qty;
		$this->marks = array();
	}

	private function sort_items() {
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

	public function __clone() {
		$new_items = array();
		foreach ( $this->items as $item ) {
			$new_items[] = clone $item;
		}

		$this->items = $new_items;
	}

	public function get_total_price() {
		return $this->get_price() * $this->qty;
	}

	public function get_price() {
		$price = 0.0;
		foreach ( $this->items as $item ) {
			$price += $item->getPrice() * $item->getQty();
		}

		return $price;
	}

	/**
	 * @return string
	 */
	public function get_hash() {
		return $this->hash;
	}

	public function recalculate_hash() {
		$hashes = array_map( function ( $item ) {
			/**
			 * @var $item CartItem
			 */
			return $item->getHash();
		}, $this->items );

		$this->hash = md5( json_encode( $hashes ) );
	}

//	public function calc_no_price_hash() {
//		$hashes = array_map( function ( $item ) {
//			/**
//			 * @var $item CartItem
//			 */
//			return $item->calc_no_price_hash();
//		}, $this->items );
//
//		return md5( json_encode( $hashes ) );
//	}

	public function get_qty() {
		return $this->qty;
	}

	public function get_items() {
		return $this->items;
	}

	public function get_positions() {
		$positions = array_unique( array_values( $this->item_positions ) );
		sort( $positions );

		return $positions;
	}

	public function set_price_for_item( $hash, $price, $qty = null, $position = null ) {
		if ( $position ) {
			$items = $this->get_items_by_position_with_reference( $position );
		} else {
			$items = $this->items;
		}

		foreach ( $items as &$item ) {
			if ( $item->getHash() === $hash ) {
				if ( $qty && $item->getQty() > $qty ) {
					$new_item = clone $item;
					$new_item->setQty( $qty );
					$new_item->setPrice( $this->rule_id, $price );
					$this->items[] = $new_item;

					$item->setQty( $item->getQty() - $qty );
				} else {
					$item->setPrice( $this->rule_id, $price );
				}

				break;
			}
		}
		$this->recalculate_hash();
	}

	public function set_price_for_items_by_position( $index, $prices ) {
		$items = $this->get_items_by_position_with_reference( $index );

		if ( ! $items ) {
			return;
		}

		$items  = array_values( $items );
		$prices = array_values( $prices );

		if ( count( $items ) !== count( $prices ) ) {
			return;
		}

		foreach ( $items as $index => $item ) {
			/**
			 * @var $item CartItem
			 */
			$item->setPrice( $this->rule_id, $prices[ $index ] );
		}

		$this->recalculate_hash();
	}

	public function inc_qty( $qty ) {
		$this->qty += $qty;
	}

	public function set_qty( $qty ) {
		$this->qty = $qty;
	}

	public function get_items_by_position( $index ) {
		$items = array();
		foreach ( $this->get_items_by_position_with_reference( $index ) as $item ) {
			$items[] = $item;
		}

		return $items;
	}

//	public function set_first_discount_range_rule( $rule_id ) {
//		foreach ( $this->items as $item ) {
//			/**
//			 * @var $item CartItem
//			 */
//			$item->set_first_discount_range_rule( $rule_id );
//		}
//	}

	private function get_items_by_position_with_reference( $index ) {
		$items = array();
		foreach ( $this->item_positions as $internal_index => $position ) {
			if ( $position === $index ) {
				$items[] = $this->items[ $internal_index ];
			}
		}

		return $items;
	}

	/**
	 * @param string $mark
	 *
	 * @return bool
	 */
	public function hasMark( $mark ) {
		return in_array( $mark, $this->marks );
	}

	/**
	 * @param array $marks
	 */
	public function addMark( ...$marks ) {
		$this->marks = $marks;
		$this->recalculate_hash();
	}

	/**
	 * @param array $marks
	 */
	public function removeMark( ...$marks ) {
		foreach ( $marks as $mark ) {
			$pos = array_search( $mark, $this->marks );

			if ( $pos !== false ) {
				unset( $this->marks[ $pos ] );
			}
		}

		$this->marks = array_values( $this->marks );
		$this->recalculate_hash();
	}

	/**
	 * @param float $qty
	 */
	public function setQty( $qty ) {
		$this->set_qty( $qty );
	}

	/**
	 * @return float
	 */
	public function getQty() {
		return $this->get_qty();
	}
}
