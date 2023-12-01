<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Api;

use Give\Framework\Exceptions\Primitives\Exception;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;
use GiveSquare\Square\Api\Traits\ApiRequestHelpers;
use stdClass;

/**
 * @see https://developer.squareup.com/reference/square/cards-api/create-card
 *
 * @since 2.3.0
 */
class CreateCard
{
    use ApiRequestHelpers;

    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|Exception
     */
    public function __invoke(array $requestData): stdClass
    {
        $requestArgs['headers'] = $this->getApiRequestHeaders();
        $requestArgs['body'] = json_encode($requestData);

        $response = wp_remote_post(
            $this->getApiRequestUrl('v2/cards'),
            $requestArgs
        );

        if (is_wp_error($response)) {
            throw new Exception("Square API request error. Error: {$response->get_error_message()}");
        }

        $response = $this->getResponseOrThrowApiException($response);

        return $response->card;
    }
}
