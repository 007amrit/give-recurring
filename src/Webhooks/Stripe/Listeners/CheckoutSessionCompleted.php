<?php

namespace GiveRecurring\Webhooks\Stripe\Listeners;

use Give\Donations\Models\Donation;
use Give\PaymentGateways\Gateways\Stripe\Webhooks\StripeEventListener;
use Stripe\Checkout\Session;
use Stripe\Event;

/**
 * Class CheckoutSessionCompleted
 * @package GiveRecurring\Webhooks\Stripe\Listeners
 *
 * @since 1.12.6
 */
class CheckoutSessionCompleted extends StripeEventListener
{

    /**
     * Processes checkout.session.completed event.
     *
     * @since 1.12.6
     *
     * @param Event $event Stripe Event received via webhooks.
     */
    public function processEvent(Event $event)
    {
        /**
         * @since 2.4.0
         */
        do_action('give_recurring_stripe_processing_checkout_session_completed', $event);

        /* @var Session $checkoutSession */
        $checkoutSession = $event->data->object;

        $donation = $this->getDonation($event);

        $subscription = give_recurring_get_subscription_by('payment', $donation->id);

        if (!$subscription || !$subscription->id) {
            return;
        }

        $subscription->update([
            'profile_id' => $checkoutSession->subscription,
        ]);

        give_recurring_update_subscription_status($subscription->id);
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    protected function getDonation(Event $event)
    {
        /* @var Session $checkoutSession */
        $checkoutSession = $event->data->object;

        $donationId = Give()->payment_meta->get_column_by(
            'donation_id',
            'meta_value',
            $checkoutSession->id
        );

        if (!$donationId) {
            return null;
        }

        return Donation::find((int)$donationId);
    }
}
