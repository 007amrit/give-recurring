<?php

namespace GiveRecurring\PaymentGateways\Stripe;

use Give\Donations\Models\Donation;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\SubscriptionProcessing;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionAmountEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionDashboardLinkable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionTransactionsSynchronizable;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\PaymentGateways\Gateways\Stripe\Actions\GetOrCreateStripeCustomer;
use Give\PaymentGateways\Gateways\Stripe\Traits\CanSetupStripeApp;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToCreateStripePlan;
use GiveRecurring\PaymentGateways\DataTransferObjects\SubscriptionDto;
use GiveRecurring\PaymentGateways\Stripe\Actions\RetrieveOrCreatePlan;
use GiveRecurring\PaymentGateways\Stripe\Actions\SubscribeStripeCustomerToPlanWithSEPA;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanCancelStripeSubscription;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanLinkStripeSubscriptionGatewayId;
use GiveRecurring\PaymentGateways\Stripe\Traits\CanUpdateStripeSubscriptionAmount;
use Stripe\Exception\ApiErrorException;

/**
 * @since 2.0.0
 */
class SEPAGatewayModule extends SubscriptionModule implements SubscriptionAmountEditable,
                                                              SubscriptionTransactionsSynchronizable,
                                                              SubscriptionDashboardLinkable
{
    use CanSetupStripeApp;
    use CanCancelStripeSubscription;
    use CanUpdateStripeSubscriptionAmount;
    use CanLinkStripeSubscriptionGatewayId;

    /**
     * @since 2.0.0
     *
     * @throws UnableToCreateStripePlan
     * @throws Exception|ApiErrorException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ): GatewayCommand {
        $paymentMethod = $gatewayData['stripePaymentMethod'];
        $stripeCustomer = (new GetOrCreateStripeCustomer())($donation, $paymentMethod->id());
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

        $subscribeStripeCustomerToPlanWithSEPA = (new SubscribeStripeCustomerToPlanWithSEPA)(
            $stripeCustomer->customer_data,
            $donation,
            $stripeCustomer->attached_payment_method,
            $stripePlan->id
        );

        return new SubscriptionProcessing($subscribeStripeCustomerToPlanWithSEPA->getGatewaySubscriptionId());
    }

    /**
     * @since 2.0.0
     */
    public function synchronizeSubscription(Subscription $subscription)
    {
        // TODO: Implement synchronizeSubscription() method.
        // We are processing sync subscription request with legacy code (MockLegacyGiveRecurringGateway::addSyncSubscriptionActionHook)
    }
}
