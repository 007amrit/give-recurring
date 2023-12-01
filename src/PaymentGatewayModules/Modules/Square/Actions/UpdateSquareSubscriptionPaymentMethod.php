<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Subscriptions\Models\Subscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\UpdateSubscription;
use GiveSquare\PaymentGateway\Actions\GetSquareCustomerId;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;
use Square\Exceptions\ApiException;

/**
 * @see https://developer.squareup.com/reference/square/objects/Subscription
 *
 * @since 2.3.0
 */
class UpdateSquareSubscriptionPaymentMethod
{
    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|Exception|ApiException
     */
    public function __invoke(Subscription $subscription, string $squareSourceId)
    {
        $idempotencyKey = uniqid();
        $squareCustomerId = (new GetSquareCustomerId())($subscription->initialDonation());

        $newCardId = [
            'card_id' => (new GetSquareCardId())($idempotencyKey, $squareCustomerId, $squareSourceId),
        ];
        $requestData = [
            "subscription" => $newCardId,
        ];

        (new UpdateSubscription())($subscription->gatewaySubscriptionId, $requestData);
    }
}
