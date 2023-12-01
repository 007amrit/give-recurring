<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square;

use Exception;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\SubscriptionComplete;
use Give\Framework\PaymentGateways\Commands\SubscriptionProcessing;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionAmountEditable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionDashboardLinkable;
use Give\Framework\PaymentGateways\Contracts\Subscription\SubscriptionPaymentMethodEditable;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Framework\PaymentGateways\SubscriptionModule;
use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Actions\CreateSquareSubscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Actions\GetSquareInvoice;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Actions\UpdateSquareSubscriptionAmount;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Actions\UpdateSquareSubscriptionPaymentMethod;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\CancelSubscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Api\GetSubscription;
use GiveRecurring\PaymentGatewayModules\Modules\Square\ValueObjects\SquareInvoiceStatus;
use GiveSquare\Square\Api\Exceptions\ApiRequestException;


/**
 * @since 2.3.0
 */
class SquareGatewaySubscriptionModule extends SubscriptionModule implements SubscriptionAmountEditable,
                                                                            SubscriptionPaymentMethodEditable,
                                                                            SubscriptionDashboardLinkable
{
    /**
     * @since 2.3.0
     *
     * @inheritDoc
     * @throws PaymentGatewayException
     */
    public function createSubscription(
        Donation $donation,
        Subscription $subscription,
        $gatewayData
    ) {
        try {
            $squareSourceId = $gatewayData['squarePaymentMethodId'];
            $squarePlanName = $this->getSquarePlanName($donation, $subscription);
            $gatewaySubscriptionId = (new CreateSquareSubscription())($subscription, $donation, $squareSourceId,
                $squarePlanName);
            $subscription->gatewaySubscriptionId = $gatewaySubscriptionId;
            $subscription->save();

            /**
             * If we use this 4 seconds of sleep we can obtain the first transaction with the "PAID" status and set the
             * subscription and the first donation as "complete", if not, we need to set the subscription as "processing"
             * and handle the first donation through webhooks later. It happens because the API takes some time to return
             * the correct result. So, this sleep value was defined with the team after a few tests with different values
             * where we find that any value of fewer than 4 seconds didn't work.
             */
            sleep(4); // Without this sleep, the 'invoice_ids' field usually return null.
            $squareSubscription = (new GetSubscription())($gatewaySubscriptionId);
            $squareInvoiceId = end($squareSubscription->invoice_ids); // newest invoices appear first

            if ($squareInvoiceId) {
                $squareInvoice = (new GetSquareInvoice())($squareInvoiceId);
                $gatewayTransactionId = $squareInvoice->id;

                $donation->gatewayTransactionId = $gatewayTransactionId;
                $donation->save();

                if (SquareInvoiceStatus::PAID()->getValue() === $squareInvoice->status) {
                    return new SubscriptionComplete($gatewayTransactionId, $gatewaySubscriptionId);
                }
            }

            return new SubscriptionProcessing($gatewaySubscriptionId);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            if ($e instanceof ApiRequestException) {
                $errorMessage = $e->getSquareErrorMessage();
            }

            throw new PaymentGatewayException($errorMessage);
        }
    }

    /**
     * @since 2.3.0
     *
     * @inheritDoc
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            $canceledDate = (new CancelSubscription())($subscription->gatewaySubscriptionId);
            $subscription->status = SubscriptionStatus::CANCELLED();
            $subscription->save();

            PaymentGatewayLog::success(
                sprintf(__('[Square] The gateway will finalize the cancellation for the subscription %s at %s.',
                    'give-square'), $subscription->id, $canceledDate),
                [
                    'Gateway Subscription ID' => $subscription->gatewaySubscriptionId,
                ]
            );
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            if ($e instanceof ApiRequestException) {
                $errorMessage = $e->getSquareErrorMessage();
            }
            throw new PaymentGatewayException($errorMessage);
        }

        return true;
    }

    /**
     * @since 2.3.0
     *
     * @inheritDoc
     *
     * @throws PaymentGatewayException
     */
    public function updateSubscriptionAmount(Subscription $subscription, Money $newRenewalAmount): bool
    {
        if ($subscription->amount->formatToDecimal() !== $newRenewalAmount->formatToDecimal()) {
            try {
                (new UpdateSquareSubscriptionAmount())($subscription->gatewaySubscriptionId, $newRenewalAmount);
                $subscription->amount = $newRenewalAmount;
                $subscription->save();
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();

                if ($e instanceof ApiRequestException) {
                    $errorMessage = $e->getSquareErrorMessage();
                }
                throw new PaymentGatewayException($errorMessage);
            }
        }

        return true;
    }

    /**
     * @since 2.3.0
     *
     * @inheritDoc
     *
     * @throws PaymentGatewayException
     */
    public function updateSubscriptionPaymentMethod(Subscription $subscription, $gatewayData)
    {
        try {
            $squareSourceId = $gatewayData['squarePaymentMethodId'];
            (new UpdateSquareSubscriptionPaymentMethod())($subscription, $squareSourceId);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            if ($e instanceof ApiRequestException) {
                $errorMessage = $e->getSquareErrorMessage();
            }
            throw new PaymentGatewayException($errorMessage);
        }
    }

    /**
     * @since 2.3.0
     *
     * @inheritDoc
     */
    public function gatewayDashboardSubscriptionUrl(Subscription $subscription): string
    {
        $squareSubscriptionURL = $subscription->initialDonation()->mode->getValue() == 'live'
            ? "https://squareup.com/dashboard/subscriptions/$subscription->gatewaySubscriptionId"
            : "https://squareupsandbox.com/dashboard/subscriptions/$subscription->gatewaySubscriptionId";

        return esc_url($squareSubscriptionURL);
    }

    /**
     * @since 2.4.1 Updated to include the donations form title as the subscription plan name.
     *
     * @since      2.3.0
     */
    private function getSquarePlanName(Donation $donation, Subscription $subscription): string
    {
        return $donation->formTitle . " (Subscription ID: " . $subscription->id . ")";
    }
}
