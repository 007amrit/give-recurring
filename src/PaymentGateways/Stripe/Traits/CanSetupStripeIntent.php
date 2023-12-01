<?php

namespace GiveRecurring\PaymentGateways\Stripe\Traits;

use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;

/**
 * @since 2.0.0
 */
trait CanSetupStripeIntent
{
    /**
     * @since 2.0.0
     * @throws ApiErrorException
     */
    protected function setupStripeIntent(
        Customer $stripeCustomer,
        PaymentMethod $paymentMethod,
        array $metadata,
        array $paymentMethodTypes
    ): SetupIntent {
        $setupIntentArgs = [
            'metadata' => $metadata,
            'payment_method_types' => $paymentMethodTypes,
            'confirm' => true,
            'customer' => $stripeCustomer->id,
            'payment_method' => $paymentMethod->id,
            'usage' => 'off_session',
            'mandate_data' => [
                'customer_acceptance' => [
                    'type' => 'online',
                    'online' => [
                        'ip_address' => give_get_ip(),
                        'user_agent' => give_get_user_agent(),
                    ],
                ],
            ],
        ];

        return SetupIntent::create($setupIntentArgs);
    }
}
