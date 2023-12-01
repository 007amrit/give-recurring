<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions;

use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Subscriptions\Models\Subscription;
use Give_Authorize;
use GiveAuthorizeNet\Actions\CreateMerchantAuthentication;
use GiveAuthorizeNet\Exceptions\InvalidCredentialsException;
use GiveAuthorizeNet\ValueObjects\AuthorizeApiResultCode;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\contract\v1\ANetApiResponseType;
use net\authorize\api\controller as AnetController;

/**
 * Cancels an existing subscription.
 *
 * @see https://developer.authorize.net/api/reference/index.html#recurring-billing-cancel-a-subscription
 *
 * @since 2.2.0
 */
class CancelSubscription
{
    /**
     * @since 2.2.0
     * @throws InvalidCredentialsException|PaymentGatewayException
     */
    public function __invoke(Subscription $subscription): bool
    {
        $merchantAuthentication = (new CreateMerchantAuthentication())();

        // Set the transaction's refId
        $refId = 'ref' . time();

        $request = new AnetAPI\ARBCancelSubscriptionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscriptionId($subscription->gatewaySubscriptionId);

        $controller = new AnetController\ARBCancelSubscriptionController($request);

        $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());

        if ( ! $apiResponse || $apiResponse->getMessages()->getResultCode() !== AuthorizeApiResultCode::OK) {
            $this->logErrorTransactionFailed($subscription, $apiResponse);
        }

        $this->logSuccessfulTransaction($subscription);

        return true;
    }

    /**
     * @since 2.2.0
     * @throws PaymentGatewayException
     */
    private function logErrorTransactionFailed(
        Subscription $subscription,
        AnetApiResponseType $apiResponse
    ) {
        $errorMessages = $apiResponse->getMessages()->getMessage();
        PaymentGatewayLog::error(
            sprintf('[Authorize.Net] Failed to cancel subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gateway()->getId(),
                'Subscription' => $subscription->id,
                'Error Code' => $errorMessages[0]->getCode(),
                'Error Message' => $errorMessages[0]->getText(),
            ]
        );

        throw new PaymentGatewayException(
            __('[Authorize.Net] Failed to cancel subscription. Error: ' .
               $errorMessages[0]->getCode() . ' - ' . $errorMessages[0]->getText(),
                'give-recurring')
        );
    }

    /**
     * @since 2.2.0
     */
    private function logSuccessfulTransaction(Subscription $subscription)
    {
        PaymentGatewayLog::success(
            sprintf('[Authorize.net] Success to cancel subscription %s', $subscription->id),
            [
                'Payment Gateway' => $subscription->gateway()->getId(),
                'Subscription' => $subscription->id,
            ]
        );
    }
}
