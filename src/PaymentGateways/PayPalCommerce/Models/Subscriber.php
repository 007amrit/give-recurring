<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models;

use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Subscriber\Card;

/**
 * Class Product
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Models
 * @see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-subscriber_request
 *
 * @since 1.11.0
 */
class Subscriber{
	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $firstName;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $lastName;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $emailAddress;

	/**
	 * @var Card
	 * @since 1.11.0
	 */
	public $card;

	/**
	 * Subscriber constructor.
	 *
	 * @param string $firstName
	 * @param string $lastName
	 * @param string $emailAddress
	 */
	public function __construct( $firstName, $lastName, $emailAddress ){
		$this->firstName = $firstName;
		$this->lastName = $lastName;
		$this->emailAddress = $emailAddress;
	}

	/**
	 * Set card details.
	 *
	 * @since 1.11.0
	 *
	 * @param  Card  $card
	 *
	 * @return $this
	 */
	public function setCardDetails( Card $card ){
		$this->card = $card;

		return $this;
	}

	/**
	 * Return whether or not subscriber has valid card.
	 *
	 * @since 1.11.0
	 *
	 * @return bool
	 */
	public function hasCard(){
		return $this->card instanceof Card;
	}
}
