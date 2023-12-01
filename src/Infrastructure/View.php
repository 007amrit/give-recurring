<?php
namespace GiveRecurring\Infrastructure;

use InvalidArgumentException;

/**
 * Helper class responsible for loading add-on views.
 *
 * @package     GiveRecurring\Infrastructure
 * @copyright   Copyright (c) 2020, GiveWP
 */
class View {

	/**
	 * Load template.
	 *
	 * @param string $view
	 * @param array $vars
	 * @param bool $echo
	 *
	 * @throws InvalidArgumentException if template file not exist
	 *
	 * @since 1.11.0
	 * @return string|void
	 */
	public static function load( $view, $vars = [], $echo = false ) {
		$template = GIVE_RECURRING_PLUGIN_DIR . 'src/resources/views/' . $view . '.php';

		if ( ! file_exists( $template ) ) {
			throw new InvalidArgumentException( "View template file {$template} not exist" );
		}

		ob_start();
		// phpcs:ignore
		extract( $vars );
		include $template;
		$content = ob_get_clean();

		if ( ! $echo ) {
			return $content;
		}

		echo $content;
	}

	/**
	 * Render template.
	 *
	 * @param string $view
	 * @param array $vars
	 *
	 * @since 1.11.0
	 */
	public static function render( $view, $vars = [] ) {
		static::load( $view, $vars, true );
	}
}
