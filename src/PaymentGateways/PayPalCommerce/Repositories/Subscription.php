<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Repositories;

use Give\PaymentGateways\PayPalCommerce\PayPalClient;
use Give_Subscription;
use Give_Subscriptions_DB;
use GiveRecurring\PaymentGateways\PayPalCommerce\HttpHeader;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Subscriber;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan as PlanModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\PayPalResponse;
use Exception;

/**
 * Class Subscription
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Repositories
 *
 * @since 1.11.0
 */
class Subscription{
	/**
	 * @var PayPalClient
	 *
	 * @since 1.11.0
	 */
	private $payPalClient;

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
	 * Create PayPal Subscription
	 *
	 * @sicne 1.11.0
	 *
	 * @param  PlanModel  $plan
	 * @param  Subscriber  $subscriber
	 * @param  array  $redirectUrls
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create( PlanModel $plan, Subscriber $subscriber, $redirectUrls = array() ){
		/* @var PayPalResponse $paypalResponse */
		$paypalResponse = give( PayPalResponse::class );
		$paypalResponse->setLogTitle( 'PayPal Commerce Subscription Api Failure' );

		try {
			$response = wp_remote_post(
				$this->payPalClient->getApiUrl( 'v1/billing/subscriptions' ),
				[
					'headers' => give( HttpHeader::class )->getHeaders( [ 'PayPal-Partner-Attribution-Id' => give('PAYPAL_COMMERCE_SUBSCRIPTION_ATTRIBUTION_ID') ] ),
					'timeout' => 20, // Direct card flow subscription payment take time.
					'body' => wp_json_encode( $this->getCreateSubscriptionQuery( $plan, $subscriber, $redirectUrls ) ),
					'display' => 'body'
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
	 * Create PayPal Subscription
	 *
	 * @sicne 1.11.0
	 *
	 * @param string $paypalSubscriptionId
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getFromPayPal( $paypalSubscriptionId ){
		/* @var PayPalResponse $paypalResponse */
		$paypalResponse = give( PayPalResponse::class );
		$paypalResponse->setLogTitle( 'PayPal Commerce Subscription Api Failure' );

		$paypalResponse->setSuccessHttpStatus( 200 );

		try {
			$response = wp_remote_get(
				$this->payPalClient->getApiUrl( "v1/billing/subscriptions/{$paypalSubscriptionId}" ),
				[
					'headers' => give( HttpHeader::class )->getHeaders(),
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
	 * Return query to create subscription in array format.
	 *
	 * @since 1.11.0
	 *
	 * @param  PlanModel  $plan
	 * @param  Subscriber  $subscriber
	 * @param array $redirectUrls
	 *
	 * @return array
	 */
	private function getCreateSubscriptionQuery( PlanModel $plan, Subscriber $subscriber, $redirectUrls ){
		$query = [
			'plan_id'        => $plan->id,
			'subscriber' => [
				'name' => [
					'given_name' => $subscriber->firstName,
					'surname' => $subscriber->lastName
				],
				'email_address' => $subscriber->emailAddress
			],
			'application_context' => [
				'locale' => str_replace( '_', '-', get_locale() ),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action' => 'SUBSCRIBE_NOW',
			]
		];

		if( array_key_exists( 'return', $redirectUrls ) ) {
			$query['application_context']['return_url'] = $redirectUrls['return'];
		}

		if( array_key_exists( 'cancel', $redirectUrls ) ) {
			$query['application_context']['cancel_url'] = $redirectUrls['cancel'];
		}

		if ( $subscriber->hasCard() ) {
			// @see https://developer.paypal.com/docs/api/subscriptions/v1/#definition-payment_source.card
			$query['subscriber']['payment_source']['card'] = [
				'number'        => $subscriber->card->number,
				'name'          => $subscriber->card->name,
				'expiry'        => $subscriber->card->expiry,
				'security_code' => $subscriber->card->cvc,
			];

			unset( $query['application_context'] );
		}

		return $query;
	}

	/**
	 * Get subscription bu paypal subscription id.
	 *
	 * @since 1.11.0
	 *
	 * @param string $id
	 *
	 * @return Give_Subscription
	 */
	public static function getSubscriptionByPayPalId( $id ){
		return new Give_Subscription( $id, true );
	}

	/**
	 * Update donation status.
	 *
	 * @since 1.11.0
	 *
	 * @param string $paypalSubscriptionId
	 * @param string $status Allowed values are "cancel", "suspend", "activate".
	 *
	 * @throws Exception
	 */
	public function updateStatus( $paypalSubscriptionId, $status ){
		try {
			/* @var PayPalResponse $paypalResponse */
			$paypalResponse = give( PayPalResponse::class );
			$paypalResponse->setLogTitle( 'PayPal Commerce Subscription Api Failure' )->setSuccessHttpStatus( 204 );

			$response = wp_remote_post(
				$this->payPalClient->getApiUrl(sprintf(
					'v1/billing/subscriptions/%1$s/%2$s',
					$paypalSubscriptionId,
					$status
				)),
				[ 'headers' => give( HttpHeader::class )->getHeaders(), ]
			);

			$paypalResponse->setData( $response )->isSuccessful();
		} catch ( Exception $ex ) {
			throw new $ex;
		}
	}
}
