<?php
namespace WP_LLM_Connector\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_LLM_Connector\Security\Security_Manager;

class API_Handler {
	private $namespace = 'wp-llm-connector/v1';
	private $security;

	/**
	 * Map of REST route slugs to their endpoint permission keys.
	 *
	 * @var array
	 */
	private $endpoint_map = array(
		'site-info'     => 'site_info',
		'plugins'       => 'plugin_list',
		'themes'        => 'theme_list',
		'system-status' => 'system_status',
		'user-count'    => 'user_count',
		'post-stats'    => 'post_stats',
	);

	/**
	 * @param Security_Manager $security Injected security manager instance.
	 */
	public function __construct( Security_Manager $security ) {
		$this->security = $security;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/site-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_site_info' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_plugins' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/themes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_themes' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/system-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_system_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/user-count',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_count' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/post-stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post_stats' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Check permissions for API access.
	 *
	 * Enforces: enabled flag, HTTPS, API key, endpoint allowlist, rate limit.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 * @return true|\WP_Error
	 */
	public function check_permissions( \WP_REST_Request $request ) {
		// Check if connector is enabled.
		if ( ! $this->security->is_enabled() ) {
			return new \WP_Error(
				'connector_disabled',
				__( 'The LLM Connector is currently disabled.', 'wp-llm-connector' ),
				array( 'status' => 403 )
			);
		}

		// Warn on non-HTTPS connections.
		if ( ! $this->security->is_secure_connection() ) {
			// Allow the request but add a header warning.
			add_filter(
				'rest_post_dispatch',
				function ( $response ) {
					$response->header( 'X-LLM-Security-Warning', 'Connection is not encrypted. Use HTTPS.' );
					return $response;
				}
			);
		}

		// Get API key from header.
		$api_key = $request->get_header( 'X-WP-LLM-API-Key' );

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'API key is required.', 'wp-llm-connector' ),
				array( 'status' => 401 )
			);
		}

		// Validate API key.
		$key_data = $this->security->validate_api_key( $api_key );
		if ( ! $key_data ) {
			$this->security->log_request( hash( 'sha256', $api_key ), 'auth_failed', array(), 401 );
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid API key.', 'wp-llm-connector' ),
				array( 'status' => 401 )
			);
		}

		// Check endpoint allowlist.
		$route        = $request->get_route();
		$endpoint_slug = $this->get_endpoint_slug( $route );

		if ( $endpoint_slug && ! $this->security->is_endpoint_allowed( $endpoint_slug ) ) {
			$api_key_hash = hash( 'sha256', $api_key );
			$this->security->log_request( $api_key_hash, $endpoint_slug . '_denied', array(), 403 );
			return new \WP_Error(
				'endpoint_not_allowed',
				__( 'This endpoint is not enabled.', 'wp-llm-connector' ),
				array( 'status' => 403 )
			);
		}

		// Check rate limit.
		$api_key_hash = hash( 'sha256', $api_key );
		if ( ! $this->security->check_rate_limit( $api_key_hash ) ) {
			$this->security->log_request( $api_key_hash, 'rate_limited', array(), 429 );
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded.', 'wp-llm-connector' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Extract the endpoint permission slug from a REST route.
	 *
	 * @param string $route Full route path (e.g. /wp-llm-connector/v1/site-info).
	 * @return string|false Permission slug or false if not mapped.
	 */
	private function get_endpoint_slug( $route ) {
		// Strip namespace prefix to get the route segment.
		$route_segment = str_replace( '/' . $this->namespace . '/', '', $route );
		$route_segment = trim( $route_segment, '/' );

		return $this->endpoint_map[ $route_segment ] ?? false;
	}

	/**
	 * Get site information.
	 */
	public function get_site_info( \WP_REST_Request $request ) {
		$data = array(
			'site_name'   => get_bloginfo( 'name' ),
			'site_url'    => get_site_url(),
			'home_url'    => get_home_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'is_multisite' => is_multisite(),
			'language'    => get_bloginfo( 'language' ),
			'charset'     => get_bloginfo( 'charset' ),
			'timezone'    => wp_timezone_string(),
			'date_format' => get_option( 'date_format' ),
			'time_format' => get_option( 'time_format' ),
		);

		$this->log_success( $request, 'site_info' );
		return rest_ensure_response( $data );
	}

	/**
	 * Get plugin list.
	 */
	public function get_plugins( \WP_REST_Request $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins = array();
		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$plugins[] = array(
				'name'           => $plugin_data['Name'],
				'version'        => $plugin_data['Version'],
				'author'         => $plugin_data['Author'],
				'description'    => $plugin_data['Description'],
				'active'         => in_array( $plugin_path, $active_plugins, true ),
				'network_active' => is_plugin_active_for_network( $plugin_path ),
			);
		}

		$this->log_success( $request, 'plugins' );
		return rest_ensure_response( $plugins );
	}

	/**
	 * Get theme list.
	 */
	public function get_themes( \WP_REST_Request $request ) {
		$themes        = wp_get_themes();
		$current_theme = wp_get_theme();

		$theme_list = array();
		foreach ( $themes as $theme ) {
			$theme_list[] = array(
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'author'      => $theme->get( 'Author' ),
				'description' => $theme->get( 'Description' ),
				'active'      => ( $theme->get_stylesheet() === $current_theme->get_stylesheet() ),
			);
		}

		$this->log_success( $request, 'themes' );
		return rest_ensure_response( $theme_list );
	}

	/**
	 * Get system status.
	 */
	public function get_system_status( \WP_REST_Request $request ) {
		global $wpdb;

		$status = array(
			'server'     => array(
				'software'            => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
				'php_version'         => PHP_VERSION,
				'mysql_version'       => $wpdb->db_version(),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
				'memory_limit'        => ini_get( 'memory_limit' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			),
			'wordpress'  => array(
				'version'          => get_bloginfo( 'version' ),
				'multisite'        => is_multisite(),
				'debug_mode'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'memory_limit'     => WP_MEMORY_LIMIT,
				'max_memory_limit' => WP_MAX_MEMORY_LIMIT,
			),
			'database'   => array(
				'tables_count'  => count( $wpdb->tables() ),
				'database_size' => $this->get_database_size(),
			),
			'filesystem' => array(
				'uploads_writable' => wp_is_writable( wp_upload_dir()['basedir'] ),
				'content_writable' => wp_is_writable( WP_CONTENT_DIR ),
			),
		);

		$this->log_success( $request, 'system_status' );
		return rest_ensure_response( $status );
	}

	/**
	 * Get user count by role.
	 */
	public function get_user_count( \WP_REST_Request $request ) {
		$user_count = count_users();

		$data = array(
			'total'   => $user_count['total_users'],
			'by_role' => $user_count['avail_roles'],
		);

		$this->log_success( $request, 'user_count' );
		return rest_ensure_response( $data );
	}

	/**
	 * Get post statistics.
	 */
	public function get_post_stats( \WP_REST_Request $request ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$stats      = array();

		foreach ( $post_types as $post_type ) {
			$counts = wp_count_posts( $post_type->name );
			$stats[ $post_type->name ] = array(
				'label'   => $post_type->label,
				'publish' => $counts->publish ?? 0,
				'draft'   => $counts->draft ?? 0,
				'pending' => $counts->pending ?? 0,
				'private' => $counts->private ?? 0,
				'trash'   => $counts->trash ?? 0,
			);
		}

		$this->log_success( $request, 'post_stats' );
		return rest_ensure_response( $stats );
	}

	/**
	 * Health check endpoint (no auth required).
	 *
	 * Returns minimal information to avoid fingerprinting.
	 */
	public function health_check( \WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'status'    => 'ok',
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get database size with caching.
	 *
	 * @return string Human-readable database size.
	 */
	private function get_database_size() {
		$cached = get_transient( 'llm_connector_db_size' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Use $wpdb->prepare() to avoid direct interpolation.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s",
				DB_NAME
			)
		);

		$size = $result ? size_format( $result, 2 ) : 'Unknown';

		// Cache for 5 minutes.
		set_transient( 'llm_connector_db_size', $size, 5 * MINUTE_IN_SECONDS );

		return $size;
	}

	/**
	 * Log a successful request.
	 *
	 * @param \WP_REST_Request $request  The request object.
	 * @param string           $endpoint Endpoint name for the log.
	 */
	private function log_success( \WP_REST_Request $request, $endpoint ) {
		$api_key = $request->get_header( 'X-WP-LLM-API-Key' );
		if ( $api_key ) {
			$api_key_hash = hash( 'sha256', $api_key );
			$this->security->log_request( $api_key_hash, $endpoint, $request->get_params(), 200 );
		}
	}
}
