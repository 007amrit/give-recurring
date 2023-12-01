<?php

namespace GiveRecurring\PaymentGateways;

use Give\Donations\Models\Donation;
use Give\Helpers\Hooks;
use Give\PaymentGateways\Gateways\Stripe\Actions\GetPaymentMethodFromRequest;
use Give\PaymentGateways\Gateways\Stripe\BECSGateway;
use Give\PaymentGateways\Gateways\Stripe\CheckoutGateway;
use Give\PaymentGateways\Gateways\Stripe\CreditCardGateway;
use Give\PaymentGateways\Gateways\Stripe\Exceptions\PaymentMethodException;
use Give\PaymentGateways\Gateways\Stripe\SEPAGateway;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\PaymentMethod;
use Give\PaymentGateways\PayPalCommerce\PayPalCommerce as GivePayPalCommerce;
use Give\PaymentGateways\PayPalCommerce\Webhooks\WebhookRegister;
use Give\ServiceProviders\ServiceProvider;
use Give_Subscription;
use GiveRecurring\Infrastructure\View;
use GiveRecurring\PaymentGateways\PayPalCommerce\AjaxRequestHandler;
use GiveRecurring\PaymentGateways\PayPalCommerce\HttpHeader;
use GiveRecurring\PaymentGateways\PayPalCommerce\PayPalCommerce;
use GiveRecurring\PaymentGateways\PayPalCommerce\SubscriptionProcessor;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionActivated;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionCancelled;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionExpired;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionPaymentFailed;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionSuspended;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\BillingSubscriptionUpdated;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\PaymentSaleCompleted;
use GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners\PaymentSaleRefunded;
use GiveRecurring\PaymentGateways\Stripe\Actions\GetPaymentMethodFromRequestForPlaidGateway;
use GiveRecurring\PaymentGateways\Stripe\ApplePayGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\BECSGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\CheckoutGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\CreditCardGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\GooglePayGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\PlaidGatewayModule;
use GiveRecurring\PaymentGateways\Stripe\SEPAGatewayModule;
use GiveStripe\PaymentMethods\ApplePay\ApplePayGateway;
use GiveStripe\PaymentMethods\GooglePay\GooglePayGateway;
use GiveStripe\PaymentMethods\Plaid\PlaidGateway;
use InvalidArgumentException;

class PaymentGateways implements ServiceProvider
{
    /**
     * @var array
     */
    private $webhookListeners = [
        PaymentSaleCompleted::WEBHOOK_ID => PaymentSaleCompleted::class,
        PaymentSaleRefunded::WEBHOOK_ID => PaymentSaleRefunded::class,
        BillingSubscriptionExpired::WEBHOOK_ID => BillingSubscriptionExpired::class,
        BillingSubscriptionCancelled::WEBHOOK_ID => BillingSubscriptionCancelled::class,
        BillingSubscriptionActivated::WEBHOOK_ID => BillingSubscriptionActivated::class,
        BillingSubscriptionPaymentFailed::WEBHOOK_ID => BillingSubscriptionPaymentFailed::class,
        BillingSubscriptionSuspended::WEBHOOK_ID => BillingSubscriptionSuspended::class,
        BillingSubscriptionUpdated::WEBHOOK_ID => BillingSubscriptionUpdated::class
    ];

    /**
     * @inheritDoc
     */
    public function register()
    {
        give()->bind('PAYPAL_COMMERCE_SUBSCRIPTION_ATTRIBUTION_ID', static function () {
            return 'GiveWP_SP_Migration';
        }); // storage

        // Load recurring gateway class.
        require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/give-recurring-gateway.php';

        $this->registerPayPalCommerceClasses();
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        $gatewayId = GivePayPalCommerce::GATEWAY_ID;

        give()->singleton(PayPalCommerce::class);

        // Initialize class.
        give(PayPalCommerce::class);

        Hooks::addFilter('give_recurring_available_gateways', __CLASS__, 'registerRecurringPaymentGateway', 10, 1);
        Hooks::addAction('template_redirect', SubscriptionProcessor::class, 'setSubscriptionFailed', 10, 1);
        Hooks::addAction(
            'give_subscription_deleted',
            SubscriptionProcessor::class,
            'handleSubscriptionDeletion',
            11,
            3
        );
        Hooks::addAction(
            'give_subscription_updated',
            SubscriptionProcessor::class,
            'handleSubscriptionStatusChange',
            11,
            3
        );
        Hooks::addAction('wp_ajax_give_paypal_commerce_create_plan_id', AjaxRequestHandler::class, 'createPlanId', 10);
        Hooks::addAction(
            'wp_ajax_nopriv_give_paypal_commerce_create_plan_id',
            AjaxRequestHandler::class,
            'createPlanId',
            10
        );
        Hooks::addAction('give_recurring_add_subscription_detail', __CLASS__, 'addSubscriptionStatusOptInField');
        Hooks::addFilter(
            "give_recurring_gateway_statues_for_optin_{$gatewayId}",
            PayPalCommerce::class,
            'getSubscriptionStatuesForOptIn'
        );

        give(WebhookRegister::class)->registerEventHandlers($this->webhookListeners);
        $this->registerSubscriptionModules();
    }

