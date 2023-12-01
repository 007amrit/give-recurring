<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce\Models;

use Give\PaymentGateways\PayPalCommerce\Models\MerchantDetail;
use GiveRecurring\PaymentGateways\PayPalCommerce\Repositories\Product as ProductRepository;
use InvalidArgumentException;

/**
 * Class Product
 * @package GiveRecurring\PaymentGateways\PayPalCommerce\Models
 * @see https://developer.paypal.com/docs/api/catalog-products/v1/#products-create-response
 *
 * @since 1.11.0
 */
class Product{
	/**
	 * @var string
	 * @since 1.11.0
	 */
	public $id;

	/**
	 * Return Product model fro give array.
	 *
	 * @sicne 1.11.0
	 *
	 * @param string $productId
	 */
	public function __construct( $productId ){
		$this->id = $productId;
	}

	/**
	 * Return form meta key for product details.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	public static function getProductDetailFormMetaKey(){
		return sprintf(
			'%1$s_%2$s',
			give(MerchantDetail::class)->merchantIdInPayPal,
			ProductRepository::PRODUCT_FORM_META_KEY
		);
	}
}
