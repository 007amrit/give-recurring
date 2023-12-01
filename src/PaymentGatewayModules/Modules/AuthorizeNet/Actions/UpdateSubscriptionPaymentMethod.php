<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions;

use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Subscriptions\Models\Subscription;
use Give_Authorize;
use GiveAuthorizeNet\Actions\CreateMerchantAuthentication;
use GiveAuthorizeNet\DataTransferObjects\AuthorizeGatewayData;
use GiveAuthorizeNet\Exceptions\InvalidCredentialsException;
use GiveAuthorizeNet\ValueObjects\AuthorizeApiResultCode;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\contract\v1\ANetApiResponseType;
use net\authorize\api\controller as AnetController;

/**
 * Updates an existing ARB subscription. Only the subscription ID and fields that you wish to modify must be submitted.
 *
 * @see https://developer.authorize.net/api/reference/index.html#recurring-billing-update-a-subscription
 *
 * @since 2.2.0
 */
class UpdateSubscriptionPaymentMethod
{
    /**
     * @since 2.2.0
     *
     * @throws InvalidCredentialsException|PaymentGatewayException
     */
    public function __invoke(Subscription $subscription, AuthorizeGatewayData $authorizeData): bool
    {
        $merchantAuthentication = (new CreateMerchantAuthentication())();

        // Set the transaction's refId
        $refId = 'ref' . time();

        $apiSubscription = new AnetAPI\ARBSubscriptionType();

        // Create the payment object for a payment nonce
        $opaqueData = new AnetAPI\OpaqueDataType();
        $opaqueData->setDataDescriptor($authorizeData->dataDescriptor);
        $opaqueData->setDataValue($authorizeData->dataValue);

        $payment = new AnetAPI\PaymentType();
        $payment->setOpaqueData($opaqueData);

        $apiSubscription->setPayment($payment);

        $request = new AnetAPI\ARBUpdateSubscriptionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscriptionId($subscription->gatewaySubscriptionId);
        $request->setSubscription($apiSubscription);

        $controller = new AnetController\ARBUpdateSubscriptionController($request);

        /**
         * [First try] Prevent "E00114 Invalid OTS Token" error return.
         *
         * @see https://stackoverflow.com/a/52107364
         */
        sleep(5);

        $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());

        /**
         * [Second try] Prevent "E00114 Invalid OTS Token" error return.
         *
         * @see https://stackoverflow.com/a/52107364
         */
        if ( ! $apiResponse || $apiResponse->getMessages()->getResultCode() !== AuthorizeApiResultCode::OK) {
            sleep(2);
            $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());
        }

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
            sprintf('[Authorize.Net] Failed to update payment method for subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gateway()->getId(),
                'Subscription' => $subscription->id,
                'Error Code' => $errorMessages[0]->getCode(),
                'Error Message' => $errorMessages[0]->getText(),
            ]
        );

        throw new PaymentGatewayException(
            __('[Authorize.Net] Fail to update payment method. Error: ' .
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
            sprintf('[Authorize.net] Success to update payment method for subscription %s', $subscription->id),
            [
                'Payment Gateway' => $subscription->gateway()->getId(),
                'Subscription' => $subscription->id,
            ]
        );
    }
}
