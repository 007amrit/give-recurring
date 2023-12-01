<?php

namespace GiveRecurring\Infrastructure;

/**
 * Class Log
 *
 * @package GiveRecurring\Infrastructure
 * @since 1.12.3
 */
class Log extends \Give\Log\Log {
	/**
	 * @inheritDoc
	 * @since 1.12.3
	 *
	 * @param  string  $type
	 * @param  array  $args
	 */
	public static function __callStatic( $type, $args ) {
		$args[1]['source'] = 'Recurring Donations';

		parent::__callStatic( $type, $args );
	}
}
