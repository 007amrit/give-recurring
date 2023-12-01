<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\PaymentGateways\Gateways\Stripe\Exceptions\StripeCustomerException;
use Give_Stripe_Customer;

/**
 * @since 2.0.0
 */
class GetStripeCustomer
{
    /**
     * @since 2.0.0
     *
     * @throws StripeCustomerException|Exception
     */
    public function __invoke(Donation $donation, string $stripePaymentMethodId = ''): Give_Stripe_Customer
    {
        $giveStripeCustomer = new Give_Stripe_Customer($donation->email, $stripePaymentMethodId);

        if (!$giveStripeCustomer->get_id()) {
            throw new StripeCustomerException('Unable to find or create stripe customer object.');
        }

        return $giveStripeCustomer;
    }
}
