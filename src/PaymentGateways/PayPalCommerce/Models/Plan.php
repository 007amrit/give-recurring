<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models;

use Give\PaymentGateways\PayPalCommerce\Models\MerchantDetail;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan\BillingCycle;
use InvalidArgumentException;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Plan as PlanRepository;

/**
 * Class Product
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Models
 * @see https://developer.paypal.com/docs/api/subscriptions/v1/#plans-create-response
 *
 * @since 1.11.0
 */
class Plan{
	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $name;

	/**
	 * @var BillingCycle
	 * @since 1.11.0
	 */
	public $billingCycles;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $description;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $id;

	/**
	 * @var int
	 * @since 1.11.0
	 */
	public $formId;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $productId;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $status;

	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $priceId = 0;

	/**
	 * Return Product model for give array.
	 *
	 * @sicne 1.11.0
	 *
	 * @param  array  $array
	 *
	 * @return Plan
	 */
	public static function formArray( $array ){
		$self = new static();
		$self->validate( $array );

		$self->setupProperties( $array );

		return $self;
	}

	/**
	 * Update model with PayPal response.
	 *
	 * @param array $array
	 *
	 * @since 1.11.0
	 */
	public function updateWithPayPalResponse( $array ){
		$this->id = $array['id'];
		$this->status = $array['status'];
		$this->productId = $array['product_id'];
		$this->name = $array['name'];

		$this->billingCycles = BillingCycle::formArray( [
			'amount'    => $array['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'],
			'period'    => $array['billing_cycles'][0]['frequency']['interval_unit'],
			'currency'  => $array['billing_cycles'][0]['pricing_scheme']['fixed_price']['currency_code'],
			'frequency' => $array['billing_cycles'][0]['frequency']['interval_count'],
			'times'     => $array['billing_cycles'][0]['total_cycles'],
		] );
	}

	/**
	 * Setup model properties.
	 *
	 * @since 1.11.0
	 *
	 * @param $array
	 */
	private function setupProperties( $array ) {
		$properties = get_object_vars( $this );

		foreach ( $properties as $property => $value ) {
			if( ! array_key_exists( $property, $array ) ) {
				continue;
			}

			$this->{$property} = $array[ $property ];
		}

		$this->billingCycles = BillingCycle::formArray( [
			'amount'    => $array['amount'],
			'period'    => $array['period'],
			'currency'  => $array['currency'],
			'frequency' => $array['frequency'],
			'times'     => $array['times'],
		] );
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
		// "id" is assign by PayPal, so it is not required.
		$required = [ 'formId', 'productId', 'amount', 'frequency','period', 'times', 'currency', 'priceId' ];

		if ( array_diff( $required, array_keys( $array ) ) ) {
			throw new InvalidArgumentException( sprintf(
				'To create a %1$s model object, please provide valid: %2$s',
				__CLASS__,
				implode( ',', $required )
			) );
		}
	}

	/**
	 * Return unique code for plan.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	public function getUniqueCodeForPlan(){
		return substr(
			md5(sprintf(
				'%1$s:%2$s:%3$s:%4$s:%5$s:%6$s:%7$s',
				$this->formId,
				$this->priceId,
				$this->billingCycles->pricingScheme->value,
				$this->billingCycles->frequency->intervalUnit,
				$this->billingCycles->frequency->intervalCount,
				$this->billingCycles->totalCycles,
				$this->billingCycles->pricingScheme->currencyCode
			)), 0, 15
		);
	}

	/**
	 * Return form meta key for plan details.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	public function getPlanDetailFormMetaKey(){
		return sprintf(
			'%1$s_%2$s_%3$s',
			give(MerchantDetail::class)->merchantIdInPayPal,
			PlanRepository::PLAN_FORM_META_KEY,
			$this->getUniqueCodeForPlan()
		);
	}


	/**
	 * Get name for PayPal plan.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	public function getNameFormPayPalPlan(){
		$label = sprintf(
			'%1$s - %2$s',
			give_recurring_generate_subscription_name( $this->formId, $this->priceId ),
			give_recurring_pretty_subscription_frequency(
				strtolower(  $this->billingCycles->frequency->intervalUnit ),
				$this->billingCycles->totalCycles,
				false,
				$this->billingCycles->frequency->intervalCount
			)
		);
		return substr( $label, 0, 127 );
	}
}
