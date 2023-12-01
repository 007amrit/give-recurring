<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\Exceptions\Primitives\Exception;
use GiveRecurring\PaymentGateways\Stripe\Contracts\SubscribeStripeCustomerToPlan;
use Stripe\BankAccount;
use Stripe\Customer;
use Stripe\Subscription;

/**
 * @since 2.0.0
 */
class SubscribeStripeCustomerToPlanWithPlaid extends SubscribeStripeCustomerToPlan
{
    /**
     * Subscribes a Stripe Customer to a plan.
     *
     * @since 2.0.0
     * @throws Exception
     */
    public function __invoke(
        Customer $stripeCustomer,
        Donation $donation,
        BankAccount $paymentMethod,
        string $planId
    ): self {
        // Get metadata.
        $metadata = give_stripe_prepare_metadata($donation->id);
        $args = [
            'plan' => $planId,
            'metadata' => $metadata,
            'default_source' => $stripeCustomer->default_source
        ];

        $stripeSubscription = $stripeCustomer->subscriptions->create(
            $args,
            give_stripe_get_connected_account_options()
        );

        /* @var Subscription $stripeSubscription */
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
