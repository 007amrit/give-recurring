<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce;

use DateTime;
use Give\PaymentGateways\PayPalCommerce\PayPalCommerce as GivePayPalCommerce;
use Give_Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan as PlanModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Subscriber;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Product;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Plan;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Subscription;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Product as ProductModel;
use Exception;
use stdClass;


/**
 * Class SubscriptionProcessor
 * @package GiveRecurring\PaymentGateways\PayPalCommerce
 *
 * @since 1.11.0
 */
class SubscriptionProcessor{
	/**
	 * @var PayPalCommerce
	 * @since 1.11.0
	 */
	private $paypalCommerce;

	/**
	 * @var ProductModel
	 * @since 1.11.0
	 */
	private $product;

	/**
	 * @var PlanModel
	 * @since 1.11.0
	 */
	private $plan;

	/**
	 * @var array
	 * @since 1.11.0
	 */
	private $subscription;

	/**
	 * Donation form id.
	 *
	 * @since 1.11.0
	 *
	 * @var int
	 */
	private $formId;

	/**
	 * Handle donation subscription requests.
	 *
	 * @param PayPalCommerce $paypalCommerce
	 *
	 * @throws Exception
	 * @since 1.11.0
	 */
	public function handle( PayPalCommerce $paypalCommerce ){
		$this->paypalCommerce = $paypalCommerce;
		$this->formId = (int) $this->paypalCommerce->purchase_data['post_data']['give-form-id'];

		// PayPal subscription id will be submit to server by donation form if payment completed with smart buttons.
		$paypalSubscriptionId = isset( $this->paypalCommerce->purchase_data['post_data']['payPalSubscriptionId'] ) ?
			$this->paypalCommerce->purchase_data['post_data']['payPalSubscriptionId'] :
			'';

		try{
			if( ! $paypalSubscriptionId ) {
				$this->product = $this->getProduct();
				$this->plan = $this->getPlan();
				$this->subscription = $this->createSubscription();
			} else{
				/* @var Subscription $subscriptionRepository */
				$subscriptionRepository = give( Subscription::class );
				$this->subscription = $subscriptionRepository->getFromPayPal( $paypalSubscriptionId );
			}

			$this->paypalCommerce->subscriptions['profile_id'] = $this->subscription['id'];

			if ( 'APPROVAL_PENDING' === $this->subscription['status'] ) {
				$this->paypalCommerce->setSubscriptionRedirectURL( $this->getApproveSubscriptionURL() );
			}

		}catch ( Exception $ex ){
			give_set_error( 'pc-subscription-error', esc_html__( 'We\'re sorry, your donation failed to process. Please try again or contact site support.', 'give-recurring' ) );
		}
	}

	/**
	 * Get Product.
	 *
	 * @since 1.11.0
	 *
	 * @throws Exception
	 *
	 * @return ProductModel
	 */
	private function getProduct(){
		/* @var Product $productRepository */
		$productRepository = give( Product::class );

		$product = $productRepository->get( $this->formId );

		if( ! $product ) {
			try {
				$paypalProduct = $productRepository->create( $this->formId );

				$productRepository->storeProductDetail( $this->formId, ProductModel::getProductDetailFormMetaKey(), $paypalProduct );

				return new ProductModel( $paypalProduct['id'] );
			} catch ( Exception $ex ) {
				throw $ex;
			}
		}

		return $product;
	}

	/**
	 * Return plan.
	 *
	 * @return PlanModel
	 * @throws Exception
	 * @since 1.11.0
	 *
	 */
	private function getPlan(){
		/* @var Plan $planRepository */
		$planRepository = give( Plan::class );

		$amount = isset( $this->paypalCommerce->purchase_data['post_data']['give-amount'] ) ? (float) apply_filters( 'give_donation_total', give_maybe_sanitize_amount( $this->paypalCommerce->purchase_data['post_data']['give-amount'], [ 'currency' => give_get_currency( $this->formId ) ] ) ) : '0.00';

		$planModel = PlanModel::formArray( [
			'formId'    => $this->formId,
			'productId' => $this->product->id,
			'amount'    => $amount,
			'period'    => $this->paypalCommerce->subscriptions['period'],
			'priceId'   => ! empty( $this->paypalCommerce->purchase_data['post_data']['give-price-id'] ) ? $this->paypalCommerce->purchase_data['post_data']['give-price-id'] : '',
			'frequency' => $this->paypalCommerce->subscriptions['frequency'],
			'times'     => $this->paypalCommerce->subscriptions['bill_times'],
			'currency'  => give_get_currency( $this->formId ),
		] );

		$plan = $planRepository->get( $planModel );

		if( $plan ) {
			return $plan;
		}

		try{
			$plan = $planRepository->create( $planModel, $this->product );
			$metaKey = $planModel->getPlanDetailFormMetaKey();
			$planRepository->storePlanDetail( $this->formId, $metaKey, $plan );
			$planModel->updateWithPayPalResponse( $plan );

			return $planModel;
		}catch( Exception $ex ) {
			throw $ex;
		}
	}

