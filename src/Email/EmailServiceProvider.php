<?php
namespace GiveRecurring\Email;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider;
use GiveRecurring\Email\EmailTags\SubscriptionAmount;

/**
 * Class EmailServiceProvider
 * @package GiveRecurring\Email
 *
 * @since 1.11.5
 */
class EmailServiceProvider implements ServiceProvider{
	/**
	 * @inheritdoc
	 */
	public function register() {}

	/**
	 * @inheritdoc
	 */
	public function boot() {
		Hooks::addFilter( 'give_add_email_tags', SubscriptionAmount\Register::class, 'register' );
		Hooks::addFilter( 'give_email_preview_template_tags', SubscriptionAmount\Preview::class, 'decode' );
	}
}
