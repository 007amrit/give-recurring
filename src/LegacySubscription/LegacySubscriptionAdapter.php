<?php

namespace GiveRecurring\LegacySubscription;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\Subscriptions\Models\Subscription;
use Give_Subscription as LegacySubscription;
use ReflectionException;

/**
 * @since 2.0.0
 */
class LegacySubscriptionAdapter
{
    /**
     * @since 2.4.1 On the "give_checkout_error_checks" hook, Use hasPaymentGateway() method to check if the gateway is registered.
     * @since      2.0.0
     * @throws ReflectionException|Exception
     */
    public function __invoke()
    {
        $paymentGateways = give(PaymentGatewayRegister::class)->getPaymentGateways();

        foreach ($paymentGateways as $paymentGateway) {
            /** @var PaymentGateway $gateway */
            $gateway = give($paymentGateway);

            if (!$gateway->supportsSubscriptions()) {
                continue;
            }

            /*
             * Below filters handles the subscriber authorization for a specific action.
             * like: cancel subscription, update subscription amount.
             */
            add_filter(
                'give_subscription_can_cancel',
                static function ($canCancelSubscription, LegacySubscription $legacySubscription) use ($gateway) {
                    if (!$legacySubscription->id) {
                        return $canCancelSubscription;
                    }

                    if ($gateway::id() === $legacySubscription->gateway) {
                        return 'cancelled' !== $legacySubscription->status;
                    }

                    return $canCancelSubscription;
                },
                10,
                2
            );

            add_filter(
                'give_subscription_can_sync',
                static function ($canSyncSubscriptionWithPaymentGateway, LegacySubscription $legacySubscription) use ($gateway
                ) {
                    if (!$legacySubscription->id) {
                        return $canSyncSubscriptionWithPaymentGateway;
                    }

                    if ($gateway::id() === $legacySubscription->gateway) {
                        return $gateway->canSyncSubscriptionWithPaymentGateway();
                    }

                    return $canSyncSubscriptionWithPaymentGateway;
                },
                10,
                2
            );

            add_filter(
                'give_subscription_can_update_subscription',
                static function ($canUpdateSubscriptionAmount, LegacySubscription $legacySubscription) use ($gateway) {
                    if (!$legacySubscription->id) {
                        return $canUpdateSubscriptionAmount;
                    }

                    if ($gateway::id() === $legacySubscription->gateway) {
                        return in_array($legacySubscription->status, ['active', 'failing']) &&
                            $gateway->canUpdateSubscriptionAmount();
                    }

                    return $canUpdateSubscriptionAmount;
                },
                10,
                2
            );

            add_filter(
                'give_subscription_can_update',
                static function ($canUpdateSubscriptionPaymentMethod, LegacySubscription $legacySubscription) use ($gateway) {
                    if (!$legacySubscription->id) {
                        return $canUpdateSubscriptionPaymentMethod;
                    }

                    if ($gateway::id() === $legacySubscription->gateway) {
                        return in_array($legacySubscription->status, ['active', 'failing']) &&
                            $gateway->canUpdateSubscriptionPaymentMethod();
                    }

                    return $canUpdateSubscriptionPaymentMethod;
                },
                10,
                2
            );

            /*
             * This handles to display gateway subscription id with link on subscription detail page.
             */
            add_filter(
                'give_subscription_profile_link_' . $gateway::id(),
                static function ($gatewaySubscriptionId, LegacySubscription $legacySubscription) use ($gateway) {
                    if (!$gatewaySubscriptionId) {
                        return false;
                    }

                    if ($gateway->hasGatewayDashboardSubscriptionUrl()) {
                        /** @var Subscription $subscription */
                        $subscription = Subscription::find($legacySubscription->id);

                        return sprintf(
                            '<a href="%1$s" target="_blank" title="%2$s">%3$s</a>',
                            esc_attr(
                                esc_url($gateway->gatewayDashboardSubscriptionUrl($subscription))
                            ),
                            esc_html__('Gateway subscription detail page', 'give-recurring'),
                            $gatewaySubscriptionId
                        );
                    }

                    return $gatewaySubscriptionId;
                },
                10,
                2
            );

            /*
             * This handles the case where new donor must have an account on website to perform subscription.
             * This will set an error otherwise which will display on frontend.
             */
            add_action(
                'give_checkout_error_checks',
                static function () use ($gateway) {
                    // Donor must have an account to create a subscription
                    if (is_user_logged_in() || give_is_setting_enabled(give_get_option('email_access'))) {
                        return;
                    }

                    // This error notice is only for subscription donation.
                    $isSubscriptionDonation = ! empty($_POST['_give_is_donation_recurring']);
                    if ( ! $isSubscriptionDonation) {
                        return;
                    }

                    if ( ! give(PaymentGatewayRegister::class)->hasPaymentGateway(give_clean($_POST['give-gateway']))) {
                        return;
                    }

                    $donationGateway = give(PaymentGatewayRegister::class)->getPaymentGateway(
                        give_clean($_POST['give-gateway'])
                    );

                    if ($gateway::id() !== $donationGateway::id()) {
                        return;
                    }

                    // Create account checkbox should be checked to create account for donor.
                    if (empty($_POST['give_create_account'])) {
                        give_set_error(
                            'recurring_create_account',
                            esc_html__(
                                'Please tick the create account button if you want to create a subscription donation',
                                'give-recurring'
                            )
                        );
                    }
                },
                0
            );

            /*
            * This class handles setup legacy hook listeners to perform action on subscription.
            */
            $mockLegacyGiveRecurringGateway = new MockGiveRecurringGatewaySubClass($gateway::id());

            /*
             * This handles the subscription amount update request from legacy subscription history shortcode.
             */
            if ($gateway->canUpdateSubscriptionAmount()) {
                $mockLegacyGiveRecurringGateway->addUpdateRenewalSubscriptionActionHook();
            }

            /*
            * This handles the subscription payment method  update request from legacy subscription history shortcode.
            */
            if ($gateway->canUpdateSubscriptionPaymentMethod()) {
                $mockLegacyGiveRecurringGateway->addSubscriptionPaymentMethodUpdateActionHook();
            }

            /*
             * This handles the subscription payment method  update request from legacy subscription history shortcode.
            */
            if ($gateway->canSyncSubscriptionWithPaymentGateway()) {
                $mockLegacyGiveRecurringGateway->addSyncSubscriptionActionHook();
            }

            /*
             * This handles the subscription cancellation request from legacy subscription history shortcode.
             */
            $mockLegacyGiveRecurringGateway->addSubscriptionCancelActionHook();
        }
    }
}
