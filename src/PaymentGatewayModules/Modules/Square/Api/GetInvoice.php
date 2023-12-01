<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Api;

use Give\Framework\Exceptions\Primitives\Exception;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;
use GiveSquare\Square\Api\Traits\ApiRequestHelpers;
use stdClass;

/**
 * @see https://developer.squareup.com/reference/square/invoices-api/get-invoice
 *
 * @since 2.3.0
 */
class GetInvoice
{
    use ApiRequestHelpers;

    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|Exception
     */
    public function __invoke(string $invoiceId): stdClass
    {
        $requestArgs['headers'] = $this->getApiRequestHeaders();

        $response = wp_remote_get(
            $this->getApiRequestUrl('v2/invoices/' . $invoiceId),
            $requestArgs
        );

        if (is_wp_error($response)) {
            throw new Exception("Square API request error. Error: {$response->get_error_message()}");
        }

        $response = $this->getResponseOrThrowApiException($response);

        return $response->invoice;
    }
}
