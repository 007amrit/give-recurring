<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\Webhooks;

use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use GiveRecurring\PaymentGatewayModules\Modules\Square\ValueObjects\SquareSubscriptionStatus;
use stdClass;

/**
 * @since 2.3.0
 */
class SquareWebhookEvents
{
    /**
     * @since 2.3.0
     *
     * @see https://developer.squareup.com/docs/webhooks/v2webhook-events-tech-ref#invoices-api
     * @see https://developer.squareup.com/docs/webhooks/v2webhook-events-tech-ref#subscriptions-api
     */
    public function processEvent(stdClass $eventJson)
    {
        if (isset($eventJson->data->object->invoice) &&
            ! isset($eventJson->data->object->invoice->subscription_id)) { // The ID of the subscription associated with the invoice. This field is present only on subscription billing invoices.
            PaymentGatewayLog::debug(
                sprintf('[Square Webhooks] Ignore %s event for %s id %s.',
                    $eventJson->type,
                    $eventJson->data->type,
                    $eventJson->data->id),
                [
                    '$gatewaySubscriptionId' => $eventJson->data->object->invoice->subscription_id,
                    '$eventJson' => $eventJson,
                ]
            );

            return;
        }

        switch (strtolower($eventJson->type)) {
            case 'invoice.updated':
            case 'invoice.payment_made':
                as_enqueue_async_action('givewp_square_event_handle_subscription_donations',
                    [
                        $eventJson->data->id,
                        __('[Square] Subscription donation approved.', 'give-square'),
                    ],
                    'give-square');
                break;
            case 'subscription.created':
            case 'subscription.updated':
                $this->handleSubscriptionStatus(new SquareSubscriptionStatus($eventJson->data->object->subscription->status),
                    $eventJson->data->id);
                break;
            default:
                break;
        }
    }

    /**
     * @since 2.3.0
     */
    private function handleSubscriptionStatus(SquareSubscriptionStatus $status, string $gatewaySubscriptionId)
    {
        switch (strtolower($status)) {
            case $status->isActive():
                as_enqueue_async_action('givewp_square_event_subscription_active',
                    [$gatewaySubscriptionId, __('[Square] Subscription Active.', 'give-recurring')],
                    'give-square');
                break;
            case $status->isCanceled():
                as_enqueue_async_action('givewp_square_event_subscription_cancelled',
                    [$gatewaySubscriptionId, __('[Square] Subscription Cancelled.', 'give-recurring')],
                    'give-square');
                break;
            case $status->isPaused():
                as_enqueue_async_action('givewp_square_event_subscription_suspended',
                    [
                        $gatewaySubscriptionId,
                        __('[Square] Subscription Suspended.', 'give-recurring'),
                    ],
                    'give-square');
                break;
            case $status->isDeactivated():
                as_enqueue_async_action('givewp_square_event_subscription_cancelled',
                    [$gatewaySubscriptionId, __('[Square] Subscription Deactivated.', 'give-recurring')],
                    'give-square');
                break;
            default:
                break;
        }
    }
}
