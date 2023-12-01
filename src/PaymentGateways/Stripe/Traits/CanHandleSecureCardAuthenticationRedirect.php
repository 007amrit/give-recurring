<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\InvalidArgumentException;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\GenerateDonationReceiptPageUrl;
use Give\Subscriptions\Repositories\SubscriptionRepository;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use Stripe\ErrorObject;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\PaymentIntent;

/**
 * @since 2.0.0
 */
trait CanHandleSecureCardAuthenticationRedirect
{
    /**
     * @since 2.1.0 Change the method to protected instead of private to prevent fatal errors
     *
     * @since      2.0.0
     *
     * @throws ApiErrorException
     * @throws \Exception
     */
    protected function handleSecureCardAuthenticationRedirectForSubscription(array $queryParams): RedirectResponse
    {
        $donationId = (int)$queryParams['donation-id'];
        $getData = give_clean(filter_input_array(INPUT_GET));
        $donation = Donation::find($donationId);

        if (empty($getData['payment_intent']) || ! $donation) {
            throw new InvalidArgumentException(
                esc_html__(
                    'We can not process this request because it does not container valid Stripe payment intent id.',
                    'give-recurring'
                )
            );
        }

        $this->setupStripeApp($donation->formId);
        $stripePaymentIntentId = $getData['payment_intent'];
        $stripePaymentIntent = PaymentIntent::retrieve($stripePaymentIntentId);

        if (
            $stripePaymentIntent->last_payment_error &&
            ErrorObject::CODE_PAYMENT_INTENT_AUTHENTICATION_FAILURE === $stripePaymentIntent->last_payment_error->code
        ) {
            $stripeInvoice = Invoice::retrieve($stripePaymentIntent->invoice);
            $stripeSubscription = \Stripe\Subscription::retrieve($stripeInvoice->subscription);

            if (
                \Stripe\Subscription::STATUS_INCOMPLETE === $stripeSubscription->status &&
                Invoice::STATUS_OPEN === $stripeInvoice->status
            ) {
                $subscription = give(SubscriptionRepository::class)
                    ->getByGatewaySubscriptionId($stripeSubscription->id);
                $subscription->status = SubscriptionStatus::CANCELLED();
                $subscription->save();

                $donation->status = DonationStatus::FAILED();
                $donation->save();
            }
        }

        return new RedirectResponse((new GenerateDonationReceiptPageUrl)($donationId));
    }
}
