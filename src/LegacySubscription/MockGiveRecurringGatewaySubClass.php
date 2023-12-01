<?php

namespace GiveRecurring\LegacySubscription;

use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\Framework\Support\ValueObjects\Money;
use Give\Helpers\Gateways\Stripe;
use Give\PaymentGateways\Gateways\Stripe\Exceptions\PaymentMethodException;
use Give\PaymentGateways\Gateways\Stripe\Traits\CreditCardForm;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\PaymentMethod;
use Give\Subscriptions\Models\Subscription;
use Give_Subscription;
use GiveAuthorizeNet\DataTransferObjects\AuthorizeGatewayData;
use GiveAuthorizeNet\Exceptions\InvalidCredentialsException;
use GiveAuthorizeNet\Gateway\AcceptJs;
use GiveRecurring\Infrastructure\Log;
use GiveSquare\PaymentGateway\Actions\GetSquareGatewayData;

/**
 * @since 2.0.0
 *
 * This class use to reuse of legacy logic which is part of Give_Recurring_Gateway class which handles subscription cancellation, edit payment method, edit amount and etc.
 */
class MockGiveRecurringGatewaySubClass extends \Give_Recurring_Gateway
{
    use CreditCardForm {
        getCreditCardFormHTML as getStripeCreditCardFormHTML;
    }

    /**
     * @since 2.0.0
     */
    public function __construct(string $gatewayId)
    {
        $this->id = $gatewayId;
    }

    /**
     * @since 2.0.0
     */
    public function addUpdateRenewalSubscriptionActionHook()
    {
        add_action(
            'give_recurring_update_renewal_subscription',
            [$this, 'process_renewal_subscription_update',],
            10,
            3
        );

        add_action(
            "give_recurring_update_renewal_{$this->id}_subscription",
            [$this, 'update_subscription'],
            10,
            2
        );
    }

    /**
     * @since 2.0.0
     */
    public function addSubscriptionCancelActionHook()
    {
        add_action(
            'give_cancel_subscription',
            [$this, 'process_cancellation',]
        );

        add_action(
            "give_recurring_cancel_{$this->id}_subscription",
            [$this, 'cancelSubscription'],
            10,
            1
        );
    }

    /**
     * @since 2.0.0
     */
    public function addSubscriptionPaymentMethodUpdateActionHook()
    {
        add_action(
            'give_recurring_update_payment_form',
            [$this, 'update_payment_method_form'],
            10,
            1
        );

        add_action('give_recurring_update_subscription_payment_method', [
            $this,
            'process_payment_method_update',
        ], 10, 3);
        add_action(
            "give_recurring_update_{$this->id}_subscription",
            [$this, 'update_payment_method'],
            10,
            2
        );
    }

    /**
     * @since 2.1.2 Validate gateway existence before requesting gateway object.
     *
     * @since 2.0.0
     */
    public function addSyncSubscriptionActionHook()
    {
        $setupLegacyPaymentGatewayClass = static function () {
            $subscriptionId = absint($_POST['subscription_id']);

            if (!($subscription = Subscription::find($subscriptionId))) {
                return;
            }

            if ( ! give(PaymentGatewayRegister::class)->hasPaymentGateway($subscription->gatewayId)) {
                return;
            }

            $gateway = $subscription->gateway();

            add_filter(
                'give_recurring_gateway_factory_get_gateway',
                function ($legacyGatewayClassObject) use ($gateway) {
                    if (Stripe::isDonationPaymentMethod($gateway->id())) {
                        require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/give-recurring-stripe.php';

                        $legacyGatewayClassObject = new \Give_Recurring_Stripe();
                        $legacyGatewayClassObject->init();
                    }

                    if ('authorize' === $gateway->id()) {
                        require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/give-recurring-authorize.php';

                        $legacyGatewayClassObject = new \Give_Recurring_Authorize();
                        $legacyGatewayClassObject->init();
                    }

                    if ('authorize_echeck' === $gateway->id()) {
                        require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/give-recurring-authorize_echeck.php';

                        $legacyGatewayClassObject = new \Give_Recurring_Authorize_eCheck();
                        $legacyGatewayClassObject->init();
                    }

                    return $legacyGatewayClassObject;
                }
            );
        };


        add_action('wp_ajax_give_recurring_sync_subscription_details', $setupLegacyPaymentGatewayClass, 9);
        add_action('wp_ajax_give_recurring_sync_subscription_transactions', $setupLegacyPaymentGatewayClass, 9);
    }

