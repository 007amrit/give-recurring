<?php

namespace GiveRecurring\LegacySubscription;

use Give\Helpers\Call;

/**
 * @since 2.0.0
 */
class ServiceProvider implements \Give\ServiceProviders\ServiceProvider
{

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    public function register()
    {
    }

    /**
     * @since 2.0.0
     * @inerhitDoc
     */
    public function boot()
    {
        add_action('give_init', function () {
            Call::invoke(LegacySubscriptionAdapter::class);
        }, 15);
    }
}
