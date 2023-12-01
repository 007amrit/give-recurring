<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Repositories;

use Exception;
use Give\PaymentGateways\PayPalCommerce\PayPalClient;
use GiveRecurring\PaymentGateways\PayPalCommerce\HttpHeader;
use GiveRecurring\PaymentGateways\PayPalCommerce\Models\Product as ProductModel;
use GiveRecurring\PaymentGateways\PayPalCommerce\PayPalResponse;
use RuntimeException;
use WP_Error;

/**
 * Class Product
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Repositories
 *
 * @since 1.11.0
 */
class Product{
	/**
	 * Form meta key for product details.
	 *
	 * @since 1.11.0
	 * @var string
	 */
	const PRODUCT_FORM_META_KEY = 'paypal_commerce_product';

	/**
	 * Product id prefix.
	 *
	 * @since 1.11.0
	 * @var string
	 */
	const PRODUCT_ID_PREFIX = 'GIVE-';

	/**
	 * @var PayPalClient
	 *
	 * @since 1.11.0
	 */
	private $payPalClient;

	/**
	 * Product constructor.
	 *
	 * @param  PayPalClient  $payPalClient
	 *
	 * @since 1.11.0
	 */
	public function __construct( PayPalClient $payPalClient ){
		$this->payPalClient = $payPalClient;
	}

	/**
	 * Get PayPal Product.
	 *
	 * @since 1.11.0
	 *
	 * @param  int  $formId
	 *
	 * @return ProductModel|null
	 */
	public function get( $formId ){
		$product = give()->form_meta->get_meta( $formId, ProductModel::getProductDetailFormMetaKey(), true );

		if( ! $product ) {
			return null;
		}

		return new ProductModel( $product['id'] );
	}

	/**
	 * Create product.
	 *
	 * @see https://developer.paypal.com/docs/api/catalog-products/v1/#products_create
	 *
	 * @since 1.11.0
	 *
	 * @param int $formId
	 *
	 * @return array|WP_Error
	 * @throws Exception
	 */
	public function create( $formId ) {
		/* @var PayPalResponse $paypalResponse */
		$paypalResponse = give( PayPalResponse::class );
		$paypalResponse->setLogTitle( 'PayPal Commerce Product Api Failure' );

		try {
			$response = wp_remote_post(
				$this->payPalClient->getApiUrl( 'v1/catalogs/products' ),
				[
					'headers' => give( HttpHeader::class )->getHeaders(),
					'body' => wp_json_encode(
						[
							'id'          => self::PRODUCT_ID_PREFIX . $formId,
							'name'        => get_the_title( $formId ),
							'type'        => 'SERVICE',
							'category'    => 'CHARITY',
						]
					),
					'data_format' => 'body'
				]
			);

			if( ! $paypalResponse->setData( $response )->isSuccessful() ) {
				return [];
			}

			return $paypalResponse->getBodyData();
		} catch ( Exception $ex ){

			// Get product from PayPal if it is duplicate product.
			if( $this->isDuplicateProduct( $paypalResponse->getBodyData() ) ) {
				return $this->getProductFromPayPal( $formId );
			}

			throw $ex;
		}
	}

	/**
	 * Return whether or not produce exist n PayPal.
	 *
	 * @since 1.11.0
	 *
	 * @param  array  $paypalResponse
	 *
	 * @return bool
	 */
	private function isDuplicateProduct( $paypalResponse ){
		if( ! array_key_exists( 'details', $paypalResponse ) ){
			return false;
		}

		$status = current(
			array_filter(
				$paypalResponse['details'],
				static function ( $error ) {
					return 'DUPLICATE_RESOURCE_IDENTIFIER' === $error['issue'];
				}
			)
		);

		return (bool) $status;
	}

	/**
	 * Store product detail.
	 *
	 * @since 1.11.0
	 *
	 * @param int $formId
	 * @param string $metaKey
	 * @param array $product
	 *
	 * @return bool
	 */
	public function storeProductDetail( $formId, $metaKey, $product ){
		return give()->form_meta->update_meta( $formId, $metaKey, $product );
	}

	/**
	 * Get product details from PayPal.
	 *
	 * @since 1.11.0
	 *
	 * @param int $formId
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getProductFromPayPal( $formId ){
		try {
			$response = wp_remote_get(
				$this->payPalClient->getApiUrl( sprintf(
					'v1/catalogs/products/%1$s',
					self::PRODUCT_ID_PREFIX . $formId
				) ),
				[ 'headers' => give( HttpHeader::class )->getHeaders(), ]
			);

			if( is_wp_error( $response ) ) {
				give_record_gateway_error(
					'PayPal Commerce Fetch Product Request Failure',
					sprintf(
						'<pre>$1%s</pre>',
						print_r( $response, true )
					) );

				throw new RuntimeException( $response->get_error_message() );
			}

			if( ! ( $response = wp_remote_retrieve_body( $response ) ) ) {
				throw new RuntimeException( esc_html__( 'Sorry, We are failed to fetch PayPal product. Please try after some time', 'give-recurring' ) );
			}

			$response = json_decode( $response, true );

			if( array_key_exists( 'message', $response ) ) {
				give_record_gateway_error(
					'PayPal Commerce Fetch Product Request Failure',
					sprintf(
						'<pre>$1%s</pre>',
						print_r( $response, true )
					) );
				throw new RuntimeException( $response['message'] );
			}

			return $response;
		} catch ( Exception $ex ){
			throw $ex;
		}
	}
}