    /**
     * @since 2.0.0
     *
     * @inerhitDoc
     */
    public function update_subscription($subscriber, $giveSubscription, $data = null)
    {
        try {
            /* @var Subscription $subscription */
            $subscription = Subscription::find($giveSubscription->id);
            $subscription->gateway()
                ->updateSubscriptionAmount(
                    $subscription,
                    Money::fromDecimal(
                        $this->getNewRenewalAmount(),
                        $subscription->amount->getCurrency()->getCode()
                    )
                );
        } catch (\Exception $e) {
            give_set_error(
                'give_recurring_update_subscription_amount',
                esc_html__(
                    'The payment gateway returned an error while updating the subscription.',
                    'give-recurring'
                )
            );

            Log::error(
                'Subscription Amount Update Failure',
                [
                    'Error' => $e->getMessage(),
                    'Subscription' => $subscription,
                    'Request Data' => give_clean($_POST),
                ]
            );
        }
    }

    /**
     * @since 2.3.0 Load Square form
     * @since      2.2.0 Load AcceptJs library for Authorize forms
     * @since      2.0.0
     *
     * @inerhitDoc
     * @throws InvalidCredentialsException
     */
    public function update_payment_method_form($subscription)
    {
        if ($subscription->gateway !== $this->id) {
            return;
        }

        // addCreditCardForm() only shows when Stripe Checkout is enabled so we fake it
        add_filter('give_get_option_stripe_checkout', '__return_false');

        // Remove Billing address fields.
        if (has_action('give_after_cc_fields', 'give_default_cc_address_fields')) {
            remove_action('give_after_cc_fields', 'give_default_cc_address_fields', 10);
        }

        $formId = ! empty($subscription->form_id) ? absint($subscription->form_id) : 0;
        $args['id_prefix'] = "$formId-1";

        if (Stripe::isDonationPaymentMethod($subscription->gateway)) {
            echo $this->getStripeCreditCardFormHTML($formId, $args);
        } elseif (class_exists(AcceptJs::class) &&
                  ('authorize' === $subscription->gateway || 'authorize_echeck' === $subscription->gateway)
        ) {
            AcceptJs::loadScript($formId);
            (new \Give_Recurring_Authorize())->update_payment_method_form($subscription);
        } elseif ('square' === $subscription->gateway && function_exists('give_square_credit_card_form')) {
            give_square_credit_card_form($formId, $args, true);
        } else {
            (new \Give_Recurring_Gateway())->update_payment_method_form($subscription);
        }
    }

    /**
     * @since 2.3.0 Handle Square gateway data.
     * @since      2.2.0 Handle Authorize.Net gateway data.
     * @since      2.1.2 Pass array instead of object to updateSubscriptionPaymentMethod()
     * @since      2.0.0
     *
     * @inerhitDoc
     */
    public function update_payment_method($subscriber, $giveSubscription, $data = null)
    {
        try {
            if (isset($_POST['give-recurring-update-gateway']) &&
                'authorize' === $_POST['give-recurring-update-gateway'] &&
                class_exists(AuthorizeGatewayData::class)) {
                $subscription = Subscription::find($giveSubscription->id);
                $subscription->gateway()->updateSubscriptionPaymentMethod($subscription, [
                    'authorizeGatewayData' => AuthorizeGatewayData::fromRequest(give_clean($_POST)),
                ]);

                return;
            }

            if (isset($_POST['give-recurring-update-gateway']) &&
                'square' === $_POST['give-recurring-update-gateway'] &&
                class_exists(GetSquareGatewayData::class)) {
                $subscription = Subscription::find($giveSubscription->id);
                $squareData = (new GetSquareGatewayData())(give_clean($_POST));
                $subscription->gateway()->updateSubscriptionPaymentMethod($subscription, $squareData);

                return;
            }

            if ( ! isset($_POST['give_stripe_payment_method'])) {
                throw new PaymentMethodException(esc_html__('Payment Method Not Found', 'give-recurring'));
            }

            /* @var Subscription $subscription */
            $subscription = Subscription::find($giveSubscription->id);
            $paymentMethodId = give_clean($_POST['give_stripe_payment_method']);
            $subscription->gateway()
                ->updateSubscriptionPaymentMethod($subscription,
                    [
                        'stripePaymentMethod' => new PaymentMethod($paymentMethodId),
                    ]);
        } catch (\Exception $e) {
            give_set_error(
                'give_recurring_update_subscription_payment_method',
                esc_html__(
                    'The payment gateway returned an error while updating the subscription payment method.',
                    'give-recurring'
                )
            );

            Log::error(
                'Subscription Payment Method Update Failure',
                [
                    'Error' => $e->getMessage(),
                    'Subscription' => $giveSubscription,
                    'Request Data' => give_clean($_POST),
                ]
            );
        }
    }

    /**
     * @since 2.0.0
     */
    public function cancelSubscription(Give_Subscription $giveSubscription)
    {
        /* @var Subscription $subscription */
        $subscription = Subscription::find($giveSubscription->id);
        $subscription->gateway()->cancelSubscription($subscription);
    }
}
