<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\Exceptions\Primitives\Exception;
use GiveRecurring\PaymentGateways\Stripe\Contracts\SubscribeStripeCustomerToPlan;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\Subscription;

/**
 * @since 2.0.0
 */
class SubscribeStripeCustomerToPlanWithCard extends SubscribeStripeCustomerToPlan
{
    /**
     * Subscribes a Stripe Customer to a plan.
     *
     * @since 2.0.0
     * @throws ApiErrorException
     * @throws Exception
     */
    public function __invoke(
        Customer $stripeCustomer,
        Donation $donation,
        PaymentMethod $paymentMethod,
        string $planId
    ): self {
        // Get metadata.
        $metadata = give_stripe_prepare_metadata($donation->id);
        $args = [
            'plan' => $planId,
            'metadata' => $metadata,
            'default_payment_method' => $paymentMethod,
            'application_fee_percent' => number_format( (float) give_get_option( 'application_fee' ), 2, '.', '' ),	// FIX: application_fee for connected account
        ];

        /* @var Subscription $stripeSubscription */
        $stripeSubscription = $stripeCustomer->subscriptions->create(
            $args,
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

        if (Subscription::STATUS_INCOMPLETE === $stripeSubscription->status) {
            $subscription = $donation->subscription;
            $subscription->gatewaySubscriptionId = $stripeSubscription->id;
            $subscription->save();

            $this->processAdditionalPaymentAuthentication(
                $donation,
                $paymentMethod,
                $stripePaymentIntent
            );
        }

        $this->gatewaySubscriptionId = $stripeSubscription->id;
        $this->gatewayTransactionId = $stripeInvoice->charge;

        return $this;
    }
}