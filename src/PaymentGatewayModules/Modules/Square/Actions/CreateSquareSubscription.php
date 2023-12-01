<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Donations\Models\Donation;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\CreateSubscription;
use GiveSquare\PaymentGateway\Actions\GetIdempotencyKey;
use GiveSquare\PaymentGateway\Actions\GetSquareCustomerId;
use GiveSquare\PaymentGateway\Actions\GetSquareLocationId;
use Square\Exceptions\ApiException;

/**
 * @see https://developer.squareup.com/docs/subscriptions-api/walkthrough#step-3-create-subscriptions
 *
 * @since 2.3.0
 */
class CreateSquareSubscription
{
    /**
     * @since 2.3.0
     *
     * @throws Exception|ApiException
     */
    public function __invoke(
        Subscription $subscription,
        Donation $donation,
        string $squareSourceId,
        string $squarePlanName
    ): string {
        $idempotencyKey = (new GetIdempotencyKey())($donation);
        $squareCustomerId = (new GetSquareCustomerId())($donation);

        $requestData = [
            "idempotency_key" => $idempotencyKey,
            'location_id' => (new GetSquareLocationId())($donation),
            'plan_id' => (new GetSquarePlanId())($subscription, $idempotencyKey, $squareCustomerId, $squarePlanName),
            'customer_id' => $squareCustomerId,
            'card_id' => (new GetSquareCardId())($idempotencyKey, $squareCustomerId, $squareSourceId),
        ];

        return (new CreateSubscription())($requestData)->id;
    }
}
