<?php
/**
 * Uninstall script for WP LLM Connector.
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It removes all data created by the plugin.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user wants to preserve settings.
$settings = get_option( 'wp_llm_connector_settings', array() );
$preserve_settings = isset( $settings['preserve_settings_on_uninstall'] ) && $settings['preserve_settings_on_uninstall'];

if ( ! $preserve_settings ) {
	// Delete plugin options.
	delete_option( 'wp_llm_connector_settings' );
	delete_option( 'wp_llm_connector_activated' );
	delete_option( 'wp_llm_connector_db_version' );

	// Drop audit log table.
	global $wpdb;
	$table_name = $wpdb->prefix . 'llm_connector_audit_log';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

	// Clear any transients using prepared LIKE query.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_llm_connector_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_llm_connector_' ) . '%'
		)
	);

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'wp_llm_connector_cleanup_logs' );
}
