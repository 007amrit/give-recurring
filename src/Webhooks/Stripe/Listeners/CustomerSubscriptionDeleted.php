<?php

namespace GiveRecurring\Webhooks\Stripe\Listeners;

use Give\PaymentGateways\Gateways\Stripe\Webhooks\StripeEventListener;
use Give_Subscription;
use Stripe\Event;
use Stripe\Subscription;

/**
 * Class CustomerSubscriptionDeleted
 * @package GiveRecurring\Webhooks\Stripe\Listeners
 *
 * @since 1.12.6
 */
class CustomerSubscriptionDeleted extends StripeEventListener
{

    /**
     * Processes customer.subscription.deleted event.
     *
     * @since 1.12.6
     *
     * @param Event $event Stripe Event received via webhooks.
     *
     * @return void
     */
    public function processEvent(Event $event)
    {
        /**
         * @since 2.4.0
         */
        do_action('give_recurring_stripe_processing_customer_subscription_deleted', $event);

        /* @var Subscription $stripeSubscription */
        $stripeSubscription = $event->data->object;

        /**
         * This action hook will be used to extend processing the customer subscription deleted event.
         *
         * @since 1.9.4
         */
        do_action('give_recurring_stripe_process_customer_subscription_deleted', $event);

        $profile_id = $stripeSubscription->id;
        $subscription = new Give_Subscription($profile_id, true);

        // Sanity Check: Don't cancel already completed or cancelled subscriptions or empty subscription objects.
        if (
            !$subscription->id ||
            in_array($subscription->status, ['completed', 'cancelled'])
        ) {
            return;
        }

        $subscription->cancel();
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    protected function getFormId(Event $event)
    {
        /* @var Subscription $stripeSubscription */
        $stripeSubscription = $event->data->object;

        return (new Give_Subscription($stripeSubscription->id, true))->form_id;
    }
}
