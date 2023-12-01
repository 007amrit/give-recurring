<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions;

use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Framework\Support\ValueObjects\Money;
use Give\Subscriptions\Models\Subscription;
use Give_Authorize;
use GiveAuthorizeNet\Actions\CreateMerchantAuthentication;
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
class UpdateSubscriptionAmount
{
    /**
     * @since 2.2.0
     *
     * @throws InvalidCredentialsException|PaymentGatewayException
     */
    public function __invoke(Subscription $subscription, Money $newRenewalAmount): bool
    {
        $gatewaySubscriptionId = $subscription->gatewaySubscriptionId;

        if ( ! $gatewaySubscriptionId) {
            throw new PaymentGatewayException(__('[Authorize.Net] Update subscription amount cannot be done without a gateway subscription id.',
                'give-recurring'));
        }

        $merchantAuthentication = (new CreateMerchantAuthentication())();

        // Set the transaction's refId
        $refId = 'ref' . time();

        $apiSubscription = new AnetAPI\ARBSubscriptionType();

        $apiSubscription->setAmount($newRenewalAmount->formatToDecimal());

        $request = new AnetAPI\ARBUpdateSubscriptionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscriptionId($gatewaySubscriptionId);
        $request->setSubscription($apiSubscription);

        $controller = new AnetController\ARBUpdateSubscriptionController($request);

        $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());

        if ( ! $apiResponse || $apiResponse->getMessages()->getResultCode() !== AuthorizeApiResultCode::OK) {
            $this->logErrorTransactionFailed($subscription, $apiResponse);
        }

        $this->logSuccessfulTransaction($subscription, $newRenewalAmount);

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
            sprintf('[Authorize.Net] Failed to update amount for subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gateway()->getId(),
                'Subscription' => $subscription->id,
                'Error Code' => $errorMessages[0]->getCode(),
                'Error Message' => $errorMessages[0]->getText(),
            ]
        );

        throw new PaymentGatewayException(
            __('[Authorize.Net] Failed to update subscription amount. Error: ' .
               $errorMessages[0]->getCode() . ' - ' . $errorMessages[0]->getText(),
                'give-recurring')
        );
    }

    /**
     * @since 2.2.0
     */
    private function logSuccessfulTransaction(Subscription $subscription, Money $newRenewalAmount)
    {
        PaymentGatewayLog::success(
            sprintf('[Authorize.Net] Amount successfully updated for subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Old Value' => $subscription->amount->formatToDecimal(),
                'New Value' => $newRenewalAmount->formatToDecimal(),
            ]
        );
    }
}
