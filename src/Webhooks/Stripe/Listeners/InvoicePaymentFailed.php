<?php


namespace GiveRecurring\Webhooks\Stripe\Listeners;

use Give\PaymentGateways\Gateways\Stripe\Webhooks\StripeEventListener;
use Give_Subscription;
use Stripe\Event;
use Stripe\Invoice;

/**
 * Class InvoicePaymentFailed
 * @package GiveRecurring\Webhooks\Stripe\Listeners
 *
 * @since 1.12.6
 */
class InvoicePaymentFailed extends StripeEventListener
{

    /**
     * Processes invoice.payment_failed event.
     *
     * @since 1.12.6
     *
     * @param Event $event Stripe Event received via webhooks.
     *
     */
    public function processEvent(Event $event)
    {
        /**
         * @since 2.4.0
         */
        do_action('give_recurring_stripe_processing_invoice_payment_failed', $event);

        /* @var Invoice $invoice */
        $invoice = $event->data->object;

        $subscription = give_recurring_get_subscription_by('profile', $invoice->subscription);

        if (!$subscription || !$subscription->id) {
            return;
        }

        $subscription->set_transaction_id($invoice->charge);

        /**
         * This action hook will be used to extend processing the invoice payment failed event.
         *
         * @since 1.9.4
         */
        do_action('give_recurring_stripe_process_invoice_payment_failed', $event);

        if (
            $invoice->attempted &&
            !$invoice->paid &&
            null !== $invoice->next_payment_attempt
        ) {
            $this->triggerFailedEmailNotificationEvent($subscription, $invoice);

            // Log the invoice object for debugging purpose.
            give_stripe_record_log(
                esc_html__('Subscription - Renewal Payment Failed', 'give-recurring'),
                print_r($invoice, true)
            );

            give_recurring_update_subscription_status($subscription->id, 'failing');
        }

        if (in_array(get_post_status($subscription->parent_payment_id), ['pending', 'processing'])) {
            give_update_payment_status($subscription->parent_payment_id, 'failed');
        }
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    protected function getFormId(Event $event)
    {
        /* @var Invoice $invoice */
        $invoice = $event->data->object;

        return (new Give_Subscription($invoice->subscription, true))->form_id;
    }

    /**
     * @since 1.12.6
     *
     * @param Give_Subscription $subscription
     * @param object $invoice
     */
    private function triggerFailedEmailNotificationEvent($subscription, $invoice)
    {
        do_action('give_donor-subscription-payment-failed_email_notification', $subscription, $invoice);
    }
}
