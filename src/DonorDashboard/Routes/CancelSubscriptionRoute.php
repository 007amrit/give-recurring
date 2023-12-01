<?php

namespace GiveRecurring\DonorDashboard\Routes;

use Exception;
use Give\DonorDashboards\Tabs\Contracts\Route as RouteAbstract;
use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\Subscriptions\Models\Subscription;
use Give_Subscription as LegacySubscription;
use GiveRecurring\Infrastructure\Log;
use WP_REST_Request;

/**
 * @since 1.12.0
 */
class CancelSubscriptionRoute extends RouteAbstract
{

    /**
     * @since 1.12.0
     */
    public function endpoint(): string
    {
        return 'recurring-donations/subscription/cancel';
    }

    /**
     * @since 1.12.0
     */
    public function args(): array
    {
        return [
            'id' => [
                'type' => 'int',
                'required' => true,
            ],
        ];
    }

    /**
     * Should handle subscription cancellation request.
     *
     * @since 2.4.0 only cancel legacy subscription if gateway succeeds, throw exception if gateway fails.
     * @since 1.12.0
     * @throws Exception
     */
    public function handleRequest(WP_REST_Request $request)
    {
        $legacySubscription = new LegacySubscription($request->get_param('id'));

        if (! $legacySubscription->can_cancel()) {
            return;
        }

        try {
            $subscriptionId = (int)$request->get_param('id');

            /** @var Subscription $subscription */
            $subscription = Subscription::find($subscriptionId);

            $gatewayId = $subscription->gatewayId;

            /** @var PaymentGatewayRegister $paymentGatewayRegister */
            $paymentGatewayRegister = give(PaymentGatewayRegister::class);

            if (
                $paymentGatewayRegister->hasPaymentGateway($gatewayId) &&
                $subscription->gateway()->supportsSubscriptions()
            ) {
                $subscription->cancel();
            } else {
                // Use legacy gateway api to cancel subscription. if:
                // 1. Payment gateway register with legacy gateway api
                // 2. Payment gateway (partially migrated) does not support subscription cancellation with new gateway api.
                $gateway = give_recurring_get_gateway_from_subscription($legacySubscription);

                if (! ($gateway instanceof \Give_Recurring_Gateway)) {
                    throw new \RuntimeException('Payment Gateway does not found');
                }

                $gateway->cancel($legacySubscription, true);

                // Cancel the subscription with GiveWP
                $legacySubscription->cancel();
            }
        } catch (Exception $exception) {
            Log::error(
                sprintf(
                    'Failed to cancel subscription %1$s with gateway %2$s',
                    $legacySubscription->id,
                    $legacySubscription->gateway
                ),
                ['Exception' => $exception->getMessage()]
            );

            throw $exception;
        }
    }
}
