<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\Support\ValueObjects\Money;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\UpdateSubscription;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;

/**
 * @see https://developer.squareup.com/reference/square/objects/Subscription
 *
 * @since 2.3.0
 */
class UpdateSquareSubscriptionAmount
{
    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|Exception
     */
    public function __invoke(string $squareSubscriptionId, Money $newRenewalAmount): bool
    {
        $priceOverrideMoney = [
            "price_override_money" => [
                "amount" => (int)$newRenewalAmount->formatToMinorAmount(),
                "currency" => $newRenewalAmount->getCurrency()->getCode(),
            ],
        ];
        $requestData = [
            "subscription" => $priceOverrideMoney,
        ];

        $squareSubscription = (new UpdateSubscription())($squareSubscriptionId, $requestData);

        if ($squareSubscription->price_override_money->amount !== (int)$newRenewalAmount->formatToMinorAmount()) {
            return false;
        }

        return true;
    }
}
