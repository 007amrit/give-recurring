<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\Repositories\SubscriptionRepository;
use GiveRecurring\PaymentGateways\Stripe\Actions\GetStripeCustomer;

/**
 * @since 2.0.0
 */
trait CanUpdateStripeSubscriptionPaymentMethod
{
    /**
     * @since 2.0.0
     *
     * @throws PaymentGatewayException
     */
    public function updateSubscriptionPaymentMethod(Subscription $subscription, $gatewayData)
    {
        $this->setupStripeApp($subscription->donationFormId);
        $paymentMethod = $gatewayData['stripePaymentMethod'];

        try {
            $initialSubscriptionDonation = Donation::find(
                give(SubscriptionRepository::class)
                    ->getInitialDonationId($subscription->id)
            );
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->gatewaySubscriptionId);
            $stripeCustomer = (new GetStripeCustomer)($initialSubscriptionDonation, $paymentMethod->id());

            \Stripe\Subscription::update(
                $stripeSubscription->id,
                ['default_payment_method' => $stripeCustomer->attached_payment_method->id]
            );
        } catch (\Exception $e) {
            throw new PaymentGatewayException($e->getMessage());
        }
    }
}
