<?php

namespace GiveRecurring\Subscriptions;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider as GiveServiceProvider;

class ServiceProvider implements GiveServiceProvider {
    /**
     * @inheritDoc
     *
     * @since 1.12.6
     */
    public function register() {
        //
    }

    /**
     * @inheritDoc
     *
     * @since 1.12.6
     */
    public function boot() {
        Hooks::addAction(
                'give_register_updates',
                Migrations\UpdateRenewalsWithPaymentMode::class,  'register',
                10, 1
        );
    }
}
