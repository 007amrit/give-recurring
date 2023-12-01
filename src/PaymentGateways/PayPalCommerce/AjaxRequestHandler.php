<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce;

use Exception;
use Give_Recurring_Gateway;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Plan as PlanModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Product as ProductModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Plan;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Product;

/**
 * Class AjaxRequestHandler
 * @package GiveRecurring\PaymentGateways\PayPalCommerce
 *
 * @since 1.11.0
 */
class AjaxRequestHandler {
	/**
	 * @var ProductModel
	 * @since 1.11.0
	 */
	private $product;

	/**
	 * Create plan id.
	 *
	 * @since 1.11.0
	 */
	public function createPlanId(){
		$this->validateRequest();

		$formId = (int) $_POST['give-form-id'];

		try{
			$this->product = $this->getProduct( $formId );
			$plan          = $this->getPlan( $formId );

			wp_send_json_success([
				'id' => $plan->id
			]);

		}catch ( Exception $ex ){
			wp_send_json_error( [
				'error' => null // On frontend, we will show general error message to donor, because few times PayPal error belongs to website admin instead of donor.
			] );
		}
	}

	/**
	 * Get Product.
	 *
	 * @param int $formId
	 *
	 * @return ProductModel
	 * @throws Exception
	 * @since 1.11.0
	 *
	 */
	private function getProduct( $formId ){
		/* @var Product $productRepository */
		$productRepository = give( Product::class );

		$product = $productRepository->get( $formId );

		if( ! $product ) {
			try {
				$paypalProduct = $productRepository->create( $formId );

				$productRepository->storeProductDetail( $formId, ProductModel::getProductDetailFormMetaKey(), $paypalProduct );

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
	 * @param int $formId
	 *
	 * @return PlanModel
	 * @throws Exception
	 * @since 1.11.0
	 */
	private function getPlan( $formId ){
		/* @var Plan $planRepository */
		$planRepository = give( Plan::class );

		$recurringData = Give_Recurring()->modify_donation_data( [ 'post_data' => give_clean( $_POST ) ], array() );
		$frequency = ! empty( $recurringData['frequency'] ) ? (int) $recurringData['frequency'] : 1;
		$times = ! empty( $recurringData['times'] ) ? (int) $recurringData['times'] : 0;
		$amount = isset( $recurringData['post_data']['give-amount'] ) ? (float) apply_filters( 'give_donation_total', give_maybe_sanitize_amount( $recurringData['post_data']['give-amount'], [ 'currency' => give_get_currency( $formId ) ] ) ) : '0.00';

		$planModel = PlanModel::formArray( [
			'formId'    => $formId,
			'productId' => $this->product->id,
			'amount'    => $amount,
			'period'    => Give_Recurring_Gateway::get_interval( $recurringData['period'], $frequency ),
			'priceId'   => ! empty( $_POST['give-price-id'] ) ? $_POST['give-price-id'] : '',
			'frequency' => Give_Recurring_Gateway::get_interval_count( $recurringData['period'], $frequency ),
			'times'     => give_recurring_calculate_times( $times, $frequency ),
			'currency'  => give_get_currency( $formId ),
		] );

		$plan = $planRepository->get( $planModel );

		if( $plan ) {
			return $plan;
		}

		try{
			$plan = $planRepository->create( $planModel, $this->product );
			$metaKey = $planModel->getPlanDetailFormMetaKey();
			$planRepository->storePlanDetail( $formId, $metaKey, $plan );
			$planModel->updateWithPayPalResponse( $plan );

			return $planModel;
		}catch( Exception $ex ) {
			throw $ex;
		}
	}

	/**
	 * Validate ajax request
	 *
	 * @since 1.11.0
	 */
	private function validateRequest(){
		if( ! give_verify_donation_form_nonce( $_POST['give-form-hash'], $_POST['give-form-id'] ) ) {
			wp_die();
		}
	}
}
