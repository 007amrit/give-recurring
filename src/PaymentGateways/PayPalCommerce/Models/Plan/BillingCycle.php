<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan;


use InvalidArgumentException;

/**
 * Class Product
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Models
 * @see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-billing_cycle
 *
 * @since 1.11.0
 */
class BillingCycle{
	/**
	 * @var Frequency
	 * @since 1.11.0
	 */
	public $frequency;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $tenureType = 'REGULAR';

	/**
	 * @var int
	 * @since 1.11.0
	 */
	public $sequence = 1;

	/**
	 * @var int
	 * @since 1.11.0
	 */
	public $totalCycles;

	/**
	 * @var PricingScheme
	 * @since 1.11.0
	 */
	public $pricingScheme;

	/**
	 * Return Product model fro give array.
	 *
	 * @sicne 1.11.0
	 *
	 * @param  array  $array
	 *
	 * @return BillingCycle
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
		$this->frequency = Frequency::formArray([
			'period' => $array['period'],
			'frequency' => $array['frequency']
		]);

		$this->pricingScheme = PricingScheme::formArray([
			'currency' => $array['currency'],
			'amount' => $array['amount']
		]);

		$this->totalCycles = $array['times'];
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
		$required = [ 'amount', 'frequency','period', 'times', 'currency' ];

		if ( array_diff( $required, array_keys( $array ) ) ) {
			throw new InvalidArgumentException( sprintf(
				'To create a %1$s model object, please provide valid: %2$s',
				__CLASS__,
				implode( ',', $required )
			) );
		}
	}
}
