<?php

declare(strict_types=1);

namespace GiveRecurring\Donation\Migrations;

use Give\Framework\Migrations\Contracts\Migration;

/**
 * Prior to this point in GiveWP, when a subscription donation is created, the subscription ID is not stored in that
 * donation's meta. This migration will go through all subscription donations and store the subscription ID in the meta.
 * In doing so, the subscription_id meta is now consistent across subscription AND renewal donations, giving a single,
 * predictable place to determine the subscription ID for a donation.
 *
 * @since 2.1.0
 */
class StoreSubscriptionIdInSubscriptionDonations extends Migration
{
    public static function id(): string
    {
        return 'store_subscription_id_in_subscription_donations';
    }

    public static function timestamp(): int
    {
        return strtotime('2022-09-05 10:22:00');
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        global $wpdb;

        $donationMetaTable = "{$wpdb->prefix}give_donationmeta";
        $subscriptionTable = "{$wpdb->prefix}give_subscriptions";

        $wpdb->query(
            "
                INSERT INTO $donationMetaTable (donation_id, meta_key, meta_value)
                SELECT
                    p.ID,
                    'subscription_id',
                    s.id
                FROM
                    $wpdb->posts AS p
                    LEFT JOIN $donationMetaTable AS dm1 ON dm1.donation_id = p.ID
                        AND dm1.meta_key = 'subscription_id'
                    LEFT JOIN $donationMetaTable AS dm2 ON dm2.donation_id = p.ID
                        AND dm2.meta_key = '_give_subscription_payment'
                    LEFT JOIN $subscriptionTable AS s ON s.parent_payment_id = p.ID
                WHERE
                    dm2.meta_value = '1'
                    AND dm1.meta_value IS NULL
            "
        );
    }
}
