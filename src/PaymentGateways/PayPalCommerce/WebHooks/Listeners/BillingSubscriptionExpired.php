<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionExpired
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionExpired extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.EXPIRED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {
		if( 'expired' !== $this->subscription->status ) {
			$this->subscription->expire();
			give_insert_subscription_note( $this->subscription->id, esc_html__( 'Subscription expired in PayPal', 'give-recurring' ) );
		}
	}
}
