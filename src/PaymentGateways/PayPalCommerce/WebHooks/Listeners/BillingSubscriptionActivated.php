<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionSuspended
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionActivated extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.ACTIVATED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {
		if( 'active' !== $this->subscription->status ) {
			give_insert_subscription_note( $this->subscription->id, esc_html__( 'Subscription activated in PayPal', 'give-recurring' ) );
			$this->subscription->update(['status' => 'active'] );
		}
	}
}
