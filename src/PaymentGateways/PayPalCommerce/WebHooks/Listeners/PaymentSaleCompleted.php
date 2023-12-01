<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

use Give\Repositories\PaymentsRepository;

/**
 * Class PaymentSaleCompleted
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class PaymentSaleCompleted extends PaymentSale {
	const WEBHOOK_ID = 'PAYMENT.SALE.COMPLETED';

	/**
	 * @inheritDoc
     *
     * @since 2.4.0 Fix PHP notices and access payment creation date from `create_time` instead of `created_date`.
	 */
	public function handle( $event ) {
		// Look to see if we have set the transaction ID on the parent payment yet.
		if ( ! $this->subscription->get_transaction_id() ) {
			$this->subscription->set_transaction_id( $event->resource->id );

			give_insert_payment_note( $this->subscription->parent_payment_id, __( 'Charge completed in PayPal', 'give-recurring' ) );
			give_update_payment_status( $this->subscription->parent_payment_id, 'publish' );

			return;
		}

		// Check if donation id empty that means renewal donation not made so please create it.
		if ( ! $this->donation ) {

			$args = array(
				'amount'         => $event->resource->amount->total,
				'transaction_id' => $event->resource->id,
				'post_date'      => iso8601_to_datetime( $event->resource->create_time, 'gmt' ),
			);

			// We have a renewal.
			$this->subscription->add_payment( $args );
			$this->subscription->renew();

			give_insert_payment_note(
				give( PaymentsRepository::class )->getDonationByPayment( $event->resource->id )->ID,

				esc_html__( 'Charge completed in PayPal', 'give-recurring' )
			);
		}
	}
}
