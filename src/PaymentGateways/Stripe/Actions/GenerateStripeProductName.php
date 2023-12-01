<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use GiveRecurring\PaymentGateways\DataTransferObjects\SubscriptionDto;

class GenerateStripeProductName {
     /**
     * @since 2.1.2 migrated this logic into an action
     * @since 1.12.6
     */
    public function __invoke(SubscriptionDto $subscriptionDto): string
    {
        return sprintf(
            '%1$s (%2$s)',
            give_recurring_generate_subscription_name(
                $subscriptionDto->formId,
                $subscriptionDto->priceId
            ),
            $subscriptionDto->currencyCode
        );
    }
}
