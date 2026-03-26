<?php
namespace WP_LLM_Connector\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	public static function deactivate() {
		// Data cleanup happens only on uninstall.
		// Clear any scheduled events.
		wp_clear_scheduled_hook( 'wp_llm_connector_cleanup_logs' );
	}
}
