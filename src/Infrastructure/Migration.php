<?php

namespace GiveRecurring\Infrastructure;

/**
 * @since 1.12.7
 */
abstract class Migration extends \Give\Framework\Migrations\Contracts\Migration {
	/**
	 * @since 1.12.7
	 * @return string
	 */
	public static function source() {
		return GIVE_RECURRING_ADDON_NAME;
	}
}
