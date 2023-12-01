<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class BillingSubscriptionCancelled
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class BillingSubscriptionCancelled extends BillingSubscription {
	const WEBHOOK_ID = 'BILLING.SUBSCRIPTION.CANCELLED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {
		if( 'cancelled' !== $this->subscription->status ) {
			$this->subscription->cancel();
			give_insert_subscription_note( $this->subscription->id, esc_html__( 'Subscription cancelled in PayPal', 'give-recurring' ) );
		}
	}
}
