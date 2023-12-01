<?php

namespace GiveRecurring\PaymentGatewayModules\EventHandlers;

use Exception;
use Give\Subscriptions\Repositories\SubscriptionRepository;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveRecurring\PaymentGatewayModules\Actions\UpdateSubscriptionStatus;

/**
 * @since 2.2.0
 */
class SubscriptionActive
{
    /**
     * @since 2.2.0
     *
     * @throws Exception
     */
    public function setStatus(string $gatewaySubscriptionId, string $message = '')
    {
        $subscription = give(SubscriptionRepository::class)->getByGatewaySubscriptionId($gatewaySubscriptionId);
        $subscriptionInitialDonation = $subscription->initialDonation();

        if ($subscription &&
            !empty($subscriptionInitialDonation->gatewayTransactionId) &&
            $subscriptionInitialDonation->status->isComplete()) {
            (new UpdateSubscriptionStatus())(
                $subscription,
                SubscriptionStatus::ACTIVE(),
                $gatewaySubscriptionId,
                $message
            );
        }

        wp_die();
    }
}
