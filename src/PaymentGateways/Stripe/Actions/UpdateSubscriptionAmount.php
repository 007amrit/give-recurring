<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Exception;
use Give\Framework\Support\ValueObjects\Money;
use Give\PaymentGateways\Gateways\Stripe\Traits\CanSetupStripeApp;
use Give\Subscriptions\Models\Subscription;
use Give_Subscription as LegacySubscription;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToUpdateSubscriptionAmountOnStripe;
use GiveRecurring\PaymentGateways\DataTransferObjects\SubscriptionDto;
use Stripe\Subscription as StripeSubscriptionApi;

use function give_get_price_id;

/**
 * Class StripeUpdateSubscriptionAmountService
 *
 * @package GiveRecurring\PaymentGateways\Stripe\Actions
 *
 * @since 1.12.6
 */
class UpdateSubscriptionAmount
{
    use CanSetupStripeApp;

    /**
     * @var RetrieveOrCreatePlan
     */
    private $stripePlanCreatorAndRetrieverService;

    /**
     * StripeUpdateSubscriptionAmountService constructor.
     *
     * @since 1.12.6
     *
     * @param RetrieveOrCreatePlan $stripePlanCreatorAndRetrieverService
     *
     */
    public function __construct(RetrieveOrCreatePlan $stripePlanCreatorAndRetrieverService)
    {
        $this->stripePlanCreatorAndRetrieverService = $stripePlanCreatorAndRetrieverService;
    }

    /**
     * @since 2.0.0
     *
     * @throws UnableToUpdateSubscriptionAmountOnStripe
     */
    public function __invoke(Subscription $subscription, Money $newRenewalAmount)
    {
        $this->handle(new LegacySubscription($subscription->id), $newRenewalAmount->formatToDecimal());
    }

    /**
     * Update Stripe Subscription plan.
     *
     * @since 1.12.6
     *
     * @throws UnableToUpdateSubscriptionAmountOnStripe
     */
    public function handle(LegacySubscription $legacySubscription, string $renewalAmount)
    {
        try {
            $this->setupStripeApp($legacySubscription->form_id);
            $newRecurringDonationAmount = Money::fromDecimal(
                $renewalAmount,
                give_get_payment_currency_code($legacySubscription->parent_payment_id)
            );

            $stripePlan = $this->stripePlanCreatorAndRetrieverService->handle(
                SubscriptionDto::fromGiveSubscriptionObject(
                    $legacySubscription,
                    [
                        'recurringDonationAmount' => $newRecurringDonationAmount,
                        'priceId' => give_get_price_id($legacySubscription->form_id, $newRecurringDonationAmount->getAmount())
                    ]
                )
            );

            $stripeSubscription = StripeSubscriptionApi::retrieve($legacySubscription->profile_id);

            StripeSubscriptionApi::update(
                $legacySubscription->profile_id,
                [
                    'items' => [
                        [
                            'id' => $stripeSubscription->items->data[0]->id,
                            'plan' => $stripePlan->id,
                        ],
                    ],
                    'prorate' => false,
                ]
            );
        } catch (Exception $e) {
            throw new UnableToUpdateSubscriptionAmountOnStripe($e->getMessage());
        }
    }
}
