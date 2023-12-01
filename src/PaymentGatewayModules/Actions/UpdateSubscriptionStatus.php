<?php

namespace GiveRecurring\PaymentGatewayModules\Actions;

use Exception;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;

class UpdateSubscriptionStatus
{
    /**
     * @since 2.2.0
     *
     * @throws Exception
     */
    public function __invoke(
        Subscription $subscription,
        SubscriptionStatus $status,
        string $gatewaySubscriptionId,
        string $message = ''
    ) {
        $subscription->status = $status;
        $subscription->save();

        if (empty($message)) {
            $message = $this->getMessageFromStatus($status);
        }

        PaymentGatewayLog::info(
            $message . ' ' . sprintf('Subscription ID: %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Gateway Subscription Id' => $gatewaySubscriptionId,
                'Subscription ID' => $subscription->id,
            ]
        );
    }


    /**
     * @since 2.2.0
     */
    protected function getMessageFromStatus(SubscriptionStatus $status): string
    {
        $message = '';

        switch (true):
            case ($status->isCompleted()):
                $message = __('Subscription Completed.', 'give-recurring');
                break;
            case ($status->isExpired()):
                $message =  __('Subscription Expired.', 'give-recurring');
                break;
            case ($status->isActive()):
                $message = __('Subscription Active.', 'give-recurring');
                break;
            case ($status->isCancelled()):
                $message = __('Subscription Cancelled.', 'give-recurring');
                break;
            case ($status->isFailing()):
                $message = __('Subscription Failing.', 'give-recurring');
                break;
            case ($status->isSuspended()):
                $message = __('Subscription Suspended.', 'give-recurring');
                break;
        endswitch;

        return $message;
    }
}
