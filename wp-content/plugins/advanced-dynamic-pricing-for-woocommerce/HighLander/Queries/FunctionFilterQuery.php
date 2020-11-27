<?php

namespace ADP\HighLander\Queries;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FunctionFilterQuery {
	const ACTION_SAVE = 1;
	const ACTION_REMOVE = 2;

	const ALLOWED_ACTIONS = array(
		self::ACTION_SAVE,
		self::ACTION_REMOVE,
	);

	/**
	 * @var callable[]
	 */
	protected $list = array();

	/**
	 * @var int
	 */
	protected $action;

	/**
	 * @var string|null
	 */
	protected $tag;

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
				if ( is_string( $item ) || $item instanceof \Closure ) {
					$this->list[] = $item;
				}
			}
		}

		return $this;
	}

	/**
	 * @return callable[]
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

	/**
	 * @param $tag string
	 *
	 * @return self
	 */
	public function useTag( $tag ) {
		if ( is_string( $tag ) ) {
			$this->tag = $tag;
		}

		return $this;
	}

	/**
	 * @param string $tag
	 *
	 * @return bool
	 */
	public function isUseTag( $tag ) {
		if ( ! is_string( $tag ) ) {
			return false;
		}

		return ! is_null( $this->tag ) ? $this->tag === $tag : true;
	}

	/**
	 * @return string|null
	 */
	public function getTag() {
		return $this->tag;
	}
}
