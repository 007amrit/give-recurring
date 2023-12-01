<?php

namespace GiveRecurring\PaymentGatewayModules\Modules;

use Exception;
use Give\Donations\Models\Donation;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionAmountEditable;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Framework\Support\ValueObjects\Money;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\GenerateDonationFailedPageUrl;
use Give\PaymentGateways\Gateways\PayPalStandard\Actions\GenerateDonationReceiptPageUrl;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GivePayFast\Gateway\PayFastGateway;

/**
 * @since 2.1.0
 */
class PayFastGatewaySubscriptionModule extends SubscriptionModule implements SubscriptionAmountEditable
{
    public $routeMethods = [];

    public $secureRouteMethods = [
        'handleSuccessSubscriptionReturn',
        'handleCanceledSubscriptionReturn',
    ];

    /**
     * @since 2.1.0
     *
     * @inheritDoc
     */
    public function canSyncSubscriptionWithPaymentGateway(): bool
    {
        return false;
    }

    /**
     * @since 2.1.0
     *
     * @inheritDoc
     */
    public function canUpdateSubscriptionAmount(): bool
    {
        return true;
    }

    /**
     * @since 2.1.0
     *
     * @inheritDoc
     */
    public function canUpdateSubscriptionPaymentMethod(): bool
    {
        return false;
    }

    /**
     * @since 2.1.0
     *
     * @inheritDoc
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData = null
    ): RedirectOffsite
    {
        PayFastGateway::addScheduleToAbandonPaymentAfterOneHour($donation->id);

        $params = array_merge($this->gateway->getPaymentParameters($donation),
            $this->getSubscriptionParameters($subscription));

        $params['return_url'] = urlencode(esc_url_raw($this->getReturnURL($donation, $subscription)));
        $params['cancel_url'] = urlencode(esc_url_raw($this->getCancelURL($donation, $subscription)));

        /**
         * Filter the PayFast transaction request subscription params
         *
         * @since 2.1.0
         *
         * @param array $params PayFast Query Parameters.
         *
         */
        $params = apply_filters('give_payfast_transaction_request_subscription_params', $params);

        $redirectUrl = add_query_arg($params, give_payfast_get_api_url());

