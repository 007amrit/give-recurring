<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

use Give\PaymentGateways\PayPalCommerce\Webhooks\Listeners\PayPalCommerce\PaymentEventListener;
use Give\Repositories\PaymentsRepository;
use Give_Payment;
use Give_Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\SubscriptionProcessor;

/**
 * Class PaymentSale
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
abstract class PaymentSale extends PaymentEventListener {
	/**
	 * @since 1.11.0
	 * @var Give_Subscription
	 */
	protected $subscription;

	/**
	 * @since 1.11.0
	 * @var Give_Payment
	 */
	protected $donation;

	/**
	 * @inheritDoc
	 */
	public function processEvent( $event ) {
		$paymentId = $event->resource->id;
		$this->subscription = Subscription::getSubscriptionByPayPalId($event->resource->billing_agreement_id);

		// Check for subscription ID.
		if ( ! $this->subscription->id ) {
			return;
		}

		$this->donation = give( PaymentsRepository::class )->getDonationByPayment( $paymentId );

		$class = SubscriptionProcessor::class;
		add_filter( "give_disable_hook-give_subscription_updated:{$class}@handleSubscriptionStatusChange", '__return_true' );

		$this->handle( $event );

		add_filter( "give_disable_hook-give_subscription_updated:{$class}@handleSubscriptionStatusChange", '__return_false' );
	}

	/**
	 * Handle subscription webhook event.
	 *
	 * @since 1.11.0
	 * @param $event
	 */
	abstract protected function handle( $event );
}