    /**
     * Register PayPal Commerce related classes.
     *
     * @since 1.11.0
     */
    private function registerPayPalCommerceClasses()
    {
        give()->singleton(SubscriptionProcessor::class);
        give()->singleton(HttpHeader::class);
    }

    /**
     * Register payment gateway as recurring payment gateway.
     *
     * @since 1.11.0
     *
     * @param $availableGateway
     *
     * @return array
     */
    public function registerRecurringPaymentGateway($availableGateway)
    {
        $availableGateway[GivePayPalCommerce::GATEWAY_ID] = PayPalCommerce::class;

        return $availableGateway;
    }

    /**
     * Render subscription status opt-in field.
     *
     * @since 1.11.0
     */
    public function addSubscriptionStatusOptInField()
    {
        View::load('admin/subscription-status-optin-field', [
            'subscription' => new Give_Subscription(absint($_GET['id']))
        ], true);
    }

    /**
     * @since 2.0.0
     * @throws PaymentMethodException
     */
    private function registerSubscriptionModules()
    {
        $stripePaymentMethods = [
            CreditCardGateway::id() => CreditCardGatewayModule::class,
            SEPAGateway::id() => SEPAGatewayModule::class,
            BECSGateway::id() => BECSGatewayModule::class,
            CheckoutGateway::id() => CheckoutGatewayModule::class,
        ];

        // Conditionally add the Stripe add-on classes
        if (class_exists(ApplePayGateway::class)) {
            $stripePaymentMethods[ApplePayGateway::id()] = ApplePayGatewayModule::class;
        }

        if (class_exists(GooglePayGateway::class)) {
            $stripePaymentMethods[GooglePayGateway::id()] = GooglePayGatewayModule::class;
        }

        if (class_exists(PlaidGateway::class)) {
            $stripePaymentMethods[PlaidGateway::id()] = PlaidGatewayModule::class;
        }

        foreach ($stripePaymentMethods as $gatewayId => $moduleClassName) {
            add_filter(
                "givewp_gateway_{$gatewayId}_subscription_module",
                function () use ($gatewayId, $moduleClassName) {
                    $this->registerStripeSubscriptionModuleFilterHooks($gatewayId, $moduleClassName);
                    return $moduleClassName;
                }
            );
        }
    }

    /**
     * @since 2.1.2 Set correct payment method from request for Stripe payment method.
     * @since 2.0.0
     * @throws PaymentMethodException
     */
    private function registerStripeSubscriptionModuleFilterHooks(string $gatewayId, string $moduleClassName)
    {
        add_filter(
            "givewp_create_subscription_gateway_data_{$gatewayId}",
            function (array $gatewayData, Donation $donation) use ($moduleClassName) {
                switch ($moduleClassName) {
                    case CreditCardGatewayModule::class:
                    case CheckoutGatewayModule::class:
                    case SEPAGatewayModule::class:
                    case BECSGatewayModule::class:
                    case ApplePayGatewayModule::class:
                    case GooglePayGatewayModule::class:
                        $getPaymentMethodFromRequest = new GetPaymentMethodFromRequest();
                        $gatewayData['stripePaymentMethod'] = $getPaymentMethodFromRequest($donation);
                        break;
                    case PlaidGatewayModule::class:
                         $getPaymentMethodFromRequest = new GetPaymentMethodFromRequestForPlaidGateway();
                         $gatewayData['stripePaymentMethod'] = $getPaymentMethodFromRequest($donation);
                        break;
                    default:
                        throw new InvalidArgumentException('Invalid Stripe payment method.');
                }

                return $gatewayData;
            }
            , 10, 2
        );

        add_filter(
            "givewp_donor_dashboard_edit_subscription_payment_method_gateway_data_{$gatewayId}",
            function ($gatewayData) {
                if (!isset($gatewayData['give_stripe_payment_method'])) {
                    throw new PaymentMethodException(esc_html__('Payment Method Not Found', 'give-recurring'));
                }

                $gatewayData['stripePaymentMethod'] = new PaymentMethod($gatewayData['give_stripe_payment_method']);

                return $gatewayData;
            },
            10,
            2
        );
    }
}
