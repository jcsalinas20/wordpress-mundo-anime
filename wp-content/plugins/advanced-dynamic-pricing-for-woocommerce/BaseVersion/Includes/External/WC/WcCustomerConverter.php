<?php

namespace ADP\BaseVersion\Includes\External\WC;

use ADP\BaseVersion\Includes\Cart\Structures\CartCustomer;
use ADP\BaseVersion\Includes\Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WcCustomerConverter {
	/**
	 * @var Context
	 */
	protected $context;

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * @param \WC_Customer     $wcCustomer
	 * @param \WC_Session|null $wcSession
	 *
	 * @return CartCustomer
	 */
	public function convertFromWcCustomer( $wcCustomer, $wcSession = null ) {
		$context  = $this->context;
		$customer = new CartCustomer();

		if ( ! is_null( $wcCustomer ) ) {
			$customer->setId( $wcCustomer->get_id() );
			$customer->setBillingAddress( $wcCustomer->get_billing( '' ) );
			$customer->setShippingAddress( $wcCustomer->get_shipping( '' ) );
			$customer->setIsVatExempt( $wcCustomer->get_is_vat_exempt() );
		}

		if ( ! is_null( $wcSession ) ) {
			if ( $context->is( $context::WC_CHECKOUT_PAGE ) ) {
				$customer->setSelectedPaymentMethod( $wcSession->get( 'chosen_payment_method' ) );
			}
			if ( $context->is( $context::WC_CHECKOUT_PAGE ) || $context->is( $context::WC_CART_PAGE ) || ! $context->is_catalog() ) {
				$customer->setSelectedShippingMethods( $wcSession->get( 'chosen_shipping_methods' ) );
			}
		}

		$wpUser = new \WP_User( $customer->getId() );
		$customer->setRoles( $wpUser->roles );

		return $customer;
	}

	/**
	 * @param CartCustomer $customer
	 *
	 * @return \WC_Customer
	 */
	public function convertToWcCustomer( $customer ) {
		$wcCustomer = new \WC_Customer();

		$wcCustomer->set_id( $customer->getId() );

		$wcCustomer->set_billing_country( $customer->getBillingCountry() );
		$wcCustomer->set_billing_state( $customer->getBillingState() );
		$wcCustomer->set_billing_postcode( $customer->getBillingPostCode() );
		$wcCustomer->set_billing_city( $customer->getBillingCity() );

		$wcCustomer->set_shipping_country( $customer->getShippingCountry() );
		$wcCustomer->set_shipping_state( $customer->getShippingState() );
		$wcCustomer->set_shipping_postcode( $customer->getShippingPostCode() );
		$wcCustomer->set_shipping_city( $customer->getShippingCity() );

		$wcCustomer->set_is_vat_exempt( $customer->isVatExempt() );

		return $wcCustomer;
	}

}
