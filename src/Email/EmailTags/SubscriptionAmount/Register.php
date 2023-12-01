<?php
namespace GiveRecurring\Email\EmailTags\SubscriptionAmount;

use Give_Subscription;
use GiveRecurring\Email\EmailTags\EmailTag;

/**
 * Class Register
 * @package GiveRecurring\Email\EmailTags\SubscriptionAmount
 *
 * @since 1.11.5
 */
class Register extends EmailTag {
	/**
	 * @inheritDoc
	 */
	public function getId(){
		return 'subscription_amount';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(){
		return esc_html__( 'Latest recurring amount for subscription.', 'give-recurring' );
	}

	/**
	 * Get email tag description.
	 *
	 * @since 1.11.5
	 *
	 * @return string
	 */
	public function getContext(){
		return 'donation';
	}

	/**
	 * @param  array  $emailTagArgs
	 *
	 * @return mixed|string
	 */
	public function decode( $emailTagArgs ) {
		if( ! isset( $emailTagArgs['subscription_id'] ) ) {
			return 'n/a';
		}

		$subscription = new Give_Subscription( (int) $emailTagArgs['subscription_id'] );

		if( ! $subscription->id ) {
			return 'n/a';
		}

		$amount = $subscription->recurring_amount;
		$amount = give_format_amount( $amount, [ 'donation_id' => $subscription->parent_payment_id ] );
		$amount = give_currency_filter( $amount, [ 'currency_code' => give_get_payment_currency_code( $subscription->parent_payment_id ), 'decode_currency' => true ] );

		return $amount;
	}
}
