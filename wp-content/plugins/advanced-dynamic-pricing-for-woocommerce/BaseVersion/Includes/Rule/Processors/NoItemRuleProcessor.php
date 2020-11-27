<?php

namespace ADP\BaseVersion\Includes\Rule\Processors;

use ADP\BaseVersion\Includes\Cart\Structures\Cart;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Rule\CartAdjustmentsApplyStrategy;
use ADP\BaseVersion\Includes\Rule\GiftStrategy;
use ADP\BaseVersion\Includes\Rule\Interfaces\RuleProcessor;
use ADP\BaseVersion\Includes\Rule\ConditionsCheckStrategy;
use ADP\BaseVersion\Includes\Rule\Exceptions\RuleExecutionTimeout;
use ADP\BaseVersion\Includes\Rule\LimitsCheckStrategy;
use ADP\BaseVersion\Includes\Rule\RuleTimer;
use ADP\BaseVersion\Includes\Rule\Structures\NoItemRule;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class NoItemRuleProcessor implements RuleProcessor {
	const STATUS_OUT_OF_TIME = - 2;
	const STATUS_UNEXPECTED_ERROR = - 1;
	const STATUS_NO_INFO = 0;
	const STATUS_STARTED = 1;
	const STATUS_DISABLED_WITH_FORCE = 2;
	const STATUS_LIMITS_NOT_PASSED = 3;
	const STATUS_CONDITIONS_NOT_PASSED = 4;
	const STATUS_FILTERS_NOT_PASSED = 5;

	protected $status;
	protected $lastUnexpectedErrorMessage;

	/**
	 * @var float Rule start timestamp
	 */
	protected $execRuleStart;

	/**
	 * @var float Rule start timestamp
	 */
	protected $lastExecTime;

	/**
	 * @var NoItemRule
	 */
	protected $rule;

	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * The way how we check conditions
	 * @var ConditionsCheckStrategy
	 */
	protected $conditionsCheckStrategy;

	/**
	 * The way how we check limits
	 * @var LimitsCheckStrategy
	 */
	protected $limitsCheckStrategy;

	/**
	 * The way how we apply cart adjustments
	 * @var CartAdjustmentsApplyStrategy
	 */
	protected $cartAdjustmentsApplyStrategy;

	/**
	 * @var RuleTimer
	 */
	protected $ruleTimer;

	/**
	 * The way how we gift items
	 * @var GiftStrategy
	 */
	protected $giftStrategy;

	/**
	 * @param Context        $context
	 * @param NoItemRule $rule
	 *
	 * @throws Exception
	 */
	public function __construct( $context, $rule ) {
		$this->context = $context;

		if ( ! ( $rule instanceof NoItemRule ) ) {
			$context->handle_error( new Exception( "Wrong rule type" ) );
		}

		$this->rule = $rule;

		$this->conditionsCheckStrategy      = new ConditionsCheckStrategy( $rule );
		$this->limitsCheckStrategy          = new LimitsCheckStrategy( $rule );
		$this->cartAdjustmentsApplyStrategy = new CartAdjustmentsApplyStrategy( $rule );
		$this->ruleTimer                    = new RuleTimer( $context, $rule );
		$this->giftStrategy                 = new GiftStrategy( $rule );
	}

	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return NoItemRule
	 */
	public function getRule() {
		return $this->rule;
	}

	/**
	 * @inheritDoc
	 */
	public function applyToCart( $cart ) {
		$this->ruleTimer->start();

		global $wp_filter;
		$current_wp_filter = $wp_filter;

		try {
			$this->process( $cart );
		} catch ( RuleExecutionTimeout $e ) {
			$this->status = self::STATUS_OUT_OF_TIME;
			$this->ruleTimer->handleOutOfTime();
		}

		$wp_filter = $current_wp_filter;

		$this->ruleTimer->finish();

		return true;
	}

	/**
	 * @param Cart $cart
	 *
	 * @throws RuleExecutionTimeout
	 *
	 */
	protected function process( $cart ) {
		$this->status = self::STATUS_STARTED;

//		$this->rule = apply_filters( 'adp_before_apply_single_item_rule', $this->rule, $this, $cart );

		if ( apply_filters( 'adp_force_disable_single_item_rule', false, $this->rule, $this, $cart ) ) {
			$this->status = self::STATUS_DISABLED_WITH_FORCE;

			return;
		}

		if ( ! $this->isRuleMatchedCart( $cart ) ) {
			return;
		}
		$this->ruleTimer->checkExecutionTime();

		$this->addGifts( $cart );
		$this->ruleTimer->checkExecutionTime();

		$this->applyCartAdjustments( $cart );
	}

	/**
	 * @param $cart
	 *
	 * @return bool
	 */
	public function isRuleMatchedCart( $cart ) {
		if ( ! $this->checkLimits( $cart ) ) {
			$this->status = $this::STATUS_LIMITS_NOT_PASSED;

			return false;
		}

		if ( ! $this->checkConditions( $cart ) ) {
			$this->status = $this::STATUS_CONDITIONS_NOT_PASSED;

			return false;
		}

		return true;
	}

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	protected function checkLimits( $cart ) {
		return $this->limitsCheckStrategy->check( $cart );
	}

	/**
	 * @param Cart $cart
	 *
	 * @return bool
	 */
	protected function checkConditions( $cart ) {
		return $this->conditionsCheckStrategy->check( $cart );
	}

	/**
	 * @param Cart $cart
	 */
	protected function applyCartAdjustments( $cart ) {
		$this->cartAdjustmentsApplyStrategy->applyToCart( $cart );
	}

	/**
	 * @param Cart $cart
	 */
	protected function addGifts( $cart ) {
		if ( ! $this->giftStrategy->canGift() ) {
			return;
		}

		$this->giftStrategy->addGifts( $cart, $this->calculateUsedQty( $cart ) );
	}

	/**
	 * @param Cart $cart
	 *
	 * @return array
	 */
	protected function calculateUsedQty( &$cart ) {
		return $this->giftStrategy->calculateUsedQtyFreeItems( $cart );
	}

	/**
	 * @return float
	 */
	public function getLastExecTime() {
		return $this->ruleTimer->getLastExecTime();
	}
}
