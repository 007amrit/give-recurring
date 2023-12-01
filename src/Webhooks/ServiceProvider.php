<?php

namespace GiveRecurring\Webhooks;

use Give\Helpers\Hooks;
use GiveRecurring\Webhooks\Stripe\Listeners\CheckoutSessionCompleted;
use GiveRecurring\Webhooks\Stripe\Listeners\CustomerSubscriptionCreated as StripeCustomerSubscriptionCreated;
use GiveRecurring\Webhooks\Stripe\Listeners\CustomerSubscriptionDeleted;
use GiveRecurring\Webhooks\Stripe\Listeners\InvoicePaymentFailed;
use GiveRecurring\Webhooks\Stripe\Listeners\InvoicePaymentSucceeded;

/**
 * Class ServiceProvider
 * @package GiveRecurring\Webhooks
 *
 * @since   1.12.6
 */
class ServiceProvider implements \Give\ServiceProviders\ServiceProvider
{
    /**
     * @var string[]
     */
    private $stripeWebhookEventListeners = [
        'give_stripe_event_invoice.payment_succeeded' => InvoicePaymentSucceeded::class,
        'give_stripe_event_invoice.payment_failed' => InvoicePaymentFailed::class,
        'give_stripe_event_customer.subscription.deleted' => CustomerSubscriptionDeleted::class,
        'give_stripe_event_checkout.session.completed' => CheckoutSessionCompleted::class,
        'give_stripe_event_customer.subscription.created' => StripeCustomerSubscriptionCreated::class,
    ];

    /**
     * @inheritDoc
     */
    public function register()
    {
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        add_action('give_init', [$this, 'registerStripeWebhookEvents'], 99);
    }

    /**
	 * @since 1.12.6
	 */
	public function registerStripeWebhookEvents(){
		foreach ( $this->stripeWebhookEventListeners as $eventName => $eventListenerClassName ) {
			Hooks::addAction( $eventName, $eventListenerClassName, 'processEvent' );
		}
	}
}
