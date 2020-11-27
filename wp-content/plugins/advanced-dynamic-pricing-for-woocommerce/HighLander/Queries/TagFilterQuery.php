<?php

namespace ADP\HighLander\Queries;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class TagFilterQuery {
	const ACTION_REMOVE_OTHERS = 1;
	const ACTION_REMOVE_ALL_IN_TAG = 2;

	const ALLOWED_ACTIONS = array(
		self::ACTION_REMOVE_OTHERS,
		self::ACTION_REMOVE_ALL_IN_TAG,
	);

	/**
	 * @var string[]
	 */
	protected $list = array();

	/**
	 * @var int
	 */
	protected $action;

	public function __construct() {
		$this->action = null;
		$this->list   = array();
	}

	public function isValid() {
		return $this->action !== null;
	}

	/**
	 * @param string[] $list
	 *
	 * @return self
	 */
	public function setList( $list ) {
		$this->list = array();

		if ( is_array( $list ) ) {
			foreach ( $list as $item ) {
				if ( is_string( $item ) ) {
					$this->list[] = $item;
				}
			}
		}

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getList() {
		return $this->list;
	}

	/**
	 * @param int $action
	 *
	 * @return self
	 */
	public function setAction( $action ) {
		if ( in_array( $action, self::ALLOWED_ACTIONS ) ) {
			$this->action = $action;
		}

		return $this;
	}

	/**
	 * @return int
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * @param int $action
	 *
	 * @return bool
	 */
	public function isAction( $action ) {
		return in_array( $action, self::ALLOWED_ACTIONS ) ? $this->action === $action : false;
	}
}
