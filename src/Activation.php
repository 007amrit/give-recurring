<?php
namespace GiveRecurring;

use GiveRecurring\Revenue\Repositories\Subscription;

/**
 * Class responsible for registering and handling add-on activation hooks.
 *
 * @package     GiveRecurring
 * @copyright   Copyright (c) 2020, GiveWP
 */
class Activation {
	/**
	 * Activate add-on action hook.
	 *
	 * @since 1.11.0
	 * @return void
	 */
	public static function activateAddon() {
		if ( defined( 'GIVE_FUNDS_ADDON_VERSION' ) ) {
			give( Subscription::class )->setAllToDefaultFundId();
		}
	}

	/**
	 * Deactivate add-on action hook.
	 *
	 * @since 1.11.0
	 * @return void
	 */
	public static function deactivateAddon() {}

	/**
	 * Uninstall add-on action hook.
	 *
	 * @since 1.11.0
	 * @return void
	 */
	public static function uninstallAddon() {}
}
