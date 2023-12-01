<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Repositories;

use Give\PaymentGateways\PayPalCommerce\PayPalClient;
use GiveRecurring\PaymentGateways\PayPalCommerce\HttpHeader;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan as PlanModel;
use Exception;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Product as ProductModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\PayPalResponse;

/**
 * Class Plan
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Repositories
 *
 * @since 1.11.0
 */
class Plan{
	/**
	 * Form meta key for plan details.
	 *
	 * @since 1.11.0
	 * @var string
	 */
	const PLAN_FORM_META_KEY = 'paypal_commerce_plan';

	/**
	 * @since 1.11.0
	 * @var PayPalClient
	 */
	private $payPalClient;

	/**
	 * @since 1.11.0
	 * @var string
	 */
	public $id;

	/**
	 * Product constructor.
	 *
	 * @param  PayPalClient  $payPalClient
	 *
	 * @since 1.11.0
	 */
	public function __construct( PayPalClient $payPalClient ){
		$this->payPalClient = $payPalClient;
	}

	/**
	 * Get PayPal Plan.
	 *
	 * @since 1.11.0
	 *
	 * @param  PlanModel  $plan
	 *
	 * @return PlanModel
	 * @throws Exception
	 */
	public function get( PlanModel $plan ){
		$storedPlanDetails = give()->form_meta->get_meta( $plan->formId, $plan->getPlanDetailFormMetaKey(), true );

		if( ! $storedPlanDetails ) {
			return null;
		}

		$plan->updateWithPayPalResponse( $storedPlanDetails );

		return $plan;
	}

	/**
	 * Create plan.
	 *
	 * On success this will trigger BILLING.PLAN.CREATED webhook.
	 *
	 * @see https://developer.paypal.com/docs/api/subscriptions/v1/#plans_create
	 *
	 * @since 1.11.0
	 *
	 * @param  PlanModel  $plan
	 *
	 * @param  ProductModel  $product
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create( PlanModel $plan, ProductModel $product ) {
		/* @var PayPalResponse $paypalResponse */
		$paypalResponse = give( PayPalResponse::class );
		$paypalResponse->setLogTitle( 'PayPal Commerce Plan Api Failure' );

		try {
			$response = wp_remote_post(
				$this->payPalClient->getApiUrl( 'v1/billing/plans' ),
				[
					'headers' => give( HttpHeader::class )->getHeaders(),
					'body' => wp_json_encode(
						[
							'product_id' => $product->id,
							'name'        => $plan->getNameFormPayPalPlan(),
							'description' => $plan->description,
							'billing_cycles' => [
								[
									'frequency' => [
										'interval_unit' => $plan->billingCycles->frequency->intervalUnit,
										'interval_count' => $plan->billingCycles->frequency->intervalCount,
									],
									'tenure_type' => $plan->billingCycles->tenureType,
									'sequence' => $plan->billingCycles->sequence,
									'total_cycles' => $plan->billingCycles->totalCycles,
									'pricing_scheme' => [
										// @see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-money
										'fixed_price' => [
											// casting due to PHP float in json_encode bug: https://stackoverflow.com/questions/42981409/php7-1-json-encode-float-issue/43056278#43056278
											'value' => (string) $plan->billingCycles->pricingScheme->value,
											'currency_code' => $plan->billingCycles->pricingScheme->currencyCode
										]
									]
								]
							],
							//@see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-payment_preferences
							'payment_preferences' => [
								'auto_bill_outstanding'     => true,
								'setup_fee'                 => [
									'value'         => 0,
									'currency_code' => $plan->billingCycles->pricingScheme->currencyCode,
								],
								'setup_fee_failure_action'  => 'CANCEL',
								'payment_failure_threshold' => 3,
							]
						]
					),
					'data_format' => 'body',
				]
			);

			if( ! $paypalResponse->setData( $response )->isSuccessful() ) {
				return [];
			}

			return $paypalResponse->getBodyData();
		} catch ( Exception $ex ){
			throw $ex;
		}
	}

	/**
	 * Store product detail.
	 *
	 * @since 1.11.0
	 *
	 * @param  int  $formId
	 * @param  string  $metaKey
	 * @param  array  $plan
	 *
	 * @return bool
	 */
	public function storePlanDetail( $formId, $metaKey, $plan ) {
		return give()->form_meta->update_meta( $formId, $metaKey, $plan );
	}
}
