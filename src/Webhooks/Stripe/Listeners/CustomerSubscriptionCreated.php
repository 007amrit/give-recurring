<?php

namespace GiveRecurring\Webhooks\Stripe\Listeners;

use Give\PaymentGateways\Gateways\Stripe\Webhooks\StripeEventListener;
use Give_Subscription;
use Stripe\Event;
use Stripe\Subscription;

/**
 * Class CustomerSubscriptionCreated
 * @package GiveRecurring\Webhooks\Stripe\Listeners
 *
 * @since 1.12.6
 */
class CustomerSubscriptionCreated extends StripeEventListener
{

    /**
     * @param Event $event
     *
     * @return void
     */
    public function processEvent(Event $event)
    {
        /**
         * @since 2.4.0
         */
        do_action('give_recurring_stripe_processing_customer_subscription_created', $event);

        $subscription = $this->getSubscription($event);

        if (!$this->isSubscriptionProcessable($subscription->id, $subscription->status)) {
            return;
        }

        give_recurring_update_subscription_status($subscription->id);

        if ('pending' === get_post_status($subscription->parent_payment_id)) {
            give_update_payment_status($subscription->parent_payment_id, 'processing');
        }
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    protected function getFormId(Event $event)
    {
        return $this->getSubscription($event)->form_id;
    }

    /**
     * @param Event $event
     *
     * @return Give_Subscription
     */
    private function getSubscription(Event $event)
    {
        /* @var Subscription $stripeSubscription */
        $stripeSubscription = $event->data->object;

        $subscription = new Give_Subscription(
            $stripeSubscription->id,
            true
        );

        if (!$subscription->id && $this->hasDonationIdInInvoiceMetadata($stripeSubscription)) {
            $donationMetaData = $stripeSubscription->metadata->toArray();
            $donationId = !empty($donationMetaData['Donation Post ID']) ? $donationMetaData['Donation Post ID'] : 0;
            $subscription = give_recurring_get_subscription_by('payment', $donationId);
        }

        return $subscription;
    }

    /**
     * @since 1.15.0
     *
     * @param int $subscriptionId
     * @param string $subscriptionStatus
     *
     * @return bool
     */
    private function isSubscriptionProcessable($subscriptionId, $subscriptionStatus)
    {
        return $subscriptionId && !in_array($subscriptionStatus, ['active', 'cancelled', 'completed', 'expired']);
    }

    /**
     * @return bool
     */
    private function hasDonationIdInInvoiceMetadata(Subscription $stripeSubscription)
    {
        return $stripeSubscription->metadata &&
            array_key_exists('Donation Post ID', $stripeSubscription->metadata->toArray());
    }
}
