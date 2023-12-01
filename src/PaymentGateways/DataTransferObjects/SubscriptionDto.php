<?php

namespace GiveRecurring\PaymentGateways\DataTransferObjects;

use Exception;
use Give\Framework\Exceptions\Primitives\InvalidArgumentException;
use Give\Framework\Support\ValueObjects\Money;
use Give_Subscription as LegacySubscription;

/**
 * Class SubscriptionDto
 * @package GiveRecurring\PaymentGateways\DataTransferObjects
 *
 * Use this data transfer object for frontend subscription request.
 * This DTO does not provide access ot subscription id.
 *
 * @since 1.12.6
 *
 * @property string $formId
 * @property string $priceId
 * @property Money $recurringDonationAmount
 * @property string $period
 * @property string $frequency
 * @property string $currencyCode
 */
class SubscriptionDto
{
    /**
     * @since 1.12.6
     */
    public static function fromArray(array $array): SubscriptionDto
    {
        $self = new static();

        try {
            $self->formId = $array['formId'];
            $self->priceId = $array['priceId'];
            $self->recurringDonationAmount = $array['recurringDonationAmount'];
            $self->period = self::get_interval($array['period'], $array['frequency']);
            $self->frequency = self::get_interval_count($array['period'], $array['frequency']);
            $self->currencyCode = $array['currencyCode'];
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                sprintf(
                    'Add required argument to array to create %s object',
                    __CLASS__
                )
            );
        }

        return $self;
    }

    /**
     * @since 1.12.6
     */
    public static function fromGiveSubscriptionObject(
        LegacySubscription $legacySubscription,
        array $overwriteWith = []
    ): SubscriptionDto {
        $currencyCode = give()->payment_meta->get_meta(
            $legacySubscription->parent_payment_id,
            '_give_payment_currency',
            true
        );

        $priceId = give()->payment_meta->get_meta(
            $legacySubscription->parent_payment_id,
            '_give_payment_price_id',
            true
        );

        $dataFromSubscription = wp_parse_args(
            $overwriteWith,
            [
                'formId' => $legacySubscription->form_id,
                'priceId' => $priceId,
                'recurringDonationAmount' => Money::fromDecimal($legacySubscription->recurring_amount, $currencyCode),
                'period' => $legacySubscription->period,
                'frequency' => $legacySubscription->frequency,
                'currencyCode' => $currencyCode
            ]
        );

        return self::fromArray($dataFromSubscription);
    }

    /**
     * @since 1.12.6
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
    }

    /**
     * @since 2.1.2Gets interval length and interval unit on Give subscription period.
     *
     *
     * @param int    $frequency
     *
     * @param string $period
     *
     * @return string
     */
    private static function get_interval($period, $frequency)
    {
        $interval = $period;

        if ($period === 'quarter') {
            $interval = 'month';
        }

        return $interval;
    }

    /**
     * @since 2.1.2 Gets interval length and interval unit based on Give subscription period.
     *
     *
     * @param int    $frequency
     *
     * @param string $period
     *
     * @return float|int
     */
    private static function get_interval_count($period, $frequency)
    {
        $interval_count = $frequency;

        if ($period === 'quarter') {
            $interval_count = 3 * $frequency;
        }

        return $interval_count;
    }
}
