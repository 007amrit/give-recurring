<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Api;

use Give\Framework\Exceptions\Primitives\Exception;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;
use GiveSquare\Square\Api\Traits\ApiRequestHelpers;

/**
 * @see https://developer.squareup.com/reference/square/subscriptions-api/cancel-subscription
 *
 * @since 2.3.0
 */
class CancelSubscription
{
    use ApiRequestHelpers;

    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|Exception
     */
    public function __invoke(string $subscriptionId): string
    {
        $requestArgs['headers'] = $this->getApiRequestHeaders();

        $response = wp_remote_post(
            $this->getApiRequestUrl('v2/subscriptions/' . $subscriptionId . '/cancel'),
            $requestArgs
        );

        if (is_wp_error($response)) {
            throw new Exception("Square API request error. Error: {$response->get_error_message()}");
        }

        $response = $this->getResponseOrThrowApiException($response);

        return $response->subscription->canceled_date;
    }
}
