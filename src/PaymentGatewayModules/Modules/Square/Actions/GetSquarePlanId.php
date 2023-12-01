<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\CreatePlan;

/**
 * @see https://developer.squareup.com/docs/subscriptions-api/walkthrough#step-2-create-a-subscription-plan
 *
 * @since 2.3.0
 */
class GetSquarePlanId
{
    /**
     * @since 2.3.0
     *
     * @throws Exception
     */
    public function __invoke(
        Subscription $subscription,
        string $idempotencyKey,
        string $squareCustomerId,
        string $squarePlanName
    ): string {
        $phase1 = [
            "cadence" => $this->getSquareCadence($subscription),
            "recurring_price_money" => [
                "amount" => (int)$subscription->amount->formatToMinorAmount(),
                "currency" => $subscription->amount->getCurrency()->getCode(),
            ],
        ];

        /**
         * The number of cadences the phase lasts. If not set, the phase never ends. Only the last phase can be indefinite.
         *
         * @see https://developer.squareup.com/reference/square/objects/SubscriptionPhase#definition__property-periods
         */
        if ($subscription->installments > 0) {
            $phase1["periods"] = $subscription->installments;
        }

        $phases[] = $phase1;

        $subscriptionPlanData = [
            "name" => $squarePlanName,
            "phases" => $phases,
        ];

        $object = [
            "type" => "SUBSCRIPTION_PLAN",
            "id" => "#plan",
            "subscription_plan_data" => $subscriptionPlanData,
        ];

        $requestData = [
            "idempotency_key" => $idempotencyKey,
            "object" => $object,
        ];

        return (new CreatePlan())($requestData)->id;
    }

    /**
     * Identifies the billing interval, such as weekly or monthly.
     *
     * @see https://developer.squareup.com/reference/square/objects/SubscriptionPhase#definition__property-cadence
     *
     * @since 2.3.0
     */
    private function getSquareCadence(Subscription $subscription): string
    {
        $cadence = '';

        if ($subscription->period->isDay()) {
            $cadence = 'DAILY';
        }

        if ($subscription->period->isWeek()) {
            $cadence = 'WEEKLY';
        }

        if ($subscription->period->isMonth()) {
            $cadence = 'MONTHLY';
        }

        if ($subscription->period->isQuarter()) {
            $cadence = 'QUARTERLY';
        }

        if ($subscription->period->isYear()) {
            $cadence = 'ANNUAL';
        }

        return $cadence;
    }
}
