<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models\Subscriber;

class Card{
	/**
	 * @since 1.11.0
	 * @var string
	 */
	public $name;

	/**
	 * @since 1.11.0
	 * @var string
	 */
	public $number;

	/**
	 * @since 1.11.0
	 * @var string
	 */
	public $cvc;

	/**
	 * @since 1.11.0
	 * @var string
	 */
	public $expiry;

	/**
	 * Card constructor.
	 *
	 * @param $name
	 * @param $number
	 * @param $cvc
	 * @param $expiry
	 */
	public function __construct( $name, $number, $cvc, $expiry ){
		$this->name = $name;
		$this->number = $number;
		$this->cvc = $cvc;
		$this->expiry = $expiry;
	}
}
