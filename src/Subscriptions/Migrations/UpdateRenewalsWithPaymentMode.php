<?php

namespace GiveRecurring\Subscriptions\Migrations;

use Give\Framework\Migrations\Contracts\Migration;
use Give\Log\Log;
use Give\ValueObjects\Money;
use Give_Payment;
use Give_Subscriptions_DB;
use Give_Updates;
use WP_Query;
use Exception;

/**
 * Update each renewal payment with its parent payment mode.
 * The payment mode is necessary for including renewals
 * as payments in the donation reports admin menu.
 *
 * @since 1.12.6
 */
class UpdateRenewalsWithPaymentMode extends Migration {

    const PerPage = 10;

    /**
     * Register background update.
     *
     * @param Give_Updates $give_updates
     *
     * @since 1.12.6
     */
    public function register( $give_updates ) {
        $give_updates->register(
            [
                'id'       => self::id(),
                'version'  => '2.11.0',
                'callback' => [ $this, 'run' ],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function run() {

        $give_updates = Give_Updates::get_instance();

        $subscription_db = new Give_Subscriptions_DB();
        $subscriptions   = $subscription_db->get_subscriptions(
            array(
                'number' => static::PerPage,
                'offset' => ( $give_updates->step - 1 ) * static::PerPage,
            )
        );

        if( ! count( $subscriptions ) ) {
            // Update Ran Successfully.
            give_set_upgrade_complete( self::id() );
            return;
        }

        $give_updates->set_percentage( $subscription_db->count(), $give_updates->step * static::PerPage );

        foreach( $subscriptions as $subscription ) {
            $paymentMode = get_post_meta( $subscription->parent_payment_id, '_give_payment_mode', true );
            foreach( $subscription->get_child_payments() as $renewal ) {
                add_post_meta( $renewal->ID, '_give_payment_mode', $paymentMode, true );
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function id() {
        return 'update-renewals-with-payment-mode';
    }

    /**
     * @inheritdoc
     */
    public static function timestamp() {
        return strtotime( '2021-07-09' );
    }
}