        return new RedirectOffsite($redirectUrl);
    }

    /**
     * Reference: https://developers.payfast.co.za/api#cancel-a-subscription
     *
     * @since 2.1.0
     *
     * @return bool
     * @throws Exception
     */
    public function cancelSubscription(Subscription $subscription)
    {
        $gatewaySubscriptionId = $subscription->gatewaySubscriptionId;

        if ( ! $gatewaySubscriptionId) {
            throw new PaymentGatewayException(__('[PayFast] Cancellation cannot be done without a transaction id.',
                'give-payfast'));
        }

        $merchant = give_payfast_get_merchant_credentials();

        $body = '';

        $headers = [
            'merchant-id' => $merchant['merchant_id'],
            'timestamp' => date('Y-m-d\TH:i:s', time()),
            'version' => 'v1',
        ];
        $headers['signature'] = $this->gateway->generateApiSignature($headers, $merchant['passphrase']);
        $headers['Content-length'] = strlen($body);

        $apiUrl = $this->getApiSubscriptionsUrl($gatewaySubscriptionId, 'cancel');

        $apiReturn = wp_remote_request($apiUrl, ['method' => 'PUT', 'headers' => $headers, 'body' => $body]);

        if (200 !== $apiReturn['response']['code']) {
            PaymentGatewayLog::error(
                sprintf(__('[PayFast] Was not possible to cancel subscription %s.', 'give-payfast'),
                    $subscription->id),
                [
                    'Payment Gateway' => $subscription->gatewayId,
                    'Message' => $apiReturn['response']['code'] . ' - ' . $apiReturn['response']['message'],
                    'Full API Return' => $apiReturn,
                ]
            );

            throw new PaymentGatewayException(__('[PayFast] API Error:',
                    'give-payfast') . $apiReturn['response']['message'],
                $apiReturn['response']['code']);
        }

        $subscription->status = SubscriptionStatus::CANCELLED();
        $subscription->save();

        PaymentGatewayLog::success(
            sprintf(__('PayFast: Cancellation successful for subscription %s.', 'give-payfast'),
                $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Message' => $apiReturn['response']['code'] . ' - ' . $apiReturn['response']['message'],
            ]
        );

        return true;
    }

    /**
     * Reference: https://developers.payfast.co.za/api#update-a-subscription
     *
     * @since 2.1.0
     *
     * @return bool
     * @throws Exception
     */
    public function updateSubscriptionAmount(Subscription $subscription, Money $newRenewalAmount)
    {
        $gatewaySubscriptionId = $subscription->gatewaySubscriptionId;

        if ( ! $gatewaySubscriptionId) {
            throw new PaymentGatewayException(__('[PayFast] Updating cannot be done without a transaction id.',
                'give-payfast'));
        }

        $merchant = give_payfast_get_merchant_credentials();

        $body = ['amount' => $newRenewalAmount->formatToMinorAmount()];

        $headers = [
            'merchant-id' => $merchant['merchant_id'],
            'timestamp' => date('Y-m-d\TH:i:s', time()),
            'version' => 'v1',
        ];
        $headers['signature'] = $this->gateway->generateApiSignature(array_merge($headers, $body),
            $merchant['passphrase']);
        $headers['Content-Type'] = 'application/json';

        $apiUrl = $this->getApiSubscriptionsUrl($gatewaySubscriptionId, 'update');

        $apiReturn = wp_remote_request($apiUrl,
            ['method' => 'PATCH', 'headers' => $headers, 'body' => wp_json_encode($body), 'data_format' => 'body',]);

        if (200 !== $apiReturn['response']['code']) {
            PaymentGatewayLog::error(
                sprintf(__('[PayFast] Was not possible to update the amount for subscription %s.', 'give-payfast'),
                    $subscription->id),
                [
                    'Payment Gateway' => $subscription->gatewayId,
                    'Message' => $apiReturn['response']['code'] . ' - ' . $apiReturn['response']['message'],
                    'Full API Return' => $apiReturn,
                ]
            );

            throw new PaymentGatewayException(__('[PayFast] API Error:',
                    'give-payfast') . $apiReturn['response']['message'],
                $apiReturn['response']['code']);
        }

        $oldRenewalAmount = $subscription->amount->formatToDecimal();

        $subscription->amount = $newRenewalAmount;
        $subscription->save();

        PaymentGatewayLog::success(
            sprintf(__('PayFast: amount successful updated for subscription %s.', 'give-payfast'),
                $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Message' => $apiReturn['response']['code'] . ' - ' . $apiReturn['response']['message'],
                'Old Value' => $oldRenewalAmount,
                'New Value' => $newRenewalAmount->formatToDecimal(),
            ]
        );

        return true;
    }

    /**
     * The URL where the user is returned to (the "return_url" parameter) after payment has been successfully
     * taken - before returning the customer to it, PayFast will send a notification to your "notify_url" page.
     *
     * IMPORTANT: If you are testing locally you will need to have a publicly accessible
     * URL (notify_url) in order to receive the notifications, consider using tools such
     * as NGROK or Expose to expose your local development server to the Internet.
     *
     * @since 2.1.0
     *
     * @return RedirectResponse
     * @throws \Give\Framework\Exceptions\Primitives\Exception
     */
    protected function handleSuccessSubscriptionReturn(array $queryParams): RedirectResponse
    {
        $donationId = (int)$queryParams['donation-id'];
        $subscriptionId = (int)$queryParams['subscription-id'];

        if (function_exists('wp_get_environment_type') && ('local' === wp_get_environment_type() || 'development' === wp_get_environment_type())) {
            $subscription = Subscription::find($subscriptionId);

            /**
             * Verify the status before changing it because maybe the 'notify_url' page already has changed it in
             * cases where you are using tools such as NGROK or Expose in your local development environment.
             *
             * So please, keep in mind that if this condition is attended, means that the $donation->gatewayTransactionId
             * and the $donation->subscription->gatewaySubscriptionId properties will not be set because they only can
             * be retrieved on the 'notify_url' page that handles the data sent by the gateway.
             */
            if ( ! $subscription->status->isActive()) {
                $subscription->status = SubscriptionStatus::ACTIVE();
                $subscription->save();
                PayFastGateway::removeScheduleToAbandonPaymentAfterOneHour($donationId);
            }
        }

        return new RedirectResponse(
            esc_url_raw(
                add_query_arg(
                    ['payment-confirmation' => $this->gateway->getId()],
                    (new GenerateDonationReceiptPageUrl())($donationId)
                )
            )
        );
    }

    /**
     * The URL where the user should be redirected should they choose to cancel their
     * payment while on the PayFast system - the "cancel_url" parameter.
     *
     * @since 2.1.0
     *
     * @return RedirectResponse
     * @throws Exception
     */
    protected function handleCanceledSubscriptionReturn(array $queryParams): RedirectResponse
    {
        $donationId = (int)$queryParams['donation-id'];
        $subscriptionId = (int)$queryParams['subscription-id'];

        $subscription = Subscription::find($subscriptionId);
        $subscription->status = SubscriptionStatus::CANCELLED();
        $subscription->save();
        PayFastGateway::removeScheduleToAbandonPaymentAfterOneHour($donationId);

        return new RedirectResponse((new GenerateDonationFailedPageUrl())($donationId));
    }

    /**
     * @since 2.1.0
     */
    private function getReturnURL(Donation $donation, Subscription $subscription): string
    {
        return $this->gateway->generateSecureGatewayRouteUrl(
            'handleSuccessSubscriptionReturn',
            $donation->id,
            [
                'donation-id' => $donation->id,
                'subscription-id' => $subscription->id,
            ]
        );
    }

    /**
     * @since 2.1.0
     */
    private function getCancelURL(Donation $donation, Subscription $subscription): string
    {
        return $this->gateway->generateSecureGatewayRouteUrl(
            'handleCanceledSubscriptionReturn',
            $donation->id,
            [
                'donation-id' => $donation->id,
                'subscription-id' => $subscription->id,
            ]
        );
    }

    /**
     * Reference: https://developers.payfast.co.za/api#recurring-billing
     *
     * @since 2.1.0
     */
    private function getApiSubscriptionsUrl(string $token, string $action): string
    {
        $url = "https://api.payfast.co.za/subscriptions/$token/$action";

        if (give_payfast_is_test_mode()) {
            $url .= '?testing=true';
        }

        return $url;
    }

    /**
     * Reference: https://developers.payfast.co.za/docs#subscriptions
     *
     * @since 2.1.0
     */
    private function getSubscriptionParameters(Subscription $subscription): array
    {
        /**
         * @see
         *
         * PayFast frequency available options:
         *
         *  The cycle period.
         * 3 - Monthly
         * 4 - Quarterly
         * 5 - Biannually
         * 6 - Annual
         */
        if ($subscription->period->isQuarter()) {
            $payFastFrequency = 4;
        } elseif ($subscription->period->isYear()) {
            $payFastFrequency = 6;
        } else {
            $payFastFrequency = 3; // Monthly is the default for all other options as GiveWP not support the "5 - Biannually" option.
        }

        // The number of payments/cycles that will occur for this subscription. Set to 0 for indefinite subscription.
        $payFastCycle = $subscription->installments;

        // Future recurring amount for the subscription in ZAR. Defaults to the "amount" value if not set. There is a minimum value of 5.00
        $payFastRecurringAmount = $subscription->amount->formatToDecimal();

        return [
            'subscription_type' => '1', // sets type to a subscription.
            'frequency' => $payFastFrequency,
            'cycles' => $payFastCycle,
            'recurring_amount' => $payFastRecurringAmount,
        ];
    }
}
