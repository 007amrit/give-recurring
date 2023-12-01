<?php
namespace GiveRecurring\Revenue\Repositories;

use Give_Subscriptions_DB;
use GiveFunds\Repositories\Funds;

/**
 * Class Subscription
 * @package GiveRecurring\Revenue\Repositories
 */
class Subscription {
	/**
	 * @param $subscriptionId
	 *
	 * @return int
	 */
	public function getAssociatedFundId( $subscriptionId ) {
		return (int) give_recurring()->subscription_meta->get_meta( $subscriptionId, 'fund_id', true );
	}

	/**
	 * Set all NULL fund ids to default fund id.
	 *
	 * @since 2.9.0
	 */
	public function setAllToDefaultFundId() {
		global $wpdb;

		$subscriptionMetaTableName = give_recurring()->subscription_meta->table_name;
		$subscriptionTableName     = ( new Give_Subscriptions_DB() )->table_name;
		/* @var Funds $fundRepository */
		$fundRepository = give( Funds::class );

		// @codingStandardsIgnoreStart
		$wpdb->query(
			$wpdb->prepare(
				"
				INSERT INTO {$subscriptionMetaTableName} (  subscription_id, meta_key, meta_value )
				SELECT id, %s, %d
				FROM {$subscriptionTableName} as s
				WHERE NOT EXISTS (
					SELECT *
					FROM {$subscriptionMetaTableName}
					WHERE meta_key=%s
					AND subscription_id=s.id
				)
				",
				'fund_id',
				$fundRepository->getDefaultFundId(),
				'fund_id'
			)
		);
		// @codingStandardsIgnoreEnd
	}
}