	/**
	 * Create paypal subscription.
	 *
	 * @since 1.11.0
	 * @throws Exception
	 */
	private function createSubscription(){
		try {
			return give(Subscription::class)->create( $this->plan, $this->getSubscriber(), $this->getRedirectURLs() );
		} catch ( Exception $ex ) {
			throw $ex;
		}
	}


	/**
	 * Get redirect urls.
	 *
	 * @since 1.11.0
	 * @return array
	 */
	private function getRedirectURLs(){
		return [
			'return' => add_query_arg(
				[
					'payment-confirmation' => GivePayPalCommerce::GATEWAY_ID,
					'payment-id'           => $this->paypalCommerce->payment_id,
					'_wpnonce'             => wp_create_nonce( "give-successful-donation-{$this->paypalCommerce->payment_id}" ),
				],
				give_get_success_page_uri()
			),
			'cancel' => give_get_failed_transaction_uri( add_query_arg( [ 'payment-id' => $this->paypalCommerce->payment_id ] ) ),
		];
	}

	/**
	 * Return subscriber.
	 *
	 * @since 1.11.0
	 *
	 * @return Subscriber
	 */
	private function getSubscriber(){
		// Last name is not required, so it can be missing from post data.
		$lastName = $this->paypalCommerce->purchase_data['post_data']['give_last'] ?: '';

		$subscriber = new Subscriber(
			$this->paypalCommerce->purchase_data['post_data']['give_first'],
			$lastName,
			$this->paypalCommerce->purchase_data['post_data']['give_email']
		);

		if( $this->isProcessingPaymentWithCustomCardFields() ) {
			$subscriber->setCardDetails( $this->getCard() );
		}

		return $subscriber;
	}

	/**
	 * Get approve subscription url.
	 *
	 * @since 1.11.0
	 *
	 * @return mixed
	 */
	private function getApproveSubscriptionURL(){
		return current(
			array_filter(
				$this->subscription['links'],
				static function ( $link ) {
					return $link['rel'] === 'approve';
				}
			)
		)['href'];
	}

	/**
	 * Set subscription to failed if donor cancel subscription before approval.
	 *
	 * @since 1.11.0
	 */
	public function setSubscriptionFailed(){
		$subscriptionId = ! empty( $_GET['subscription_id'] ) ? give_clean( $_GET['subscription_id'] ) : 0;
		$payment_id = ! empty( $_GET['payment-id'] ) ? absint( $_GET['payment-id'] ) : 0;
		$nonce      = ! empty( $_GET['_wpnonce'] ) ? give_clean( $_GET['_wpnonce'] ) : false;

		// Bailout.
		if (
			! $payment_id ||
			! $subscriptionId ||
			! wp_verify_nonce( $nonce, "give-failed-donation-{$payment_id}" ) ||
			GivePayPalCommerce::GATEWAY_ID !== give_get_payment_gateway( $payment_id )
		) {
			return;
		}

		$subscription = Subscription::getSubscriptionByPayPalId( $subscriptionId );

		if( $subscription->id && 'failing' !== $subscription->status ) {
			give_record_gateway_error( 'Subscription Failing: Pass' );

			$subscription->update( [ 'status' => 'failing' ] );
		}
	}

