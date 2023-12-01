<?php

namespace GiveRecurring\PaymentGateways\Stripe\Contracts;

use Give\Donations\Models\Donation;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Subscription;

/**
 * @since 2.0.0
 */
abstract class SubscribeStripeCustomerToPlan
{
    /* @var string $gatewaySubscriptionId */
    protected $gatewaySubscriptionId;

    /* @var string $gatewayTransactionId */
    protected $gatewayTransactionId;

    /**
     * @since 2.0.0
     */
    protected function getPaymentIntent(Invoice $invoice): PaymentIntent
    {
        return (new \Give_Stripe_Payment_Intent())->retrieve($invoice->payment_intent);
    }

    /**
     * @since 2.0.0
     */
    protected function getInvoice(Subscription $stripeSubscription): Invoice
    {
        return (new \Give_Stripe_Invoice())->retrieve($stripeSubscription->latest_invoice);
    }

    /**
     * @since 2.0.0
     * @throws ApiErrorException
     */
    protected function processAdditionalPaymentAuthentication(
        Donation $donation,
        PaymentMethod $paymentMethod,
        PaymentIntent $paymentIntent
    ) {
        $requestArgs = [
            'return_url' => $donation->gateway()
                ->generateSecureGatewayRouteUrl(
                    'handleSecureCardAuthenticationRedirectForSubscription',
                    $donation->id,
                    ['donation-id' => $donation->id]
                )
        ];

        if (
            give_stripe_is_source_type($paymentMethod, 'tok') ||
            give_stripe_is_source_type($paymentMethod, 'src')
        ) {
            $requestArgs['source'] = $paymentMethod;
        } elseif (give_stripe_is_source_type($paymentMethod, 'pm')) {
            $requestArgs['payment_method'] = $paymentMethod;
        }

        $paymentIntent->confirm($requestArgs);

        give_stripe_process_additional_authentication($donation->id, $paymentIntent);
    }

    /**
     * @since 2.0.0
     */
    public function getGatewaySubscriptionId(): string
    {
        return $this->gatewaySubscriptionId;
    }

    /**
     * @since 2.0.0
     */
    public function getGatewayTransactionId(): string
    {
        return $this->gatewayTransactionId;
    }
}
