<?php

namespace GiveRecurring\Webhooks\Stripe\Listeners;

use Give\PaymentGateways\Gateways\Stripe\Webhooks\StripeEventListener;
use Give_Subscription as LegacySubscription;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\InvoiceLineItem;

/**
 * Class InvoicePaymentSucceeded
 * @package GiveRecurring\Webhooks\Stripe\Listeners
 *
 * @since 1.12.6
 */
class InvoicePaymentSucceeded extends StripeEventListener
{

    /**
     * Processes invoice.payment_succeeded event.
     *
     * @since 1.12.6
     *
     * @param Event $event Stripe Event received via webhooks.
     *
     * @return void
     */
    public function processEvent(Event $event)
    {
        /**
         * @since 2.4.0
         */
        do_action('give_recurring_stripe_processing_invoice_payment_succeeded', $event);

        /* @var Invoice $invoice */
        $invoice = $event->data->object;
        $legacySubscription = $this->getSubscription($event);

        // Exit if we did not find subscription for given webhook notification.
        if (!$legacySubscription->id) {
            return;
        }

        /**
         * This action hook will be used to extend processing the invoice payment succeeded event.
         *
         * @since 1.9.4
         */
        do_action('give_recurring_stripe_process_invoice_payment_succeeded', $event);

        $totalPayments = (int)$legacySubscription->get_total_payments();
        $billTimes = (int)$legacySubscription->bill_times;

        // We can create renewal If:
        //  1. Subscription is ongoing
        //  2. bill_times is less than total payments.
        if ($this->shouldCreateRenewal($billTimes, $totalPayments)) {
            // Look to see if we have set the transaction ID on the parent payment yet.
            if (!$legacySubscription->get_transaction_id()) {
                // This is the initial transaction payment aka first subscription payment.
                $legacySubscription->set_transaction_id($invoice->charge);

                if (!$this->isDonationCompleted($legacySubscription->parent_payment_id)) {
                    give_update_payment_status($legacySubscription->parent_payment_id);
                    give_insert_payment_note(
                        $legacySubscription->parent_payment_id,
                        esc_html__('Charge succeeded in Stripe.', 'give-recurring')
                    );
                }
            } else {
                $this->addRenewal($legacySubscription, $invoice);
            }
        } else {
            $this->completeSubscriptionAndCancelOnStripe(
                $legacySubscription,
                $totalPayments,
                $billTimes
            );
        }
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    protected function getFormId(Event $event)
    {
        return $this->getSubscription($event)->form_id;
    }

    /**
     * @since 1.15.0
     */
    private function shouldCreateRenewal(int $billTimes, int $totalPayments): bool
    {
        return 0 === $billTimes || $totalPayments < $billTimes;
    }

    /**
     * @since 1.15.0
     */
    private function isDonationCompleted(int $legacySubscriptionParentDonationId): bool
    {
        $paymentStatus = give_get_payment_status($legacySubscriptionParentDonationId);

        return $paymentStatus === 'publish';
    }

    /**
     * @since 1.15.0
     *
     * @param object $invoice
     */
    private function addRenewal(LegacySubscription $legacySubscription, $invoice)
    {
        $donationId = give_get_purchase_id_by_transaction_id($invoice->charge);

        // Check if donation id empty that means renewal donation not made so please create it.
        // We have a renewal.
        if (empty($donationId)) {
            $args = [
                'amount' => give_stripe_cents_to_dollars($invoice->total),
                'transaction_id' => $invoice->charge,
                'post_date' => date_i18n('Y-m-d H:i:s', $invoice->created),
            ];

            $legacySubscription->add_payment($args);
            $legacySubscription->renew();
        }

        $this->completeSubscriptionAndCancelOnStripe(
            $legacySubscription,
            $legacySubscription->get_total_payments(),
            $legacySubscription->bill_times
        );
    }

    /**
     * @since 2.1.2 Fix handling of `give_recurring_get_subscription_by` value, which returns boolean|Give_Subscription
     * @since 2.0.0
     */
    private function getSubscription(Event $event): LegacySubscription
    {
        /* @var Invoice $invoice */
        $invoice = $event->data->object;

        $legacySubscription = new LegacySubscription(
            $event->data->object->subscription,
            true
        );

        if ($legacySubscription->id) {
            return $legacySubscription;
        }

        if ($this->hasDonationIdInInvoiceMetadata($invoice)) {
            /* @var InvoiceLineItem $invoiceLineItem */
            $invoiceLineItem = $invoice->lines->data[0];
            $donationMetaData = $invoiceLineItem->metadata->toArray();
            $donationId = ! empty($donationMetaData['Donation Post ID']) ? $donationMetaData['Donation Post ID'] : 0;
            $legacySubscription = give_recurring_get_subscription_by('payment', $donationId);

            if ($legacySubscription) {
                return $legacySubscription;
            }
        }

        return new LegacySubscription();
    }

    /**
     * @since 1.15.0
     */
    private function completeSubscriptionAndCancelOnStripe(
        LegacySubscription $legacySubscription,
        int $totalPayments,
        int $billTimes
    ) {
        // If the billing cycle is completed for a subscription then we have to complete the subscription to prevent further payments on Stripe.
        // This function completes the subscription in the GiveWP database and cancels it on Stripe.
        give_recurring_stripe_is_subscription_completed(
            $legacySubscription,
            $totalPayments,
            $billTimes
        );
    }

    /**
     * @since 1.15.0
     */
    private function hasDonationIdInInvoiceMetadata(Invoice $invoice): bool
    {
        if (!$invoice->lines->data) {
            return false;
        }

        /* @var InvoiceLineItem $invoiceLineItem */
        $invoiceLineItem = $invoice->lines->data[0];
        $metadata = $invoiceLineItem->metadata->toArray();

        return array_key_exists('Donation Post ID', $metadata) &&
            'subscription' === $invoiceLineItem->type;
    }
}
