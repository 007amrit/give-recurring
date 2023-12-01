<?php

namespace GiveRecurring\DonorDashboard;

use Give\DonorDashboards\Tabs\Contracts\Tab as TabAbstract;
use GiveRecurring\DonorDashboard\Routes\SubscriptionsRoute as SubscriptionsRoute;
use GiveRecurring\DonorDashboard\Routes\CancelSubscriptionRoute as CancelSubscriptionRoute;
use GiveRecurring\DonorDashboard\Routes\UpdateSubscriptionRoute as UpdateSubscriptionRoute;

class Tab extends TabAbstract {

	public static function id() {
		return 'recurring-donations';
	}

	public function routes() {
		return [
			SubscriptionsRoute::class,
			CancelSubscriptionRoute::class,
			UpdateSubscriptionRoute::class,
		];
	}
}
