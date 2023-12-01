<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners;

/**
 * Class PaymentSaleRefunded
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\WebHooks\Listeners
 *
 * @since 1.11.0
 */
class PaymentSaleRefunded extends PaymentSale {
	const WEBHOOK_ID = 'PAYMENT.SALE.REFUNDED';

	/**
	 * @inheritDoc
	 */
	public function handle( $event ) {
		if ( $this->donation->ID ) {
			give_insert_payment_note( $this->donation->ID, esc_html__( 'Charge refunded in PayPal', 'give-recurring' ) );
			$this->donation->update_status( 'refunded' );
		}
	}
}
