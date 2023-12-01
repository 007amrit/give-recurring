<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce;

use RuntimeException;
use WP_Error;

/**
 * Class PayPalErrorResponse
 * @package GiveRecurring\PaymentGateways\PayPalCommerce
 *
 * @since 1.11.0
 */
class PayPalResponse{
	/**
	 * @since 1.11.0
	 * @var array|WP_Error
	 */
	private $response;

	/**
	 * @since 1.11.0
	 * @var array
	 */
	private $responseBody;

	/**
	 * @since 1.11.0
	 * @var array
	 */
	private $responseHttpStatus;

	/**
	 * @since 1.11.0
	 * @var array
	 */
	private $httpStatus = 201;

	/**
	 * @since 1.11.0
	 * @var array
	 */
	private $log = true;

	/**
	 * @since 1.11.0
	 * @var string
	 */
	private $logTitle;

	/**
	 * Set response data.
	 *
	 * @param  array  $response
	 *
	 * @return $this
	 * @since 1.11.0
	 *
	 */
	public function setData( $response ) {
		$this->response = $response;

		$temp                     = wp_remote_retrieve_body( $response );
		$this->responseBody       = $temp ? json_decode( $temp, true ) : $temp;
		$this->responseHttpStatus = wp_remote_retrieve_response_code( $response );

		return $this;
	}

	/**
	 * Set log title.
	 *
	 * @param  string  $title
	 *
	 * @return $this
	 * @since 1.11.0
	 *
	 */
	public function setLogTitle( $title ) {
		$this->logTitle = $title;

		return $this;
	}

	/**
	 * Set success http status value.
	 *
	 * @since 1.11.0
	 *
	 * @param int $httpCode
	 *
	 * @return $this
	 */
	public function setSuccessHttpStatus( $httpCode ){
		$this->httpStatus = $httpCode;

		return $this;
	}

	/**
	 * Set success http status value.
	 *
	 * @since 1.11.0
	 *
	 * @param bool $enable
	 *
	 * @return $this
	 */
	public function canLogError( $enable ){
		$this->log = $enable;

		return $this;
	}

	/**
	 * Return whether or not PayPal response successful.
	 *
	 * @since 1.11.0
	 *
	 * @return bool
	 */
	public function isSuccessful(){
		if( $this->responseHttpStatus === $this->httpStatus ) {
			return true;
		}

		$this->validateWPError();
		$this->validateErrorMessage();

		return false;
	}

	/**
	 * Validate response for WP_Error.
	 *
	 * @since 1.11.0
	 */
	private function validateWPError(){
		if( is_wp_error( $this->response ) ) {
			$this->logError(
				$this->logTitle,
				$this->response->get_error_message()
			);

			throw new RuntimeException( $this->response->get_error_message() );
		}
	}

	/**
	 * Validate error message in response.
	 *
	 * PayPal does not send error in specific format, so it is difficult to verify error message.
	 *
	 * @since 1.11.0
	 */
	private function validateErrorMessage() {
		$this->logError(
			'PayPal Commerce Subscription Api Failure',
			$this->responseBody
		);

		throw new RuntimeException( wp_json_encode( $this->responseBody ) );
	}

	/**
	 * Log errors.
	 *
	 * @since 1.11.0
	 *
	 * @param  string  $title
	 * @param  mixed  $data
	 */
	private function logError( $title, $data ) {
		if ( ! $this->log ) {
			return;
		}

		give_record_gateway_error( $title,
			sprintf(
				'<pre>%1$s</pre>',
				print_r( $data, true )
			)
		);
	}

	/**
	 * Get response body data.
	 *
	 * @since 1.11.0
	 *
	 * @param  array  $default
	 *
	 * @return array
	 */
	public function getBodyData( $default = [] ){
		return $this->responseBody ?: $default;
	}
}
