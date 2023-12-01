<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Actions;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\GetInvoice;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;
use stdClass;

/**
 * @see https://developer.squareup.com/reference/square/objects/Invoice
 *
 * @since 2.3.0
 */
class GetSquareInvoice
{
    /**
     * @since 2.3.0
     *
     * @throws ApiRequestException|PaymentGatewayException|Exception
     */
    public function __invoke(string $invoiceId): stdClass
    {
        $squareInvoice = (new GetInvoice())($invoiceId);

        if ( ! isset($squareInvoice->id)) {
            PaymentGatewayLog::error(
                sprintf('[Square] The gateway did not find the invoice id %s.',
                    $invoiceId),
                [
                    'Square Invoice ID' => $invoiceId,
                    'Square Invoice' => $squareInvoice,
                ]
            );

            throw new PaymentGatewayException(__('[Square API] No response from the gateway.', 'give-square'));
        }

        if ( ! isset($squareInvoice->subscription_id)) {
            PaymentGatewayLog::error(
                sprintf('[Square] The invoice id %s is not associated with a Square subscription.',
                    $invoiceId),
                [
                    'Square Invoice ID' => $invoiceId,
                    'Square Subscription ID' => $squareInvoice->subscription_id,
                    'Square Invoice' => $squareInvoice,
                ]
            );

            throw new PaymentGatewayException(__('[Square API] Invoice without subscription associated in the gateway.',
                'give-square'));
        }

        return $squareInvoice;
    }
}
