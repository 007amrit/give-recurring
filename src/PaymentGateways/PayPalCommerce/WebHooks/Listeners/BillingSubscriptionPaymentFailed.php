<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionSuspended
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionPaymentFailed extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.PAYMENT.FAILED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {}
}
