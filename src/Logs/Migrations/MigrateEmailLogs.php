<?php
namespace GiveRecurring\Logs\Migrations;

use Give_Updates;
use Give\Log\LogFactory;
use Give\Log\ValueObjects\LogType;
use Give\Framework\Database\DB;
use Give\Framework\Migrations\Contracts\Migration;


/**
 * Class MigrateLogs
 * @package GiveRecurring\Email\Migrations
 *
 * Migrate existing email logs to the new logging db table
 *
 * @since 1.12.3
 */
class MigrateEmailLogs extends Migration {
	/**
	 * Register background update.
	 *
	 * @param Give_Updates $give_updates
	 */
	public function register( $give_updates ) {
		$give_updates->register(
			[
				'id'       => self::id(),
				'version'  => '1.11.5',
				'callback' => [ $this, 'run' ],
			]
		);
	}
	/**
	 * @inheritdoc
	 */
	public static function id() {
		return 'give-recurring-migrate-existing-email-logs';
	}

	/**
	 * @inheritdoc
	 */
	public static function title() {
		return 'Migrate existing email logs to give_log table';
	}

	/**
	 * @inheritdoc
	 */
	public static function source() {
		return GIVE_RECURRING_ADDON_NAME;
	}

	/**
	 * @inheritdoc
	 */
	public static function timestamp() {
		return strtotime( '2021-02-19' );
	}

	/**
	 * @inheritdoc
	 */
	public function run() {
		global $wpdb;

		$give_updates = Give_Updates::get_instance();

		$args = [
			'post_type'      => 'give_recur_email_log',
			'posts_per_page' => 100,
			'paged'          => $give_updates->step
		];

		$totalLogs = DB::get_var( "SELECT COUNT(id) FROM {$wpdb->posts} WHERE post_type='give_recur_email_log'" );

		$logs = get_posts( $args );

		if ( $logs ) {

			$give_updates->set_percentage(
				$totalLogs,
				$give_updates->step * 100
			);

			foreach ( $logs as $log ) {
				$context = [
					'Info'            => 'Migrated from existing email logs',
					'Customer ID'     => get_post_meta( $log->ID, '_give_recurring_email_log_customer_id', true ),
					'Subscription ID' => get_post_meta( $log->ID, '_give_recurring_email_log_subscription_id', true ),
					'Email Subject'   => get_post_meta( $log->ID, '_give_recurring_email_subject', true )
				];

				LogFactory::make(
					LogType::INFO,
					$log->post_title,
					'Recurring Donations',
					'Email Logs',
					$context,
					null,
					$log->post_date
				)->save();
			}
		} else {
			give_set_upgrade_complete( self::id() );
		}
	}
}
