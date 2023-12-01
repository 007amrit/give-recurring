<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan;

use InvalidArgumentException;

/**
 * Class Frequency
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan
 * @see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-frequency
 *
 * @since 1.01.7
 */
class PricingScheme{
	/**
	 * @var float
	 * @since 1.11.0
	 * @see https://developer.paypal.com/docs/api/reference/currency-codes/
	 */
	public $value;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $currencyCode;

	/**
	 * Return Product model fro give array.
	 *
	 * @sicne 1.11.0
	 *
	 * @param  array  $array
	 *
	 * @return PricingScheme
	 */
	public static function formArray( $array ){
		$self = new static();
		$self->validate( $array );

		$self->setupProperties( $array );

		return $self;
	}

	/**
	 * Setup model properties.
	 *
	 * @since 1.11.0
	 *
	 * @param $array
	 */
	private function setupProperties( $array ) {
		$this->value = $array['amount'];
		$this->currencyCode = $array['currency'];
	}

	/**
	 * Validate order given in array format.
	 *
	 * @since 2.8.0
	 *
	 * @param array $array
	 * @throws InvalidArgumentException
	 */
	private function validate( $array ) {
		$required = [ 'currency','amount' ];

		if ( array_diff( $required, array_keys( $array ) ) ) {
			throw new InvalidArgumentException( sprintf(
				'To create a %1$s model object, please provide valid: %2$s',
				__CLASS__,
				implode( ',', $required )
			) );
		}
	}
}
