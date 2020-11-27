<?php

namespace ADP\BaseVersion\Includes\Cart\Structures;

use ADP\BaseVersion\Includes\Context;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ShippingAdjustment {
	const TYPE_FREE = 'free';
	const TYPE_PERCENTAGE = 'percentage';
	const TYPE_AMOUNT = 'fixed_amount';
	const TYPE_FIXED_VALUE = 'fixed_value';

	const AVAILABLE_TYPES = array(
		self::TYPE_FREE,
		self::TYPE_PERCENTAGE,
		self::TYPE_AMOUNT,
		self::TYPE_FIXED_VALUE,
	);

	/**
	 * @var integer
	 */
	protected $ruleId;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var float
	 */
	protected $value;

	/**
	 * @var float
	 */
	protected $amount;

	/**
	 * @param Context $context
	 * @param string  $type
	 * @param float   $value
	 * @param integer    $ruleId
	 */
	public function __construct( $context, $type, $value, $ruleId ) {
		if ( ! in_array( $type, self::AVAILABLE_TYPES ) ) {
			$context->handle_error( new Exception( sprintf( "Shipping adjustment type '%s' not supported", $type ) ) );
		}

		$this->type   = $type;
		$this->value  = floatval( $value );
		$this->ruleId = $ruleId;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	public function isType( $type ) {
		return $this->type === $type;
	}

	/**
	 * @param float $value
	 */
	public function setValue( $value ) {
		$this->value = floatval( $value );
	}

	/**
	 * @return float
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * @return integer
	 */
	public function getRuleId() {
		return $this->ruleId;
	}

	public function setAmount( $amount ) {
		$this->amount = floatval( $amount );
	}

	/**
	 * @return float|null
	 */
	public function getAmount() {
		return $this->amount;
	}
}
