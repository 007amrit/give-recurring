<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Exception;
use Give\PaymentGateways\Exceptions\InvalidPropertyName;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToCreateStripePlan;
use GiveRecurring\PaymentGateways\DataTransferObjects\SubscriptionDto;
use GiveRecurring\PaymentGateways\Stripe\Repositories\Plan;
use Stripe\Exception\ApiErrorException;
use Stripe\Plan as StripePlan;
use Stripe\Product as StripeProduct;

/**
 * @since 1.12.6
 */
class RetrieveOrCreatePlan
{
    /**
     * @since 2.1.2 always create new stripe plans
     * @since 1.12.6
     *
     * @throws UnableToCreateStripePlan
     */
    public function handle(SubscriptionDto $subscriptionDto): StripePlan
    {
        $stripeProductName = (new GenerateStripeProductName())(
            $subscriptionDto
        );

        return $this->createNewStripePlan($subscriptionDto, $stripeProductName);
    }

    /**
     * Creates a Stripe Plan using the API.
     *
     * @since 2.1.2 remove $stripePlanId param so Stripe can generate the ID for us.
     * @since 1.12.6
     *
     * @throws UnableToCreateStripePlan
     */
    private function createNewStripePlan(
        SubscriptionDto $subscriptionDto,
        string $stripeProductName
    ): StripePlan {
        $args = [
            'amount' => $subscriptionDto->recurringDonationAmount->formatToMinorAmount(),
            'interval' => $subscriptionDto->period,
            'interval_count' => $subscriptionDto->frequency,
            'currency' => $subscriptionDto->currencyCode,
        ];

        try {
            $args['product'] = $this->createStripeProduct($stripeProductName);
            return give(Plan::class)->create($args);
        } catch (Exception $e) {
            throw new UnableToCreateStripePlan($e->getMessage());
        }
    }

    /**
     * @since 1.12.6
     * @throws ApiErrorException|InvalidPropertyName
     */
    private function createStripeProduct(string $stripeProductName): StripeProduct
    {
        return StripeProduct::create([
            'name' => $stripeProductName,
            'statement_descriptor' => give_stripe_get_statement_descriptor(),
            'type' => 'service',
        ]);
    }
}
