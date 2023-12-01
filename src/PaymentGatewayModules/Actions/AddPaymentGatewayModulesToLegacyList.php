<?php

namespace GiveRecurring\PaymentGatewayModules\Actions;

use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Framework\PaymentGateways\PaymentGatewayRegister;

class AddPaymentGatewayModulesToLegacyList
{
    /**
     * @since 1.14.0
     *
     * @param  array  $gateways
     *
     * @return array
     */
    public function __invoke($gateways)
    {
        /** @var PaymentGatewayRegister $paymentGatewayRegister */
        $paymentGatewayRegister = give(PaymentGatewayRegister::class);

        $registeredGateways = $paymentGatewayRegister->getPaymentGateways();

        $subscriptionModules = [];

        foreach ($registeredGateways as $gatewayClass) {
            /** @var PaymentGateway $gateway */
            $gateway = give($gatewayClass);

            // the gateway ID just needs to be in the filter 'give_recurring_available_gateways'
            // to continue.  eventually this won't be necessary.
            if ($gateway->supportsSubscriptions()) {
                $subscriptionModules[$gateway::id()] = true;
            }
        }

        return array_merge($gateways, $subscriptionModules);
    }
}
