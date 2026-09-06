<?php
/**
 * Uninstall plugin
 *
 * @package ArtsPay
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'theme_manager_version' );
delete_option( 'theme_manager_settings' );

// Delete any transients.
delete_transient( 'theme_manager_old_theme_data' );

// Clear any scheduled hooks.
wp_clear_scheduled_hook( 'theme_manager_cleanup' );
