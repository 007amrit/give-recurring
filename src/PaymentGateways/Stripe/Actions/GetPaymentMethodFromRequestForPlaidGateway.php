<?php

namespace GiveRecurring\PaymentGateways\Stripe\Actions;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\PaymentMethod;

/**
 * @since 2.0.0
 */
class GetPaymentMethodFromRequestForPlaidGateway
{
    /**
     * @since 2.0.0
     *
     * @throws PaymentGatewayException
     * @throws Exception
     */
    public function __invoke(Donation $donation): PaymentMethod
    {
        $plaidCredentials = [
            'client_id' => trim(give_get_option('plaid_client_id')),
            'secret_key' => trim(give_get_option('plaid_secret_key')),
        ];

        $stripe_ach_token = give_clean($_POST['give_stripe_ach_token']);
        $stripe_ach_account_id = give_clean($_POST['give_stripe_ach_account_id']);

        // Sanity check: must have Plaid token and account id.
        if (empty($stripe_ach_token)) {
            throw new PaymentGatewayException(
                'The Stripe ACH gateway failed to generate the Plaid token. Reload page and process subscription again. If problem does not resolve then please contact the site administrator.',
            );
        }

        if (empty($stripe_ach_account_id)) {
            throw new PaymentGatewayException(
                'The Stripe ACH gateway failed to generate the Plaid account ID. Reload page and process subscription again. If problem does not resolve then please contact the site administrator.',
            );
        }

        $request = wp_remote_post(
            give_stripe_ach_get_endpoint_url('exchange'),
            [
                'body' => json_encode([
                    'client_id' => $plaidCredentials['client_id'],
                    'secret' => $plaidCredentials['secret_key'],
                    'public_token' => $stripe_ach_token,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                ],
            ]
        );

        if (is_wp_error($request)) {
            throw new Exception(
                sprintf(
                    'The Stripe ACH gateway failed to make the call to the Plaid server to get the Stripe bank account token along with the Plaid access token that can be used for other Plaid API requests. Details: %1$s',
                    $request->get_error_message()
                )
            );
        }

        // Decode response.
        $response = json_decode(wp_remote_retrieve_body($request));

        $request = wp_remote_post(give_stripe_ach_get_endpoint_url('bank_account'), [
            'body' => json_encode([
                'client_id' => $plaidCredentials['client_id'],
                'secret' => $plaidCredentials['secret_key'],
                'access_token' => $response->access_token,
                'account_id' => $stripe_ach_account_id,
            ]),
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
            ],
        ]);

        $response = json_decode(wp_remote_retrieve_body($request));

        // Is there an error returned from the API?
        if (isset($response->error_code)) {
            throw new Exception(
                sprintf(
                    'An error occurred when processing a donation via Plaid\'s API. Details: %1$s',
                    "$response->error_code (error code) - $response->error_type (error type) - $response->error_message"
                )
            );
        }

        $paymentMethod = new PaymentMethod($response->stripe_bank_account_token);
        give_update_meta($donation->id, '_give_stripe_source_id', $paymentMethod->id());

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => sprintf(__('Stripe Source/Payment Method ID: %s', 'give'), $paymentMethod->id())
        ]);

        return $paymentMethod;
    }
}
