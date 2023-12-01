<?php

namespace GiveRecurring\DonorDashboard\Routes;

use Exception;
use Give\DonorDashboards\Tabs\Contracts\Route as RouteAbstract;
use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use Give_Recurring_Gateway;
use Give_Recurring_Subscriber as Subscriber;
use Give_Subscription as LegacySubscription;
use WP_REST_Request;
use WP_REST_Response;

/**
 * This class could use some major refactoring in the future. At this point, the legacy Gateway code is being used to
 * update the payment methods and amounts. The legacy code is overbearing, checking the shape of requests and not having
 * a clean method to do things like just change an amount for a given subscription. Once gateways are improved, this
 * code needs to be updated.
 *
 * @todo Refactor this after Gateways are improved
 *
 * @since 1.12.0
 */
class UpdateSubscriptionRoute extends RouteAbstract
{

    /**
     * @return string
     */
    public function endpoint()
    {
        return 'recurring-donations/subscription/update';
    }

    /**
     * @return array[]
     */
    public function args()
    {
        return [
            'id' => [
                'type' => 'int',
                'required' => true,
            ],
            'payment_method' => [
                'type' => 'array',
                'required' => false,
                'sanitize_callback' => [$this, 'sanitizeArray'],
            ],
            'amount' => [
                'type' => 'int',
                'required' => false,
            ]
        ];
    }

    /**
     * @param $arr
     *
     * @return array
     */
    public function sanitizeArray($arr)
    {
        $sanitizedArr = [];
        if ($arr) {
            foreach ($arr as $key => $value) {
                $sanitizedArr[$key] = sanitize_text_field($value);
            }
        }

        return $sanitizedArr;
    }

    /**
     * @since 1.12.5 add necessary workarounds for legacy gateway code
     * @since 1.12.0
     */
    public function handleRequest(WP_REST_Request $request)
    {
        // GiveWP stores internal errors in session which make next request exit early (from same origin).
        // Most of function which we are using to update subscription are written frontend. For this reason we are string errors in session.
        // Clear GiveWP errors from session to unblock request or functionality.
        give_clear_errors();

        // Gather parameters from the request
        $legacySubscription = new LegacySubscription($request->get_param('id'));
        $paymentMethod = $this->getPaymentMethodFromRequest($request);
        $amount = $request->get_param('amount');

        if (!$legacySubscription->id) {
            return;
        }

        if (!$amount || !$legacySubscription->can_update_subscription()) {
            return;
        }

        // TODO: Remove below code when you think all payment method use new gateway api.
        if ($legacyGateway = give_recurring_get_gateway_from_subscription($legacySubscription)) {
            $this->setRequiredValueInPostGlobal($legacySubscription, $legacyGateway);
            $subscriber = new Subscriber($legacySubscription->donor_id);

            if ($paymentMethod) {
                $this->updatePaymentMethodOnPaymentGateway(
                    $legacySubscription,
                    $subscriber,
                    $legacyGateway,
                    $paymentMethod
                );
            }

            $this->updateSubscriptionOnPaymentGateway($legacySubscription, $legacyGateway, $paymentMethod, $amount);
        } else {
            $subscription = Subscription::find($legacySubscription->id);
            $gateway = $subscription->gateway();
            $paymentMethod = array_filter($paymentMethod);

            if ($paymentMethod) {
                /**
                 * Filter to provide payment method request data to gateway before updating payment method.
                 * Note: This filter fires when donor send payment method update request from donor dashboard.
                 *
                 * @since 2.0.0
                 */
                $gatewayDataData = apply_filters(
                    "givewp_donor_dashboard_edit_subscription_payment_method_gateway_data_{$gateway::id()}",
                    $paymentMethod,
                    $subscription
                );

                try {
                    $gateway->updateSubscriptionPaymentMethod($subscription, $gatewayDataData);
                } catch (Exception $e) {
                    return new WP_REST_Response(
                        [
                            'status' => 400,
                            'response' => 'failed_to_update_subscription_payment_method',
                            'body_response' => [
                                'message' => html_entity_decode(
                                    sprintf(
                                        esc_html__('%s. Contact a site administrator and have them search the logs at Donations > Tools > Logs for a more specific cause of the problem.',
                                            'give-recurring'), rtrim($e->getMessage(), '.')
                                    )
                                ),
                            ],
                        ]
                    );
                }
            }

            try {
                $moneyAmount = Money::fromDecimal($amount, $subscription->amount->getCurrency()->getCode());
                $gateway->updateSubscriptionAmount($subscription, $moneyAmount);
            } catch (Exception $e) {
                return new WP_REST_Response(
                    [
                        'status' => 400,
                        'response' => 'failed_to_update_subscription_amount',
                        'body_response' => [
                            'message' => html_entity_decode(
                                sprintf(
                                    esc_html__('%s. Contact a site administrator and have them search the logs at Donations > Tools > Logs for a more specific cause of the problem.',
                                        'give-recurring'), rtrim($e->getMessage(), '.')
                                )
                            ),
                        ],
                    ]
                );
            }
        }

        $legacySubscription->update(['recurring_amount' => $amount]);

        // Reset GiveWP errors.
        give_clear_errors();
    }

    /**
     * @since 1.12.5
     *
     * @param LegacySubscription $subscription
     * @param Give_Recurring_Gateway $gateway
     * @param Subscriber $subscriber
     * @param string $amount
     */
    private function updateSubscriptionOnPaymentGateway($subscription, $gateway, $subscriber, $amount)
    {
        $data = [
            'give-amount' => $amount,
            'subscription_id' => $subscription->id,
        ];
        $gateway->update_subscription($subscriber, $subscription, $data);
    }

    /**
     * @since 1.12.5
     *
     * @param LegacySubscription $subscription
     * @param Subscriber $subscriber
     * @param Give_Recurring_Gateway $gateway
     * @param array $paymentMethod
     */
    private function updatePaymentMethodOnPaymentGateway($subscription, $subscriber, $gateway, $paymentMethod)
    {
        $data = [];
        foreach ($paymentMethod as $key => $value) {
            $data[$key] = $value;
        }
        $gateway->update_payment_method($subscriber, $subscription, $data);
    }

    /**
     * @since 1.12.5
     *
     * @param WP_REST_Request $request
     *
     * @return array|mixed|null
     */
    private function getPaymentMethodFromRequest(WP_REST_Request $request)
    {
        $paymentMethodInfo = $request->get_param('payment_method');

        // Payment method is not required.
        // Do not pass card information array with empty value.
        // Array either contains value or empty array. it prevent unnecessary error to set in session.
        // These errors block subscription update process.
        if (array_key_exists('card_number', $paymentMethodInfo)) {
            $cardInfo = array_filter($paymentMethodInfo);
            if (5 !== count($cardInfo)) {
                return [];
            }
        }

        return $paymentMethodInfo;
    }

    /**
     * @since 1.12.5
     *
     * @param LegacySubscription $subscription
     * @param Give_Recurring_Gateway $gateway
     */
    private function setRequiredValueInPostGlobal($subscription, $gateway)
    {
        // Give_Recurring_Stripe::update_subscription_method uses  Give_Stripe_Payment_Method::retrieve method to set payment source information.
        // This function internally depends upon form id and in most of case access form id from  post method.
        if (in_array($gateway->id, give_stripe_supported_payment_methods())) {
            $_POST['give-form-id'] = $subscription->form_id;
        }
    }
}
