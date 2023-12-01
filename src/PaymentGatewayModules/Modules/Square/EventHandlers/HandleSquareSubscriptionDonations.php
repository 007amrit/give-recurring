<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\EventHandlers;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveRecurring\PaymentGatewayModules\Modules\Square\Actions\GetSquareInvoice;
use GiveRecurring\PaymentGatewayModules\Modules\Square\ValueObjects\SquareInvoiceStatus;

/**
 * @since 2.3.0
 */
class HandleSquareSubscriptionDonations
{
    /**
     * This event handler will either update the initial subscription donation, or create a renewal
     *
     * @since 2.2.0
     *
     * @throws Exception
     */
    public function run(string $gatewayTransactionId, string $message = '')
    {
        if ($this->isOneTimeDonation($gatewayTransactionId)) {
            wp_die();
        }

        if ($this->isDonationAlreadyHandled($gatewayTransactionId)) {
            wp_die();
        }

        try {
            $squareInvoice = (new GetSquareInvoice())($gatewayTransactionId);
        } catch (Exception $e) {
            wp_die();
        }

        $gatewaySubscriptionId = $squareInvoice->subscription_id;
        $subscription = give()->subscriptions->getByGatewaySubscriptionId($gatewaySubscriptionId);

        if ( ! $subscription) {
            PaymentGatewayLog::error(
                sprintf('[Square] The invoice id %s is not associated with a GiveWP subscription.',
                    $gatewayTransactionId),
                [
                    'Square Invoice Status' => $squareInvoice->status,
                    'Gateway Transaction Id' => $gatewayTransactionId,
                    'Gateway Subscription Id' => $gatewaySubscriptionId,
                    'Subscription' => $subscription,
                    'Square Invoice' => $squareInvoice,
                ]
            );

            wp_die();
        }

        if (SquareInvoiceStatus::PAID()->getValue() !== $squareInvoice->status) {
            PaymentGatewayLog::error(
                sprintf('[Square] The invoice id %s is not PAID.',
                    $gatewayTransactionId),
                [
                    'Square Invoice Status' => $squareInvoice->status,
                    'Gateway Transaction Id' => $gatewayTransactionId,
                    'Gateway Subscription Id' => $gatewaySubscriptionId,
                    'Subscription' => $subscription,
                    'Square Invoice' => $squareInvoice,
                ]
            );

            wp_die();
        }

        PaymentGatewayLog::debug(
            sprintf(
                '[Square] Webhooks: data before handle donation for subscription %s.',
                $subscription->id
            ),
            [
                'Square Invoice Status' => $squareInvoice->status,
                'Gateway Transaction Id' => $gatewayTransactionId,
                'Gateway Subscription Id' => $gatewaySubscriptionId,
                'Subscription' => $subscription,
                'Square Invoice' => $squareInvoice,
            ]
        );

        $donation = $this->updateOrCreateSubscriptionDonation(
            $gatewayTransactionId,
            $subscription
        );

        if (empty($message)) {
            $message = __('Subscription Donation Completed.', 'give-square');
        }

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => $message . ' ' . sprintf(
                    __('Transaction ID: %s', 'give-square'),
                    $donation->gatewayTransactionId
                ),
        ]);

        PaymentGatewayLog::success(
            $message . ' ' . sprintf('Donation ID: %s.', $donation->id),
            [
                'Payment Gateway' => $donation->gateway()->getId(),
                'Gateway Transaction Id' => $donation->gatewayTransactionId,
                'Donation' => $donation->id,
                'Subscription' => $donation->subscription->id,
            ]
        );

        wp_die();
    }

    /**
     * @since 2.3.0
     *
     * @throws Exception
     */
    public function updateOrCreateSubscriptionDonation(
        string $gatewayTransactionId,
        Subscription $subscription
    ): Donation {
        if ($this->isFirstDonation($subscription)) {
            $donation = $subscription->initialDonation();
            $donation->gatewayTransactionId = $gatewayTransactionId;
            $donation->status = DonationStatus::COMPLETE();
            $donation->save();

            $subscription->status = SubscriptionStatus::ACTIVE();
            $subscription->save();

            return $donation;
        }

        return Donation::create([
            'subscriptionId' => $subscription->id,
            'amount' => $subscription->amount,
            'status' => DonationStatus::RENEWAL(),
            'type' => DonationType::RENEWAL(),
            'donorId' => $subscription->donor->id,
            'firstName' => $subscription->donor->firstName,
            'lastName' => $subscription->donor->lastName,
            'email' => $subscription->donor->email,
            'gatewayId' => $subscription->gatewayId,
            'formId' => $subscription->donationFormId,
            'levelId' => $subscription->initialDonation()->levelId,
            'anonymous' => $subscription->initialDonation()->anonymous,
            'company' => $subscription->initialDonation()->company,
            'gatewayTransactionId' => $gatewayTransactionId,
        ]);
    }

    /**
     * @since 2.3.0
     */
    private function isFirstDonation(Subscription $subscription): bool
    {
        return ! $subscription->status->isActive() || empty($subscription->initialDonation()->gatewayTransactionId);
    }

    /**
     * @since 2.3.0
     */
    private function isOneTimeDonation(string $gatewayTransactionId): bool
    {
        $donation = give()->donations->getByGatewayTransactionId($gatewayTransactionId);

        return $donation && $donation->type->isSingle();
    }

    /**
     * @since 2.3.0
     */
    private function isDonationAlreadyHandled(string $gatewayTransactionId): bool
    {
        $donation = give()->donations->getByGatewayTransactionId($gatewayTransactionId);

        return isset($donation->id) && ($donation->status->isComplete() || $donation->status->isRenewal());
    }
}
