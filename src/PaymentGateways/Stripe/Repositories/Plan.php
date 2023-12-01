<?php

namespace GiveRecurring\PaymentGateways\Stripe\Repositories;

use Exception;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToCreateStripePlan;
use GiveRecurring\Infrastructure\Exceptions\PaymentGateways\Stripe\UnableToRetrieveStripePlan;
use Stripe\Plan as StripePlan;

/**
 * @since 1.10.3
 */
class Plan
{
    /**
     * @since 1.10.3
     * @since 1.12.6 throw exception
     *
     * @throws UnableToCreateStripePlan
     */
    public function create(array $args): StripePlan
    {
        try {
            return StripePlan::create($args);
        } catch (Exception $e) {
            throw new UnableToCreateStripePlan(
                sprintf(
                /* translators: %s Exception Message Body */
                    esc_html__(
                        'The Stripe Gateway returned an error while creating a subscription plan. Details: %s',
                        'give-recurring'
                    ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @since 1.10.3
     * @since 1.12.6 throw exception
     *
     * @throws UnableToRetrieveStripePlan
     */
    public function retrieve(string $id): StripePlan
    {
        try {
            return StripePlan::retrieve($id);
        } catch (Exception $e) {
            throw new UnableToRetrieveStripePlan(
                sprintf(
                /* translators: %s Exception Message Body */
                    esc_html__(
                        'The Stripe Gateway returned an error while retrieving the subscription plan. Details: %s',
                        'give-recurring'
                    ),
                    $e->getMessage()
                )
            );
        }
    }
}
