<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;

/**
 * @since 2.0.0
 */
trait CanCancelStripeSubscription
{
    /**
     * @since 2.0.0
     *
     * @inerhitDoc
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription)
    {
        try {
            $this->setupStripeApp($subscription->donationFormId);
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->gatewaySubscriptionId);
            $stripeSubscription->cancel();

            $subscription->status = SubscriptionStatus::CANCELLED();
            $subscription->save();
        } catch (\Exception $exception) {
            throw new PaymentGatewayException(
                sprintf(
                    'Unable to cancel subscription with Stripe. %s',
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }
}
