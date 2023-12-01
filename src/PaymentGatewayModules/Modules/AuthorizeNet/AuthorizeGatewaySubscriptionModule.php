<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet;

use Exception;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\SubscriptionProcessing;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionAmountEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionDashboardLinkable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionPaymentMethodEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionTransactionsSynchronizable;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions\CancelSubscription;
use GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions\CreateSubscription;
use GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions\UpdateSubscriptionAmount;
use GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions\UpdateSubscriptionPaymentMethod;

/**
 * @since 2.2.0
 */
class AuthorizeGatewaySubscriptionModule extends SubscriptionModule implements SubscriptionAmountEditable,
                                                                               SubscriptionPaymentMethodEditable,
                                                                               SubscriptionTransactionsSynchronizable,
                                                                               SubscriptionDashboardLinkable
{
    /**
     * @since 2.2.0
     *
     * @throws PaymentGatewayException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ): SubscriptionProcessing {
        try {
            $authorizeData = $gatewayData['authorizeGatewayData'];
            $gatewaySubscriptionId = (new CreateSubscription())($donation, $subscription, $authorizeData);
            $subscription->gatewaySubscriptionId = $gatewaySubscriptionId;
            $subscription->save();
        } catch (Exception $e) {
            throw new PaymentGatewayException($e->getMessage());
        }

        return new SubscriptionProcessing($gatewaySubscriptionId);
    }

    /**
     * @since 2.2.0
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            (new CancelSubscription())($subscription);
            $subscription->status = SubscriptionStatus::CANCELLED();
            $subscription->save();
        } catch (Exception $e) {
            throw new PaymentGatewayException($e->getMessage());
        }

        return true;
    }

    /**
     * @since 2.2.0
     *
     * @inheritDoc
     */
    public function canUpdateSubscriptionPaymentMethod(): bool
    {
        return 'authorize_echeck' !== $this->gateway->getId();
    }

    /**
     * @since 2.2.0
     *
     * @param array $gatewayData
     *
     * @throws PaymentGatewayException
     */
    public function updateSubscriptionPaymentMethod(Subscription $subscription, $gatewayData): bool
    {
        try {
            $authorizeData = $gatewayData['authorizeGatewayData'];
            (new UpdateSubscriptionPaymentMethod())($subscription, $authorizeData);
        } catch (Exception $e) {
            throw new PaymentGatewayException($e->getMessage());
        }

        return true;
    }

    /**
     * @since 2.2.0
     *
     * @throws PaymentGatewayException
     */
    public function updateSubscriptionAmount(Subscription $subscription, Money $newRenewalAmount): bool
    {
        if ($subscription->amount->formatToDecimal() !== $newRenewalAmount->formatToDecimal()) {
            try {
                (new UpdateSubscriptionAmount())($subscription, $newRenewalAmount);
                $subscription->amount = $newRenewalAmount;
                $subscription->save();
            } catch (Exception $e) {
                throw new PaymentGatewayException($e->getMessage());
            }
        }

        return true;
    }

    /**
     * @since 2.2.0
     */
    public function synchronizeSubscription(Subscription $subscription)
    {
        // TODO: Implement synchronizeSubscription() method.
        // We are processing sync subscription request with legacy code (MockLegacyGiveRecurringGateway::addSyncSubscriptionActionHook)
    }

    /**
     * @since 2.2.0
     */
    public function gatewayDashboardSubscriptionUrl(Subscription $subscription): string
    {
        $authorizeSubscriptionURL = $this->isLivePayment($subscription->initialDonation()->id) ?
            "https://account.authorize.net/UI/themes/anet/ARB/SubscriptionDetail.aspx?SubscrID=$subscription->gatewaySubscriptionId" :
            "https://sandbox.authorize.net/ui/themes/sandbox/ARB/SubscriptionDetail.aspx?SubscrID=$subscription->gatewaySubscriptionId";

        return esc_url($authorizeSubscriptionURL);
    }

    /**
     * @since 2.2.0
     */
    private function isLivePayment(int $donationId): bool
    {
        return Give()->payment_meta->get_meta($donationId, '_give_payment_mode', true) === 'live';
    }
}
