<?php

namespace GiveRecurring\PaymentGateways\Stripe;

use Give\Donations\Models\Donation;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionAmountEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionDashboardLinkable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionPaymentMethodEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionTransactionsSynchronizable;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\PaymentGateways\Gateways\Stripe\Actions\GetOrCreateStripeCustomer;
use Give\PaymentGateways\Gateways\Stripe\Traits\CanSetupStripeApp;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\PaymentMethod;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\PaymentGateways\DataTransferObjects\SubscriptionDto;
use GiveRecurring\PaymentGateways\Stripe\Actions\RetrieveOrCreatePlan;
use GiveRecurring\PaymentGateways\Stripe\Actions\SubscribeStripeCustomerToPlanWithCard;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanCancelStripeSubscription;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanHandleSecureCardAuthenticationRedirect;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanLinkStripeSubscriptionGatewayId;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanUpdateStripeSubscriptionAmount;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanUpdateStripeSubscriptionPaymentMethod;
use Stripe\Exception\ApiErrorException;

/**
 * @since 2.0.0
 */
class CreditCardGatewayModule extends SubscriptionModule implements SubscriptionDashboardLinkable,
                                                                    SubscriptionAmountEditable,
                                                                    SubscriptionPaymentMethodEditable,
                                                                    SubscriptionTransactionsSynchronizable
{
    use CanSetupStripeApp;
    use CanCancelStripeSubscription;
    use CanUpdateStripeSubscriptionAmount;
    use CanLinkStripeSubscriptionGatewayId;
    use CanUpdateStripeSubscriptionPaymentMethod;
    use CanHandleSecureCardAuthenticationRedirect;

    /**
     * @since 2.0.0
     * @var string[]
     */
    public $secureRouteMethods = [
        'handleSecureCardAuthenticationRedirectForSubscription',
    ];

    /**
     * @inheritDoc
     *
     * @param PaymentMethod $paymentMethod
     *
     * @throws Exception
     * @throws ApiErrorException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ): GatewayCommand {
        $paymentMethod = $gatewayData['stripePaymentMethod'];
        $stripePaymentMethodObject = (new \Give_Stripe_Payment_Method())->retrieve($paymentMethod->id());
        $stripeCustomer = (new GetOrCreateStripeCustomer)(
            $donation,
            $stripePaymentMethodObject->id
        );

        $stripePlan = give(RetrieveOrCreatePlan::class)->handle(
            SubscriptionDto::fromArray(
                [
                    'formId' => $donation->formId,
                    'priceId' => $donation->levelId,
                    'recurringDonationAmount' => $donation->amount,
                    'period' => $subscription->period->getValue(),
                    'frequency' => $subscription->frequency,
                    'currencyCode' => $donation->amount->getCurrency(),
                ]
            )
        );

        $subscribeStripeCustomerToPlan = (new SubscribeStripeCustomerToPlanWithCard)(
            $stripeCustomer->customer_data,
            $donation,
            $stripeCustomer->attached_payment_method,
            $stripePlan->id
        );

        return new SubscriptionComplete(
            $subscribeStripeCustomerToPlan->getGatewayTransactionId(),
            $subscribeStripeCustomerToPlan->getGatewaySubscriptionId()
        );
    }

    /**
     * @since 2.0.0
     *
     * @inheritDoc
     */
    public function synchronizeSubscription(Subscription $subscription)
    {
        // TODO: Implement synchronizeSubscription() method.
        // We are processing sync subscription request with legacy code (MockLegacyGiveRecurringGateway::addSyncSubscriptionActionHook)
    }
}
