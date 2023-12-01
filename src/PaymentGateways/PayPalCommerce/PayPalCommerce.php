<?php
namespace GiveRecurring\PaymentGateways\PayPalCommerce;

use Give_Recurring_Gateway;
use Give\PaymentGateways\PayPalCommerce\PayPalCommerce as PayPalCommerceGateway;
use Give\PaymentGateways\PayPalCommerce\PayPalClient;
use Give_Subscription;

/**
 * Class PayPalCommerce
 * @package GiveRecurring\PaymentGateways\PayPalCommerce
 *
 * @since 1.11.0
 */
class PayPalCommerce extends Give_Recurring_Gateway {
	/**
	 * @since 1.11.0
	 * @var string
	 */
	private $subscriptionApproveRedirectURL;

	/**
	 * @inheritDoc
	 */
	public function init() {
		$this->id = PayPalCommerceGateway::GATEWAY_ID;
		$this->offsite = true;
	}

	/**
	 * @inheritDoc
	 */
	public function create_payment_profiles(){
		give(SubscriptionProcessor::class)->handle( $this );
	}

	/**
	 * @inheritDoc
	 */
	public function complete_signup() {
		if( $this->subscriptionApproveRedirectURL ) {
			wp_redirect( $this->subscriptionApproveRedirectURL );
			exit();
		}

		parent::complete_signup();
	}

	/**
	 * Determines if the subscription can be cancelled.
	 *
	 * @access      public
	 * @since       1.11.0
	 *
	 * @param bool $ret
	 * @param Give_Subscription $subscription
	 *
	 * @return bool
	 */
	public function can_cancel( $ret, $subscription ) {
		// Check gateway
		if ( $subscription->gateway === $this->id && 'active' === $subscription->status ) {
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Set subscription redirect url.
	 *
	 * @since 1.11.0
	 *
	 * @param $url
	 */
	public function setSubscriptionRedirectURL( $url ){
		$this->subscriptionApproveRedirectURL = $url;
	}

	/**
	 * @inheritDoc
	 */
	public function link_profile_id( $profile_id, $subscription ) {
		return sprintf(
			'<a href="%1$sbilling/subscriptions/%2$s" target="_blank">%2$s</a>',
			give( PayPalClient::class )->getHomePageUrl(),
			$profile_id
		);
	}

	/**
	 * Return list of subscription statuses for admin opt-in
	 *
	 * @since 1.11.0
	 *
	 * @param  array  $statues
	 *
	 * @return array|string[]
	 */
	public function getSubscriptionStatuesForOptIn( $statues ){
		return [
			'active'    => [
				'checkboxLabel' => esc_html__( 'Activate the subscription at PayPal?', 'give-recurring' )
			],
			'cancelled' => [
				'checkboxLabel' => esc_html__( 'Cancel the subscription at PayPal?', 'give-recurring' )
			],
			'suspended' => [
				'checkboxLabel' => esc_html__( 'Pause the subscription at PayPal?', 'give-recurring' )
			],
		];
	}
}
