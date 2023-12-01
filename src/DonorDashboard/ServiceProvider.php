<?php

namespace GiveRecurring\DonorDashboard;

use Give\ServiceProviders\ServiceProvider as ServiceProviderInterface;
use Give\Helpers\Hooks;

use GiveRecurring\DonorDashboard\Tab as RecurringDonationsTab;

class ServiceProvider implements ServiceProviderInterface {

	/**
	 * @inheritDoc
	 */
	public function register() {
        // Do nothing
	}

	/**
	 * @inheritDoc
	 */
	public function boot() {
		// Register Tabs
		Hooks::addAction( 'init', RecurringDonationsTab::class, 'registerTab' );
	}
}