	/**
	 * Handle subscription status change.
	 *
	 * @since 1.11.0
	 *
	 * @param  bool   $isStatusUpdated
	 * @param  int    $subscriptionId
	 * @param  array  $data
	 */
	public function handleSubscriptionStatusChange( $isStatusUpdated, $subscriptionId, $data ){
		// Edit PayPal subscription only if status changed.
		if( ! $isStatusUpdated || empty( $data['status'] ) ) {
			return;
		}

		$subscription = new Give_Subscription( $subscriptionId );

		// Return if subscription is not process by PayPal Commerce.
		if( GivePayPalCommerce::GATEWAY_ID !== give_get_payment_gateway( $subscription->parent_payment_id ) ) {
			return;
		}

		// Make sure that admin opt in to perform action on PayPal if editing subscription.
		if( $this->isEditingSubscription() && ! $this->isAdminOptInToPerformActionOnPayPal() ) {
			return;
		}

		try{
			if( 'active' === $subscription->status ) {
				give(Subscription::class)->updateStatus( $subscription->profile_id, 'activate' );
				give_insert_subscription_note( $subscription->id, esc_html__( 'Subscription activated in PayPal', 'give-recurring' ) );
			} elseif( 'cancelled' === $subscription->status ) {
				give(Subscription::class)->updateStatus( $subscription->profile_id, 'cancel' );
				give_insert_subscription_note( $subscription->id, esc_html__( 'Subscription cancelled in PayPal', 'give-recurring' ) );
			} elseif( 'suspended' === $subscription->status ) {
				give(Subscription::class)->updateStatus( $subscription->profile_id, 'suspend' );
				give_insert_subscription_note( $subscription->id, esc_html__( 'Subscription suspended in PayPal', 'give-recurring' ) );
			}
		}catch ( Exception $ex ) {
			$this->addSubscriptionStatusUpdateFailedNote( $subscription->id );
		}
	}

	/**
	 * Handle subscription deletion.
	 *
	 * @param  bool     $isDeleted
	 * @param  int      $subscriptionId
	 * @param  stdClass $subscriptionData
	 *
	 * @since 1.11.0
	 *
	 */
	public function handleSubscriptionDeletion( $isDeleted, $subscriptionId, $subscriptionData ) {
		if( $isDeleted && GivePayPalCommerce::GATEWAY_ID === give_get_payment_gateway( $subscriptionData->parent_payment_id ) ) {
			try{
				give(Subscription::class)->updateStatus( $subscriptionData->profile_id, 'cancel' );
				give_insert_payment_note( $subscriptionData->id, esc_html__( 'Subscription cancelled in PayPal', 'give-recurring' ) );
			}catch( Exception $ex ) {
				$this->addSubscriptionStatusUpdateFailedNote( $subscriptionData->id );
			}
		}
	}

	/**
	 * Return whether or not admin editing subscription.
	 *
	 * @since 1.11.0
	 * @return bool
	 */
	private function isEditingSubscription(){
		return isset( $_POST['give_update_subscription'] );
	}

	/**
	 * Return whether or not admin opt in to perform action on PayPal.
	 *
	 * @since 1.11.0
	 */
	public function isAdminOptInToPerformActionOnPayPal(){
		return isset( $_POST['give_subscription_status_gateway_optin'] ) && absint( $_POST['give_subscription_status_gateway_optin'] );
	}

	/**
	 * Add note to subscription about status update failure.
	 *
	 * @since 1.11.0
	 * @param int $subscriptionId
	 */
	private function addSubscriptionStatusUpdateFailedNote( $subscriptionId ){
		give_insert_payment_note( $subscriptionId, esc_html__( 'Subscription status should be active or suspended> Please review subscription in PayPal dashboard.', 'give-recurring' ) );
	}


	/**
	 * Get paypal payment method.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	private function isProcessingPaymentWithCustomCardFields(){
		return isset( $this->paypalCommerce->purchase_data['post_data']['card_number'] );
	}

	/**
	 * Get donor card.
	 *
	 * @since 1.11.0
	 *
	 * @return Subscriber\Card
	 */
	private function getCard(){
		$rawCardExpiry = preg_replace("/\s+/", "", $this->paypalCommerce->purchase_data['post_data']['card_expiry'] );
		$time = DateTime::createFromFormat( 5 < strlen( $rawCardExpiry ) ? 'm/Y' : 'm/y', $rawCardExpiry );
		$cardExpiry = date( 'Y-m', $time->getTimestamp() );
		$cardNumber = preg_replace("/\s+/", "", $this->paypalCommerce->purchase_data['post_data']['card_number'] );

		return new Subscriber\Card(
			$this->paypalCommerce->purchase_data['post_data']['card_name'],
			$cardNumber,
			$this->paypalCommerce->purchase_data['post_data']['card_cvc'],
			$cardExpiry
		);
	}
}
