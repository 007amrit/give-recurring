<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionSuspended
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionSuspended extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.SUSPENDED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {
		if( 'suspended' !== $this->subscription->status ) {
			$this->subscription->update(['status' => 'suspended']);
			give_insert_subscription_note( $this->subscription->id, esc_html__( 'Subscription suspended in PayPal', 'give-recurring' ) );
		}
	}
}
