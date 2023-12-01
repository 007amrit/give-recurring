<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Framework\Exceptions\Primitives\Exception;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\CreateCard;

/**
 * @see https://developer.squareup.com/docs/cards-api/walkthrough-seller-card
 *
 * @since 2.3.0
 */
class GetSquareCardId
{
    /**
     * @since 2.3.0
     *
     * @throws Exception
     */
    public function __invoke(string $idempotencyKey, string $squareCustomerId, string $squareSourceId): string
    {
        $requestData = [
            "card" => [
                "customer_id" => $squareCustomerId,
            ],
            "source_id" => $squareSourceId,
            "idempotency_key" => $idempotencyKey,
        ];

        return (new CreateCard())($requestData)->id;
    }
}
