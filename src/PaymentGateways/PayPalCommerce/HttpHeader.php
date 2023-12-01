<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce;

use Give\PaymentGateways\PayPalCommerce\Models\MerchantDetail;

/**
 * Class HttpHeader
 * @package GiveRecurring\PaymentGateways\PayPalCommerce
 *
 * @since 1.11.0
 */
class HttpHeader{
	/**
	 * @var MerchantDetail
	 *
	 * @since 1.11.0
	 */
	private $merchantDetails;

	/**
	 * Product constructor.
	 *
	 * @param  MerchantDetail  $merchantDetails
	 *
	 * @since 1.11.0
	 */
	public function __construct( MerchantDetail $merchantDetails ){
		$this->merchantDetails = $merchantDetails;
	}

	/**
	 * Get headers for http query.
	 *
	 * @since 1.11.0
	 *
	 * @param  array  $args
	 *
	 * @return array
	 */
	public function getHeaders( $args = [] ){
		return wp_parse_args(
			[
				'Authorization' => sprintf(
					'Bearer %1$s',
					$this->merchantDetails->accessToken
				),
				'Content-Type' => 'application/json',
				'Prefer' => 'return=representation'
			],
			$args
		);
	}
}
