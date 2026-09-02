<?php
/**
 * Clean up plugin options on uninstall.
 *
 * Posts and their meta are left in place on purpose, so removing the plugin
 * never deletes content a site owner may still want.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'repopress_settings' );
delete_option( 'repopress_token' );
delete_option( 'repopress_log' );
delete_option( 'repopress_last_sync' );

$repopress_timestamp = wp_next_scheduled( 'repopress_scheduled_sync' );
if ( $repopress_timestamp ) {
	wp_unschedule_event( $repopress_timestamp, 'repopress_scheduled_sync' );
}
