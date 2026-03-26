<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Abilities;

use WP_LLM_Connector\Security\Security_Manager;
use WP_LLM_Connector\Providers\Provider_Registry;

/**
 * Manages WP Abilities API integration (WP 6.9+).
 *
 * All hooks are guarded with function_exists() checks so this is a
 * complete no-op on WordPress versions that lack the Abilities API.
 *
 * @since 2.0.0
 */
class Abilities_Manager {

	/**
	 * @var Security_Manager
	 */
	private $security;

	/**
	 * @var Provider_Registry
	 */
	private $provider_registry;

	/**
	 * @param Security_Manager  $security          Security manager instance.
	 * @param Provider_Registry $provider_registry Provider registry instance.
	 */
	public function __construct( Security_Manager $security, Provider_Registry $provider_registry ) {
		$this->security          = $security;
		$this->provider_registry = $provider_registry;

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the LLM Connector ability category.
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'llm-connector',
			array(
				'label'       => __( 'LLM Connector', 'wp-llm-connector' ),
				'description' => __( 'WordPress site diagnostics and LLM provider access.', 'wp-llm-connector' ),
			)
		);
	}

	/**
	 * Register all abilities.
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_site_info_ability();
		$this->register_list_plugins_ability();
		$this->register_system_status_ability();
		$this->register_user_count_ability();
		$this->register_post_stats_ability();
		$this->register_list_providers_ability();
		$this->register_generate_text_ability();
		$this->register_provider_status_ability();
	}

	/**
	 * Default permission callback for read-only abilities.
	 *
	 * @return bool
	 */
	private function check_permission() {
		return $this->security->is_enabled() && current_user_can( 'manage_options' );
	}

	/**
	 * Register site-info ability.
	 */
	private function register_site_info_ability() {
		wp_register_ability(
			'llm-connector/site-info',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'Site Information', 'wp-llm-connector' ),
				'description'         => __( 'Retrieve basic WordPress site information.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site_name'  => array( 'type' => 'string' ),
						'site_url'   => array( 'type' => 'string' ),
						'wp_version' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_site_info' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register list-plugins ability.
	 */
	private function register_list_plugins_ability() {
		wp_register_ability(
			'llm-connector/list-plugins',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'List Plugins', 'wp-llm-connector' ),
				'description'         => __( 'List all installed WordPress plugins.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'    => array( 'type' => 'string' ),
							'version' => array( 'type' => 'string' ),
							'active'  => array( 'type' => 'boolean' ),
						),
					),
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_list_plugins' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register system-status ability.
	 */
	private function register_system_status_ability() {
		wp_register_ability(
			'llm-connector/system-status',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'System Status', 'wp-llm-connector' ),
				'description'         => __( 'Retrieve server and WordPress system status.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'server'    => array( 'type' => 'object' ),
						'wordpress' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_system_status' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register user-count ability.
	 */
	private function register_user_count_ability() {
		wp_register_ability(
			'llm-connector/user-count',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'User Count', 'wp-llm-connector' ),
				'description'         => __( 'Get user counts by role.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total'   => array( 'type' => 'integer' ),
						'by_role' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_user_count' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register post-stats ability.
	 */
	private function register_post_stats_ability() {
		wp_register_ability(
			'llm-connector/post-stats',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'Post Statistics', 'wp-llm-connector' ),
				'description'         => __( 'Get content statistics by post type.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_post_stats' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register list-providers ability.
	 */
	private function register_list_providers_ability() {
		wp_register_ability(
			'llm-connector/list-providers',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'List Providers', 'wp-llm-connector' ),
				'description'         => __( 'List all registered LLM providers.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'slug'         => array( 'type' => 'string' ),
							'display_name' => array( 'type' => 'string' ),
							'capabilities' => array( 'type' => 'array' ),
						),
					),
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_list_providers' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Register generate-text ability (requires auth, not MCP-public).
	 */
	private function register_generate_text_ability() {
		wp_register_ability(
			'llm-connector/generate-text',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'Generate Text', 'wp-llm-connector' ),
				'description'         => __( 'Generate text using a configured LLM provider.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'   => array( 'type' => 'string' ),
						'provider' => array( 'type' => 'string' ),
						'model'    => array( 'type' => 'string' ),
					),
					'required'   => array( 'prompt' ),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_generate_text' ),
				'meta'                => array( 'mcp' => array( 'public' => false ) ),
			)
		);
	}

	/**
	 * Register provider-status ability.
	 */
	private function register_provider_status_ability() {
		wp_register_ability(
			'llm-connector/provider-status',
			array(
				'category'            => 'llm-connector',
				'label'               => __( 'Provider Status', 'wp-llm-connector' ),
				'description'         => __( 'Get the status of all configured LLM providers.', 'wp-llm-connector' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'permission_callback' => array( $this, 'public_permission_callback' ),
				'execute_callback'    => array( $this, 'execute_provider_status' ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * Permission callback for public MCP abilities.
	 *
	 * @return bool
	 */
	public function public_permission_callback() {
		return $this->check_permission();
	}

	/**
	 * Execute: site-info.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_site_info( $input ) {
		return array(
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => get_site_url(),
			'home_url'     => get_home_url(),
			'wp_version'   => get_bloginfo( 'version' ),
			'php_version'  => PHP_VERSION,
			'is_multisite' => is_multisite(),
			'language'     => get_bloginfo( 'language' ),
			'charset'      => get_bloginfo( 'charset' ),
			'timezone'     => wp_timezone_string(),
			'date_format'  => get_option( 'date_format' ),
			'time_format'  => get_option( 'time_format' ),
		);
	}

	/**
	 * Execute: list-plugins.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_list_plugins( $input ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$plugins        = array();

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

		return $plugins;
	}

	/**
	 * Execute: system-status.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_system_status( $input ) {
		global $wpdb;

		return array(
			'server'    => array(
				'software'            => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
				'php_version'         => PHP_VERSION,
				'mysql_version'       => $wpdb->db_version(),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
				'memory_limit'        => ini_get( 'memory_limit' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			),
			'wordpress' => array(
				'version'          => get_bloginfo( 'version' ),
				'multisite'        => is_multisite(),
				'debug_mode'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'memory_limit'     => WP_MEMORY_LIMIT,
				'max_memory_limit' => WP_MAX_MEMORY_LIMIT,
			),
		);
	}

	/**
	 * Execute: user-count.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_user_count( $input ) {
		$user_count = count_users();

		return array(
			'total'   => $user_count['total_users'],
			'by_role' => $user_count['avail_roles'],
		);
	}

	/**
	 * Execute: post-stats.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_post_stats( $input ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$stats      = array();

		foreach ( $post_types as $post_type ) {
			$counts                        = wp_count_posts( $post_type->name );
			$stats[ $post_type->name ] = array(
				'label'   => $post_type->label,
				'publish' => $counts->publish ?? 0,
				'draft'   => $counts->draft ?? 0,
				'pending' => $counts->pending ?? 0,
				'private' => $counts->private ?? 0,
				'trash'   => $counts->trash ?? 0,
			);
		}

		return $stats;
	}

	/**
	 * Execute: list-providers.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_list_providers( $input ) {
		$result = array();

		foreach ( $this->provider_registry->get_all_providers() as $slug => $provider ) {
			$result[] = array(
				'slug'             => $slug,
				'display_name'     => $provider->get_provider_display_name(),
				'capabilities'     => $provider->get_capabilities(),
				'supported_models' => $provider->get_supported_models(),
			);
		}

		return $result;
	}

	/**
	 * Execute: generate-text.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_generate_text( $input ) {
		$prompt        = sanitize_text_field( $input['prompt'] ?? '' );
		$provider_slug = sanitize_text_field( $input['provider'] ?? '' );

		if ( empty( $prompt ) ) {
			return new \WP_Error( 'missing_prompt', __( 'A prompt is required.', 'wp-llm-connector' ) );
		}

		if ( ! empty( $provider_slug ) ) {
			$provider = $this->provider_registry->get_provider( $provider_slug );
		} else {
			$provider = $this->provider_registry->get_provider_for_capability( 'text_generation' );
		}

		if ( ! $provider ) {
			return new \WP_Error( 'no_provider', __( 'No active provider available for text generation.', 'wp-llm-connector' ) );
		}

		$options = array();
		if ( ! empty( $input['model'] ) ) {
			$options['model'] = sanitize_text_field( $input['model'] );
		}

		return $provider->generate_text( $prompt, $options );
	}

	/**
	 * Execute: provider-status.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute_provider_status( $input ) {
		$settings         = get_option( 'wp_llm_connector_settings', array() );
		$provider_configs = $settings['providers'] ?? array();
		$result           = array();

		foreach ( $this->provider_registry->get_all_providers() as $slug => $provider ) {
			$config    = $provider_configs[ $slug ] ?? array();
			$result[ $slug ] = array(
				'display_name'       => $provider->get_provider_display_name(),
				'enabled'            => ! empty( $config['enabled'] ),
				'credentials_valid'  => $provider->validate_credentials(),
				'capabilities'       => $provider->get_capabilities(),
				'wp_ai_client_ready' => $this->provider_registry->is_wp_ai_client_available(),
			);
		}

		return $result;
	}
}
