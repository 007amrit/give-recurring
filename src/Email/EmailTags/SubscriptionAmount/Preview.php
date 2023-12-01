<?php
namespace GiveRecurring\Email\EmailTags\SubscriptionAmount;

use Give_Subscription;
use Give_Subscriptions_DB;

/**
 * Class Preview
 * @package GiveRecurring\Email\EmailTags\SubscriptionAmount
 *
 * @since 1.11.5
 */
class Preview {
	private $shortcode;

	/**
	 * Preview constructor.
	 *
	 * @since 1.11.5
	 *
	 * @param  Register  $shortcode
	 */
	public function __construct( Register $shortcode ) {
		$this->shortcode = $shortcode;
	}

	/**
	 * Return recent subscription amount if donation is recurring payment.
	 *
	 * @since 1.11.5
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	public function decode( $message ) {
		if( false === strpos( $message, $this->shortcode->getCode() ) ) {
			return $message;
		}

		if( ! isset( $_GET['preview_id'] ) ) {
			return $this->replaceShortcodeWithPlaceholder( $message );
		}

		$donationId = absint( $_GET['preview_id'] );
		$subscription_id = ( new Give_Subscriptions_DB() )->get_column_by( 'id', 'parent_payment_id', $donationId );
		$subscription = new Give_Subscription( (int) $subscription_id );

		if( ! $subscription->id ) {
			return $this->replaceShortcodeWithPlaceholder( $message );
		}

		$amount = $subscription->recurring_amount;
		$amount = give_format_amount( $amount, [ 'donation_id' => $subscription->parent_payment_id ] );
		$amount = give_currency_filter( $amount, [ 'currency_code' => give_get_payment_currency_code( $subscription->parent_payment_id ), 'decode_currency' => true ] );

		return str_replace( $this->shortcode->getCode(), $amount , $message );
	}

	/**
	 * Return message with shortcode placeholder.
	 *
	 * @since 1.11.5
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	private function replaceShortcodeWithPlaceholder( $message ){
		$formattingArgs = [ 'currency_code' => give_get_currency() ];
		$amount = give_currency_filter( give_format_amount( '10.50', $formattingArgs), $formattingArgs );

		return str_replace( $this->shortcode->getCode(), $amount , $message );
	}
}
