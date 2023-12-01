<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\Exceptions\Primitives\Exception;
use GiveRecurring\PaymentGateways\Stripe\Contracts\SubscribeStripeCustomerToPlan;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanSetupStripeIntent;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Subscription;

/**
 * @since 2.0.0
 */
class SubscribeStripeCustomerToPlanWithBECS extends SubscribeStripeCustomerToPlan
{
    use CanSetupStripeIntent;

    /**
     * Subscribes a Stripe Customer to a plan.
     *
     * @since 2.0.0
     * @throws Exception
     */
    public function __invoke(
        Customer $stripeCustomer,
        Donation $donation,
        PaymentMethod $paymentMethod,
        string $planId
    ): self {
        $metadata = give_stripe_prepare_metadata($donation->id);
        $stripeIntent = $this->setupStripeIntent(
            $stripeCustomer,
            $paymentMethod,
            $metadata,
            ['au_becs_debit']
        );

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => sprintf(
            /* translators: 1. Stripe intent id */
                esc_html__('Stripe Setup Intent ID: %1$s', 'give-recurring'),
                $stripeIntent->id
            )
        ]);

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => sprintf(
            /* translators: 1. Stripe manadate id */
                esc_html__('Stripe Mandate ID: %1$s', 'give-recurring'),
                $stripeIntent->mandate
            )
        ]);

        /* @var Subscription $stripeSubscription */
        $stripeSubscription = $stripeCustomer->subscriptions->create(
            [
                'metadata' => $metadata,
                'plan' => $planId,
                'payment_behavior' => 'allow_incomplete',
                'default_payment_method' => $paymentMethod->id,
            ],
            give_stripe_get_connected_account_options()
        );

        $stripeInvoice = $this->getInvoice($stripeSubscription);
        $stripePaymentIntent = $this->getPaymentIntent($stripeInvoice);

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => sprintf(
            /* translators: 1. Stripe payment intent id */
                esc_html__('Stripe Payment Invoice ID: %1$s', 'give-recurring'),
                $stripePaymentIntent->id
            )
        ]);

        $this->gatewaySubscriptionId = $stripeSubscription->id;
        $this->gatewayTransactionId = $stripeInvoice->charge;

        return $this;
    }
}
