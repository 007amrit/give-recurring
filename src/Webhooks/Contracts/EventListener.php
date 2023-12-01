<?php

namespace GiveRecurring\Webhooks\Contracts;

/**
 * Interface EventListener
 * @package GiveRecurring\Webhooks
 *
 * @since 1.12.6
 */
interface EventListener {
	/**
	 * @param mixed $event
	 *
	 * @since 1.12.6
	 * @return void
	 */
	public function processEvent( $event );
}