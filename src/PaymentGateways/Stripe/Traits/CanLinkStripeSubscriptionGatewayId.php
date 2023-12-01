<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Give\Donations\Models\Donation;
use Give\Donations\Repositories\DonationRepository;
use Give\Subscriptions\Models\Subscription;

/**
 * @since 2.0.0
 */
trait CanLinkStripeSubscriptionGatewayId
{
    /**
     * @since 2.1.0 If the subscription transaction id isn't set, get the donation by initial donation id
     * @since      2.0.0
     * @inerhitDoc
     */
    public function gatewayDashboardSubscriptionUrl(Subscription $subscription): string
    {
        /* @var Donation $donation */
        $donation = $subscription->transactionId ?
            give(DonationRepository::class)->getByGatewayTransactionId($subscription->transactionId) :
            $subscription->initialDonation();

        $stripeDashboardUrl = 'live' === $donation->mode->getValue() ?
            'https://dashboard.stripe.com/' :
            'https://dashboard.stripe.com/test/';

        return esc_url("{$stripeDashboardUrl}subscriptions/$subscription->gatewaySubscriptionId");
    }
}
