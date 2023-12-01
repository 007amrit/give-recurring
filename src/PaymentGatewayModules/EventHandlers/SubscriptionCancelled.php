<?php

namespace GiveRecurring\PaymentGatewayModules\EventHandlers;

use Exception;
use Give\Subscriptions\Repositories\SubscriptionRepository;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveRecurring\PaymentGatewayModules\Actions\UpdateSubscriptionStatus;

/**
 * @since 2.2.0
 */
class SubscriptionCancelled
{
    /**
     * @since 2.2.0
     *
     * @throws Exception
     */
    public function setStatus(string $gatewaySubscriptionId, string $message = '')
    {
        $subscription = give(SubscriptionRepository::class)->getByGatewaySubscriptionId($gatewaySubscriptionId);

        if ($subscription) {
            (new UpdateSubscriptionStatus())(
                $subscription,
                SubscriptionStatus::CANCELLED(),
                $gatewaySubscriptionId,
                $message
            );
        }

        wp_die();
    }
}
