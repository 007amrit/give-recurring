<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\EventHandlers;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use GiveAuthorizeNet\Actions\GetTransactionDetails;

class HandleSubscriptionDonations
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

        $transactionDetails = (new GetTransactionDetails())($gatewayTransactionId);

        if ( ! $transactionDetails) {
            PaymentGatewayLog::error(
                sprintf('[Authorize.Net] The gateway did not respond to an attempt to retrieve transaction details for the transaction id %s',
                    $gatewayTransactionId),
                [
                    'Gateway Transaction Id' => $gatewayTransactionId,
                ]
            );

            wp_die();
        }

        $gatewaySubscriptionId = $transactionDetails->getSubscription()->getId();
        $subscriptionPayNum = $transactionDetails->getSubscription()->getPayNum();
        $subscription = give()->subscriptions->getByGatewaySubscriptionId($gatewaySubscriptionId);

        if ( ! $gatewaySubscriptionId || ! $subscriptionPayNum || ! $subscription) {
            PaymentGatewayLog::error(
                sprintf('[Authorize.Net] The gateway did not respond to an attempt to retrieve subscription details for the transaction id %s',
                    $gatewayTransactionId),
                [
                    'Gateway Transaction Id' => $gatewayTransactionId,
                    'Gateway Subscription Id' => $gatewaySubscriptionId,
                    'Gateway Subscription payNum' => $subscriptionPayNum,
                    'Subscription' => $subscription,
                ]
            );

            wp_die();
        }

        PaymentGatewayLog::debug(
            sprintf(
                '[Authorize.Net] Webhooks: data before handle donation for subscription %s.',
                $subscription->id
            ),
            [
                '$subscriptionPayNum' => $subscriptionPayNum,
                '$gatewaySubscriptionId' => $gatewaySubscriptionId,
                '$gatewayTransactionId' => $gatewayTransactionId,
            ]
        );

        $donation = $this->updateOrCreateSubscriptionDonation(
            $gatewayTransactionId,
            $subscriptionPayNum,
            $subscription
        );

        if (empty($message)) {
            $message = __('Subscription Donation Completed.', 'give-recurring');
        }

        DonationNote::create([
            'donationId' => $donation->id,
            'content' => $message . ' ' . sprintf(
                    __('Transaction ID: %s', 'give-recurring'),
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
     * @since 2.2.0
     *
     * @throws Exception
     */
    public function updateOrCreateSubscriptionDonation(
        string $gatewayTransactionId,
        int $subscriptionPayNum,
        Subscription $subscription
    ): Donation {
        if ($this->isFirstDonation($subscriptionPayNum, $subscription)) {
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
     * @since 2.2.0
     */
    private function isFirstDonation(int $subscriptionPayNum, Subscription $subscription): bool
    {
        return $subscriptionPayNum === 1 && empty($subscription->initialDonation()->gatewayTransactionId);
    }

    /**
     * @since 2.2.0
     */
    private function isOneTimeDonation(string $gatewayTransactionId): bool
    {
        return (bool)give()->donations->getByGatewayTransactionId($gatewayTransactionId);
    }
}
