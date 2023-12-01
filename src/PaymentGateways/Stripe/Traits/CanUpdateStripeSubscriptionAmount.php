<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToUpdateSubscriptionAmountOnStripe;
use GiveRecurring\PaymentGateways\Stripe\Actions\UpdateSubscriptionAmount;

/**
 * @since 2.0.0
 */
trait CanUpdateStripeSubscriptionAmount
{
    /**
     * @since 2.0.0
     *
     * @inheritDoc
     * @throws UnableToUpdateSubscriptionAmountOnStripe
     */
    public function updateSubscriptionAmount(Subscription $subscription, Money $newRenewalAmount)
    {
        give(UpdateSubscriptionAmount::class)($subscription, $newRenewalAmount);
    }
}
