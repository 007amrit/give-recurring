<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\DonationSummary;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\CheckoutSession;
use Stripe\Customer;

/**
 * @since 2.0.0
 */
class SubscribeStripeCustomerToPlanWithCheckoutRedirect
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
        string $planId
    ): RedirectOffsite {
        // Fetch whether the billing address collection is enabled in admin settings or not.
        $is_billing_enabled = give_is_setting_enabled(give_get_option('stripe_collect_billing'));

        $session_args = [
            'customer' => $stripeCustomer->id,
            'client_reference_id' => $donation->purchaseKey,
            'billing_address_collection' => $is_billing_enabled ? 'required' : 'auto',
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'subscription_data' => [
                'items' => [
                    [
                        'plan' => $planId,
                        'quantity' => 1,
                    ]
                ],
                'metadata' => give_stripe_prepare_metadata($donation->id),
            ],
            'success_url' => give_get_success_page_uri(),
            'cancel_url' => give_get_failed_transaction_uri(),
        ];

        /* @var CheckoutSession $session */
        $session = give(CheckoutSession::class)->create($session_args);


        DonationNote::create([
            'donationId' => $donation->id,
            'content' => sprintf(
            /* translators: 1. Stripe payment intent id */
                esc_html__('Stripe Checkout Session ID: %1$s', 'give-recurring'),
                $session->id()
            )
        ]);

        give_update_meta($donation->id, '_give_stripe_checkout_session_id', $session->id());
        give_update_meta(
            $donation->id,
            '_give_stripe_donation_summary',
            (new DonationSummary($donation))->getSummary()
        );

        return new RedirectOffsite(
            esc_url_raw(
                add_query_arg(
                    [
                        'action' => 'checkout_processing',
                        'session' => $session->id(),
                        'id' => $donation->formId,
                    ],
                    home_url()
                )
            )
        );
    }
}
