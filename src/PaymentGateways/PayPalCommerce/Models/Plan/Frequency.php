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
class Frequency{
	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $intervalUnit;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $intervalCount;

	/**
	 * Return Product model fro give array.
	 *
	 * @sicne 1.11.0
	 *
	 * @param  array  $array
	 *
	 * @return Frequency
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
		$this->intervalUnit = strtoupper( $array['period'] );
		$this->intervalCount = $array['frequency'];
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
		$required = [ 'frequency','period' ];

		if ( array_diff( $required, array_keys( $array ) ) ) {
			throw new InvalidArgumentException( sprintf(
				'To create a %1$s model object, please provide valid: %2$s',
				__CLASS__,
				implode( ',', $required )
			) );
		}
	}
}
