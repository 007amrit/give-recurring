<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

use Give\PaymentGateways\PayPalCommerce\Webhooks\Listeners\PayPalCommerce\PaymentEventListener;
use Give_Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\SubscriptionProcessor;

/**
 * Class BillingSubscriptionSuspended
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
abstract class BillingSubscription extends PaymentEventListener {
	/**
	 * @since 1.11.0
	 * @var Give_Subscription
	 */
	protected $subscription;

	/**
	 * @inheritDoc
	 */
	public function processEvent( $event ) {
		$this->subscription = Subscription::getSubscriptionByPayPalId($event->resource->id);

		// Check for subscription ID.
		if ( ! $this->subscription->id ) {
			return;
		}

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
