<?php

namespace GiveRecurring\DonorDashboard\Repositories;

use Give_Subscriptions_DB;

class SubscriptionRepository {
    public function getByDonorId ( $id ) {
        return (new Give_Subscriptions_DB())->get_subscriptions([
            'customer_id' => $id
        ]);
    }
}