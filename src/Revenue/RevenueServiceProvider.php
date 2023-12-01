<?php

namespace GiveRecurring\Revenue;

use Give\Framework\Migrations\MigrationsRegister;
use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider;
use GiveRecurring\Activation;
use GiveRecurring\Revenue\Admin\SubscriptionEditPage;
use GiveRecurring\Revenue\Migrations\AddDefaultFundIdToSubscriptionMetadata;

/**
 * Class RevenueServiceProvider
 * @package GiveRecurring\Revenue
 *
 * @since 1.11.0
 */
class RevenueServiceProvider implements ServiceProvider {
	/**
	 * @inheritdoc
	 */
	public function register() {
		give()->singleton( Activation::class );
	}

	/**
	 * @inheritdoc
	 */
	public function boot() {
		Hooks::addAction( 'give_recurring_record_payment', DonationHandler::class, 'handle', 999 );

		// Fund addon related functionality
		if ( defined( 'GIVE_FUNDS_ADDON_VERSION' ) ) {
			// Register migration
			give( MigrationsRegister::class )->addMigration( AddDefaultFundIdToSubscriptionMetadata::class );

			// Fund subscription details
			if ( is_admin() && current_user_can( 'edit_give_payments' ) ) {
				Hooks::addAction( 'give_recurring_add_subscription_detail', SubscriptionEditPage::class, 'handle' );
				Hooks::addAction( 'give_recurring_update_subscription', SubscriptionEditPage::class, 'updateFundId' );
			}
		}
	}
}
