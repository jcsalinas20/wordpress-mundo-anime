<?php

namespace ADP\BaseVersion\Includes\Cart;

use ADP\BaseVersion\Includes\Cart\Structures\CartCustomer;
use ADP\BaseVersion\Includes\Context;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CartCustomerHelper {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @var CartCustomer
	 */
	protected $cartCustomer;

	/**
	 * @param Context      $context
	 * @param CartCustomer $cartCustomer
	 */
	public function __construct( $context, $cartCustomer ) {
		$this->context      = $context;
		$this->cartCustomer = $cartCustomer;
	}

	public function isLoggedIn() {
		$user         = $this->context->get_current_user();
		$cartCustomer = $this->cartCustomer;

		return $this->context->isUserLoggedIn() && $user->ID === $cartCustomer->getId();
	}

	/**
	 * @param $time
	 *
	 * @return int
	 */
	public function getOrderCountAfter( $time ) {
		$time = $this->convertForStrToTime( $time );

		if ( $time === false ) {
			return floatval( 0 );
		}

		$args = array(
			'post_status' => array_keys( wc_get_order_statuses() ),
		);

		if ( ! empty( $time ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => $time,
				),
			);
		}

		return count( $this->getOrderIds( $args ) );
	}

	/**
	 * @return false|WC_Order
	 */
	public function getLastPaidOrder() {
		$order_ids = $this->getOrderIds( array(
			'post_status' => array( 'wc-completed' ),
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		return wc_get_order( array_pop( $order_ids ) );
	}

	/**
	 * @param $time
	 *
	 * @return float
	 */
	public function getTotalSpendAmount( $time ) {
		$time = $this->convertForStrToTime( $time );

		if ( $time === false ) {
			return floatval( 0 );
		}

		$args = array(
			'post_status' => array( 'wc-completed' ),
		);

		if ( ! empty( $time ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => $time,
				),
			);
		}

		$order_ids = $this->getOrderIds( $args );

		$orders = array_filter( array_map( 'wc_get_order', $order_ids ) );

		if ( ! count( $orders ) ) {
			return floatval( 0 );
		}

		return array_sum( array_map( function ( $order ) {
			/**
			 * @var $order WC_Order
			 */
			return $order->get_total() - $order->get_total_refunded();
		}, $orders ) );
	}

	/**
	 * @return float
	 */
	public function getAvgSpendAmount() {
		$order_ids = $this->getOrderIds( array(
			'statuses' => array( 'wc-completed' ),
		) );

		$orders = array_filter( array_map( 'wc_get_order', $order_ids ) );

		if ( ! count( $orders ) ) {
			return floatval( 0 );
		}

		return array_sum( array_map( function($order) { return $order->get_total(); }, $orders ) ) / count( $orders );
	}

	/**
	 * @param array $args
	 *
	 * @return int[]
	 */
	protected function getOrderIds( $args = array() ) {
		if ( $this->cartCustomer->isGuest() ) {
			return array();
		}

		$args = array_merge( array(
			'numberposts' => - 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_key'    => '_customer_user',
			'meta_value'  => $this->cartCustomer->getId(),
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'fields'      => 'ids',

		), $args );

		return get_posts( $args );
	}

	public function convertForStrToTime( $time ) {
		if ( ! $time OR ! is_string( $time ) ) {
			return false;
		}

		if ( 'all_time' == $time ) {
			$time = 0;
		} elseif ( 'now' == $time ) {
			$time = 'today';
		} elseif ( 'this week' == $time ) {
			$time = 'last monday';
		} elseif ( 'this month' == $time ) {
			$time = 'first day of ' . date( 'F Y', current_time( 'timestamp' ) );
		} elseif ( 'this year' == $time ) {
			$time = 'first day of January ' . date( 'Y', current_time( 'timestamp' ) );
		}

		return $time;
	}
}
