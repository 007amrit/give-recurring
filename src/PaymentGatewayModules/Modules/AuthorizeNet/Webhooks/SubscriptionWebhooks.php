<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Webhooks;

use stdClass;

/**
 * Process Authorize.Net Subscription Webhooks.
 *
 * @since 2.2.0
 */
class SubscriptionWebhooks
{
    /**
     * @since 2.2.0
     *
     * @see https://developer.authorize.net/api/reference/features/webhooks.html#Event_Types_and_Payloads
     *
     * @param stdClass $eventJson
     */
    public function processWebhooks($eventJson)
    {
        switch (strtolower($eventJson->eventType)) {
            case 'net.authorize.payment.authcapture.created':
                as_enqueue_async_action('givewp_authorize_event_handle_subscription_donations',
                    [
                        $eventJson->payload->id,
                        __('[Authorize.Net] Subscription donation approved.', 'give-recurring'),
                    ],
                    'give-authorize');
                break;
            case 'net.authorize.payment.fraud.approved':
                as_enqueue_async_action('givewp_authorize_event_handle_subscription_donations',
                    [
                        $eventJson->payload->id,
                        __('[Authorize.Net] Subscription donation approved by the fraud filter.', 'give-recurring'),
                    ],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.created':
                as_enqueue_async_action('givewp_authorize_event_subscription_active',
                    [$eventJson->payload->id, __('[Authorize.Net] Subscription active.', 'give-recurring')],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.cancelled':
                as_enqueue_async_action('givewp_authorize_event_subscription_cancelled',
                    [$eventJson->payload->id, __('[Authorize.Net] Subscription cancelled.', 'give-recurring')],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.suspended':
                as_enqueue_async_action('givewp_authorize_event_subscription_suspended',
                    [
                        $eventJson->payload->id,
                        __('[Authorize.Net] Subscription suspended.', 'give-recurring'),
                    ],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.terminated':
                as_enqueue_async_action('givewp_authorize_event_subscription_completed',
                    [
                        $eventJson->payload->id,
                        __('[Authorize.Net] Subscription terminated/completed.', 'give-recurring'),
                    ],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.expiring':
            case 'net.authorize.customer.subscription.expired':
                as_enqueue_async_action('givewp_authorize_event_subscription_expired',
                    [$eventJson->payload->id, __('[Authorize.Net] Subscription expired.', 'give-recurring')],
                    'give-authorize');
                break;
            case 'net.authorize.customer.subscription.failed':
                as_enqueue_async_action('givewp_authorize_event_subscription_failing',
                    [$eventJson->payload->id, __('[Authorize.Net] Subscription failing.', 'give-recurring')],
                    'give-authorize');
                break;
            default:
                break;
        }
    }
}
