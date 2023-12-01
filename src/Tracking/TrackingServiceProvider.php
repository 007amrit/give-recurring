<?php
namespace GiveRecurring\Tracking;

use Give\ServiceProviders\ServiceProvider;

/**
 * Class TrackingServiceProvider
 *
 * @package GiveRecurring\Tracking
 * @since 1.12.3
 */
class TrackingServiceProvider implements ServiceProvider {
	/**
	 * @inheritdoc
	 */
	public function register() {
	}

	/**
	 * @inheritdoc
	 */
	public function boot() {
		add_filter( 'give_telemetry_form_uses_addon_recurring', static function(  $result, $formId ){
			return give_is_form_recurring( $formId );
		}, 10, 2 );
	}
}
