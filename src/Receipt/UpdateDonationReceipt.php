<?php

namespace GiveRecurring\Receipt;

use Give\Receipt\UpdateReceipt;
use Give_Subscription;
use Give_Subscriptions_DB;

/**
 * Class UpdateDonationReceipt
 *
 * @package GiveRecurring\Receipt
 * @since 1.10.3
 */
class UpdateDonationReceipt extends UpdateReceipt {
	/**
	 * @var Give_Subscription
	 */
	private $subscription = null;

	/**
	 * Apple changes to receipt
	 *
	 * @since 1.10.3
	 */
	public function apply() {
		// Setup subscription.
		$this->setupSubscription( $this->receipt->donationId );

		// Exit if donation is not recurring donation.
		if ( ! $this->subscription || ! $this->subscription->id ) {
			return;
		}

		// Add section.
		$section = $this->receipt->addSection( [
			'id'    => 'Subscription',
			'label' => esc_html__( 'Subscription Details', 'give-recurring' ),
		] );

		$section->addLineItem( $this->getSubscriptionLineItem() );
		$section->addLineItem( $this->getStatusLineItem() );
		$section->addLineItem( $this->getRenewalDateLineItem() );
		$section->addLineItem( $this->getProgressLineItem() );
	}

	/**
	 * Get subscription from donation id.
	 *
	 * @param  int  $donationId
	 * @since 1.10.3
	 */
	private function setupSubscription( $donationId ) {
		// Get parent donation id if any.
		if ( $donationParent = get_post_field( 'post_parent', $donationId ) ) {
			$donationId = (int) $donationParent;
		}

		$subscriptionDB = new Give_Subscriptions_DB();
		$subscriptionId = $subscriptionDB->get_column_by( 'id', 'parent_payment_id', $donationId );

		if ( $subscriptionId ) {
			$this->subscription = new Give_Subscription( $subscriptionId );
		}
	}

	/**
	 * Get subscription line item.
	 *
	 * @return array
	 * @since 1.10.3
	 */
	private function getSubscriptionLineItem() {
		$value = sprintf(
			'%1$s%2$s',
			$this->getSubscriptionAndFrequency(),
			$this->getEditAmountLink()
		);

		return [
			'id'    => 'subscription',
			'label' => esc_html__( 'Subscription', 'give-recurring' ),
			'value' => $value,
		];
	}

	/**
	 * Get subscription amount vs frequency string.
	 *
	 * @return string
	 * @since 1.10.3
	 */
	private function getSubscriptionAndFrequency() {
		return sprintf(
			'%1$s / %2$s',
			give_currency_filter(
				give_format_amount(
					$this->subscription->recurring_amount,
					[ 'donation_id' => $this->subscription->parent_payment_id ]
				),
				[ 'currency_code' => give_get_payment_currency_code( $this->subscription->parent_payment_id ) ]
			),
			$this->getSubscriptionFrequency()
		);
	}

	/**
	 * Get edit amount link.
	 *
	 * @return string
	 * @since 1.10.3
	 */
	private function getEditAmountLink() {
		return $this->subscription->can_update_subscription() ?
			sprintf(
				'<br><strong><a href="%3$s" title="%2$s" target="_parent">%1$s</a></strong>',
				__( 'Edit Amount', 'give-recurring' ),
				__( 'Edit amount of subscription', 'give-recurring' ),
				esc_url( $this->subscription->get_edit_subscription_url() )
			) :
			'';
	}

	/**
	 * Get subscription frequesncy.
	 *
	 * @return mixed|string
	 * @since 1.10.3
	 */
	private function getSubscriptionFrequency() {
		return give_recurring_pretty_subscription_frequency(
			$this->subscription->period,
			$this->subscription->bill_times,
			false,
			$this->subscription->frequency ?: 1
		);
	}

	/**
	 * Get subscription status line item.
	 *
	 * @return array
	 * @since 1.10.3
	 */
	private function getStatusLineItem() {
		return [
			'id'    => 'status',
			'label' => esc_html__( 'Status', 'give-recurring' ),
			'value' => give_recurring_get_pretty_subscription_status( $this->subscription->status ),
		];
	}

	/**
	 * Get subscription renewal date line item.
	 *
	 * @return array
	 * @since 1.10.3
	 */
	private function getRenewalDateLineItem() {
		$value = ! empty( $this->subscription->expiration ) ?
			date_i18n( get_option( 'date_format' ), strtotime( $this->subscription->expiration ) ) :
			__( 'N/A', 'give-recurring' );;

		return [
			'id'    => 'renewalDate',
			'label' => esc_html__( 'Renewal Date', 'give-recurring' ),
			'value' => $value,
		];
	}

	/**
	 * Get subscription progress line item.
	 *
	 * @return array
	 * @since 1.10.3
	 */
	private function getProgressLineItem() {
		return [
			'id'    => 'progress',
			'label' => esc_html__( 'Progress', 'give-recurring' ),
			'value' => get_times_billed_text( $this->subscription ),
		];
	}
}
