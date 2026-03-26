<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Core;

class Activator {

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0';

	public static function activate() {
		// Create default options (only if they don't exist).
		$default_options = array(
			'enabled'           => false,
			'read_only_mode'    => true,
			'allowed_endpoints' => array(
				'site_info',
				'plugin_list',
				'theme_list',
				'user_count',
				'post_stats',
				'system_status',
			),
			'api_keys'          => array(),
			'rate_limit'        => 60,
			'log_requests'      => true,
			'providers'         => array(
				'anthropic' => array(
					'enabled'       => false,
					'api_key'       => '',
					'default_model' => 'claude-sonnet-4-6',
				),
				'openai'    => array(
					'enabled'       => false,
					'api_key'       => '',
					'default_model' => 'gpt-4o',
				),
				'gemini'    => array(
					'enabled'       => false,
					'api_key'       => '',
					'default_model' => 'gemini-2.0-flash',
				),
			),
		);

		add_option( 'wp_llm_connector_settings', $default_options );

		// Create or upgrade the audit log table.
		self::create_or_upgrade_table();

		// Set activation timestamp.
		update_option( 'wp_llm_connector_activated', current_time( 'mysql' ) );

		// Schedule daily log cleanup.
		if ( ! wp_next_scheduled( 'wp_llm_connector_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_llm_connector_cleanup_logs' );
		}
	}

	/**
	 * Create or upgrade the audit log table with version tracking.
	 */
	public static function create_or_upgrade_table() {
		$installed_version = get_option( 'wp_llm_connector_db_version', '0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table_name      = $wpdb->prefix . 'llm_connector_audit_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			api_key_hash varchar(64) NOT NULL,
			endpoint varchar(255) NOT NULL,
			request_data text,
			response_code int(3),
			ip_address varchar(45),
			user_agent varchar(500),
			provider varchar(64) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY timestamp (timestamp),
			KEY api_key_hash (api_key_hash),
			KEY provider (provider)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wp_llm_connector_db_version', self::DB_VERSION );
	}
}
