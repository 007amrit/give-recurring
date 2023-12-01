<?php

namespace GiveRecurring\PaymentGatewayModules\Modules;

use Exception;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Subscriptions\Models\Subscription;

/**
 * @since 2.0.0 update module to stub implemented interface
 * @since 1.14.0
 */
class TestGatewaySubscriptionModule extends SubscriptionModule
{
    /**
     * @inheritDoc
     */
    public function canSyncSubscriptionWithPaymentGateway(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canUpdateSubscriptionAmount(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canUpdateSubscriptionPaymentMethod(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData = null
    ): SubscriptionComplete {
        $profileId = md5($donation->purchaseKey . $donation->id);
        $transactionId = md5(uniqid(mt_rand(), true));

        return new SubscriptionComplete($transactionId, $profileId);
    }

    /**
     * @since 2.0.0
     *
     * @param Subscription $subscription
     *
     * @throws Exception
     */
    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->cancel();
    }
}
