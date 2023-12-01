<?php
namespace GiveRecurring\Donation;

use Give\Framework\Migrations\MigrationsRegister;
use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider as GiveWpServiceProvider;
use GiveRecurring\Donation\Migrations\RecoverDonorFirstAndLastNameAffectByRenewal;
use GiveRecurring\Donation\Migrations\StoreSubscriptionIdInSubscriptionDonations;

/**
 * @since 1.12.7
 */
class ServiceProvider implements GiveWpServiceProvider {

	/**
	 * @inheritDoc
	 */
	public function register() {
	}

	/**
	 * @inheritDoc
	 */
	public function boot() {
		Hooks::addAction( 'give_register_updates', RecoverDonorFirstAndLastNameAffectByRenewal::class, 'register' );

        give(MigrationsRegister::class)
            ->addMigration(StoreSubscriptionIdInSubscriptionDonations::class);
	}
}
