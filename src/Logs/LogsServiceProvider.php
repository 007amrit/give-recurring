<?php
namespace GiveRecurring\Logs;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider;
use Give\Framework\Migrations\MigrationsRegister;
use GiveRecurring\Logs\Migrations\MigrateLogs;
use GiveRecurring\Logs\Migrations\MigrateEmailLogs;
use GiveRecurring\Logs\Migrations\DeleteLogs;
use GiveRecurring\Logs\Migrations\DeleteEmailLogs;

/**
 * Class LogsServiceProvider
 * @package GiveRecurring\Logs
 *
 * @since 1.12.3
 */
class LogsServiceProvider implements ServiceProvider{
	/**
	 * @inheritdoc
	 */
	public function register() {}

	/**
	 * @inheritdoc
	 */
	public function boot() {
		Hooks::addAction( 'give_register_updates', MigrateLogs::class, 'register' );
		Hooks::addAction( 'give_register_updates', MigrateEmailLogs::class, 'register' );

		// Check if Logs migration batch processing is completed
		if ( give_has_upgrade_completed( MigrateLogs::id() ) ) {
			give( MigrationsRegister::class )->addMigration( DeleteLogs::class );
		}

		// Check if Email Logs migration batch processing is completed
		if ( give_has_upgrade_completed( MigrateEmailLogs::id() ) ) {
			give( MigrationsRegister::class )->addMigration( DeleteEmailLogs::class );
		}
	}
}
