<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\LegacyListeners;

use Exception;
use Give\Donations\Models\Donation;
use Give\Subscriptions\Models\Subscription;

/**
 * @since 2.3.1
 */
class DispatchGiveAuthorizeRecurringPaymentDescription
{
    /**
     * @since 2.3.1
     *
     * @throws Exception
     */
    public function __invoke(string $description, Donation $donation, Subscription $subscription): string
    {
        if ( ! has_filter('give_authorize_recurring_payment_description')) {
            return $description;
        }

        $purchase_data = [
            'price' => $donation->amount->formatToDecimal(),
            'purchase_key' => $donation->purchaseKey,
            'user_email' => $donation->donor->email,
            'date' => $donation->createdAt->format('Y-m-d H:i:s'),
            'user_info' => [
                'id' => $donation->donorId,
                'title' => $donation->donor->prefix,
                'email' => $donation->donor->email,
                'first_name' => $donation->donor->firstName,
                'last_name' => $donation->donor->lastName,
                'address' => [
                    'line1' => $donation->billingAddress->address1,
                    'line2' => $donation->billingAddress->address2,
                    'city' => $donation->billingAddress->city,
                    'state' => $donation->billingAddress->state,
                    'zip' => $donation->billingAddress->zip,
                    'country' => $donation->billingAddress->country,
                ],
            ],
            'post_data' => give_clean($_POST),
            'gateway' => $donation->gatewayId,
            'card_info' => [
                'card_name' => '',
                'card_number' => '',
                'card_cvc' => '',
                'card_exp_month' => '',
                'card_exp_year' => '',
                'card_address' => '',
                'card_address_2' => '',
                'card_city' => '',
                'card_state' => '',
                'card_country' => '',
                'card_zip' => '',
            ],
            'period' => $subscription->period->getValue(),
            'times' => $subscription->installments,
            'frequency' => $subscription->frequency,
            'gateway_nonce' => '',
        ];

        $subscription = [
            'name' => $donation->formTitle,
            'id' => $subscription->id,
            'form_id' => $donation->formId,
            'price_id' => $donation->levelId,
            'initial_amount' => $donation->amount->formatToDecimal(),
            'recurring_amount' => $subscription->amount->formatToDecimal(),
            'period' => $subscription->period->getValue(),
            'frequency' => $subscription->frequency,
            'bill_times' => $subscription->installments,
            'profile_id' => '',
            'transaction_id' => $subscription->transactionId,
            'status' => $subscription->status->getValue(),
        ];

        return apply_filters('give_authorize_recurring_payment_description', $description, $purchase_data,
            $subscription);
    }
}
