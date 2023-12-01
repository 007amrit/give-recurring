<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionUpdated
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionUpdated extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.UPDATED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {}
}
