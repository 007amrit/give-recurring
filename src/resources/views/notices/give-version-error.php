<?php defined( 'ABSPATH' ) or exit; ?>

<strong>
	<?php _e( 'Activation Error:', 'give-recurring' ); ?>
</strong>
<?php _e( 'You must have', 'give-recurring' ); ?> <a href="https://givewp.com" target="_blank">Give</a>
<?php _e( 'version', 'give-recurring' ); ?> <?php echo GIVE_VERSION; ?>+
<?php printf( esc_html__( 'for the %1$s add-on to activate', 'give-recurring' ), GIVE_RECURRING_ADDON_NAME ); ?>.

