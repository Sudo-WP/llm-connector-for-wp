<?php
namespace WP_LLM_Connector\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_LLM_Connector\Security\Security_Manager;
use WP_LLM_Connector\API\API_Handler;
use WP_LLM_Connector\Admin\Admin_Interface;
use WP_LLM_Connector\Providers\Provider_Registry;
use WP_LLM_Connector\Abilities\Abilities_Manager;

class Plugin {
	private static $instance = null;
	private $api_handler;
	private $security;
	private $admin;
	private $provider_registry;
	private $abilities_manager;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Singleton.
	}

	public function init() {
		// Initialize security first — single instance shared across components.
		$this->security = new Security_Manager();

		// Initialize provider registry.
		$this->provider_registry = new Provider_Registry( $this->security );
		$this->provider_registry->init();

		// Inject security into the API handler.
		$this->api_handler = new API_Handler( $this->security );

		// Initialize admin interface only in admin context.
		if ( is_admin() ) {
			$this->admin = new Admin_Interface();
		}

		// Register REST routes.
		add_action( 'rest_api_init', array( $this->api_handler, 'register_routes' ) );

		// Register WP AI Client providers (WP 7.0+).
		add_action( 'init', array( $this->provider_registry, 'register_wp_ai_client_providers' ) );

		// Initialize abilities manager (WP 6.9+).
		$this->abilities_manager = new Abilities_Manager( $this->security, $this->provider_registry );

		// Schedule log cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'wp_llm_connector_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_llm_connector_cleanup_logs' );
		}
		add_action( 'wp_llm_connector_cleanup_logs', array( $this, 'cleanup_old_logs' ) );
	}

	/**
	 * Delete audit log entries older than 90 days.
	 */
	public function cleanup_old_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'llm_connector_audit_log';

		// Validate table name.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
			)
		);
	}

	public function get_api_handler() {
		return $this->api_handler;
	}

	public function get_security_manager() {
		return $this->security;
	}

	public function get_provider_registry() {
		return $this->provider_registry;
	}

	public function get_abilities_manager() {
		return $this->abilities_manager;
	}
}
