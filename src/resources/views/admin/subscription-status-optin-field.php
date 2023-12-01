<?php
/* @var Give_Subscription $subscription */

/**
 * Filter opt-in statues
 */
$optInStatues = apply_filters(
	"give_recurring_gateway_statues_for_optin_{$subscription->gateway}",
	[],
	$subscription->id
);

echo sprintf(
	'<div id="give-recurring-subscription-status-optin-wrap" class="give-hidden" data-optin-statues="%1$s" style="margin-top: 15px;">
		<p>
			<input type="checkbox" id="give-recurring-subscription-status-optin" name="give_subscription_status_gateway_optin" value="1" checked>
			<label for="give-recurring-subscription-status-optin"></label>
		</p>
	</div>',
	esc_attr( wp_json_encode( $optInStatues ) )
);
