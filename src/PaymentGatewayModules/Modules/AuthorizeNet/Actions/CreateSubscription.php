<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\AuthorizeNet\Actions;

use Give\Donations\Models\Donation;
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
 * For subscriptions with a monthly interval, whose payments begin on the 31st of a month,
 * payments for months with fewer than 31 days occur on the last day of the month.
 *
 * @see https://developer.authorize.net/api/reference/index.html#recurring-billing-create-a-subscription
 *
 * @since 2.2.0
 */
class CreateSubscription
{
    /**
     * @since 2.3.1 Add givewp_authorize_recurring_payment_description filter
     * @since      2.2.0
     *
     * @return false|string
     * @throws InvalidCredentialsException|PaymentGatewayException
     */
    public function __invoke(
        Donation $donation,
        Subscription $subscription,
        AuthorizeGatewayData $authorizeData
    ) {
        $merchantAuthentication = (new CreateMerchantAuthentication())();

        // Set the transaction's refId
        $refId = 'ref' . time();

        // Subscription Type Info
        $apiSubscription = new AnetAPI\ARBSubscriptionType();
        $apiSubscription->setName("GiveWP Subscription #" . $subscription->id);

        $authorizePeriod = $this->getAuthorizePeriod($subscription);

        $interval = new AnetAPI\PaymentScheduleType\IntervalAType();
        $interval->setLength($authorizePeriod['length']);
        $interval->setUnit($authorizePeriod['unit']);

        $paymentSchedule = new AnetAPI\PaymentScheduleType();
        $paymentSchedule->setInterval($interval);
        $paymentSchedule->setStartDate($subscription->createdAt);
        $totalOccurrences = $this->getAuthorizeTotalOccurrences($subscription);
        $paymentSchedule->setTotalOccurrences($totalOccurrences);

        $apiSubscription->setPaymentSchedule($paymentSchedule);
        $apiSubscription->setAmount($donation->amount->formatToDecimal());

        // Create the payment object for a payment nonce
        $opaqueData = new AnetAPI\OpaqueDataType();
        $opaqueData->setDataDescriptor($authorizeData->dataDescriptor);
        $opaqueData->setDataValue($authorizeData->dataValue);

        $payment = new AnetAPI\PaymentType();
        $payment->setOpaqueData($opaqueData);
        $apiSubscription->setPayment($payment);

        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($subscription->id);
        $description = apply_filters('givewp_authorize_recurring_payment_description', 'GiveWP Recurring Donation',
            $donation, $subscription);
        $order->setDescription($description);
        $apiSubscription->setOrder($order);

        $billTo = new AnetAPI\NameAndAddressType();
        $billTo->setFirstName($donation->donor->firstName);
        $billTo->setLastName($donation->donor->lastName);
        $billTo->setCompany($donation->company);
        $billTo->setAddress($donation->billingAddress->address1 . ' ' . $donation->billingAddress->address2);
        $billTo->setCity($donation->billingAddress->city);
        $billTo->setState($donation->billingAddress->state);
        $billTo->setZip($donation->billingAddress->zip);
        $billTo->setCountry($donation->billingAddress->country);
        $apiSubscription->setBillTo($billTo);

        // Set the customer's identifying information
        $customerData = new AnetAPI\CustomerType();
        $customerData->setType("individual"); //or business
        $customerData->setId($donation->donorId);
        $customerData->setEmail($donation->donor->email);
        $apiSubscription->setCustomer($customerData);

        $request = new AnetAPI\ARBCreateSubscriptionRequest();
        $request->setmerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscription($apiSubscription);
        $controller = new AnetController\ARBCreateSubscriptionController($request);

        /**
         * [First try] Prevent "E00114 Invalid OTS Token" error return.
         *
         * @see https://stackoverflow.com/a/52107364
         */
        sleep(5);

        $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());

        if ($apiResponse === null) {
            $this->logErrorNoResponse($subscription);
        }

        /**
         * [Second try] Prevent "E00114 Invalid OTS Token" error return.
         *
         * @see https://stackoverflow.com/a/52107364
         */
        if ($apiResponse->getMessages()->getResultCode() !== AuthorizeApiResultCode::OK) {
            sleep(2);
            $apiResponse = $controller->executeWithApiResponse(Give_Authorize::get_instance()->getApiEnv());
        }

        if ($apiResponse === null) {
            $this->logErrorNoResponse($subscription);
        }

        if ($apiResponse->getMessages()->getResultCode() !== AuthorizeApiResultCode::OK) {
            $this->logErrorTransactionFailed($subscription, $apiResponse);
        }

        $this->logSuccessfulTransaction($subscription);

        return $apiResponse->getSubscriptionId();
    }

    /**
     * length - The measurement of time, in association with unit, that is used to define the frequency of the
     * billing occurrences. For a unit of days, use an integer between 7 and 365, inclusive. For a unit of months,
     * use an integer between 1 and 12, inclusive. Numeric string, up to 3 digits.
     *
     * unit - The unit of time, in association with the length, between each billing occurrence. Either days or months.
     *
     * @since 2.2.0
     */
    private function getAuthorizePeriod(Subscription $subscription): array
    {
        $authorizePeriod = [];

        if ($subscription->period->isDay()) {
            $authorizePeriod['length'] = $subscription->frequency;
            $authorizePeriod['unit'] = 'days';
        }

        if ($subscription->period->isWeek()) {
            $authorizePeriod['length'] = 7 * $subscription->frequency;
            $authorizePeriod['unit'] = 'days';
        }

        if ($subscription->period->isMonth()) {
            $authorizePeriod['length'] = $subscription->frequency;
            $authorizePeriod['unit'] = 'months';
        }

        if ($subscription->period->isQuarter()) {
            $authorizePeriod['length'] = 3 * $subscription->frequency;
            $authorizePeriod['unit'] = 'months';
        }

        if ($subscription->period->isYear()) {
            $authorizePeriod['length'] = 12 * $subscription->frequency;
            $authorizePeriod['unit'] = 'months';
        }

        return $authorizePeriod;
    }

    /**
     * Number of payments for the subscription. If a trial period is specified, this value should
     * include the number of payments during the trial period. To create an ongoing subscription
     * without an end date, set totalOccurrences to "9999".
     *
     * @since 2.2.0
     */
    private function getAuthorizeTotalOccurrences(Subscription $subscription)
    {
        return $subscription->installments === 0 ? 9999 : $subscription->installments;
    }

    /**
     * @since 2.2.0
     * @throws PaymentGatewayException
     */
    private function logErrorNoResponse(Subscription $subscription)
    {
        PaymentGatewayLog::error(
            sprintf('[Authorize.Net] No response from the API. We\'re unable to contact the payment gateway to complete subscription %s.',
                $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Donation' => $subscription->initialDonation()->id,
                'Subscription' => $subscription->id,
            ]
        );

        throw new PaymentGatewayException(__('[Authorize.Net] No response from the API.', 'give-recurring'));
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
            sprintf('[Authorize.net] API transaction failed to create subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Donation' => $subscription->initialDonation()->id,
                'Subscription' => $subscription->id,
                'Error Code' => $errorMessages[0]->getCode(),
                'Error Message' => $errorMessages[0]->getText(),
            ]
        );

        throw new PaymentGatewayException(
            __('[Authorize.Net] Fail to create subscription. Error: ' .
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
            sprintf('[Authorize.net] API transaction successful to create subscription %s.', $subscription->id),
            [
                'Payment Gateway' => $subscription->gatewayId,
                'Donation' => $subscription->initialDonation()->id,
                'Subscription' => $subscription->id,
            ]
        );
    }
}
