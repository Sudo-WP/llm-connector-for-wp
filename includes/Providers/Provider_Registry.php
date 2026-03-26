<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Providers;

use WP_LLM_Connector\Security\Security_Manager;

/**
 * Registry for managing LLM provider instances.
 *
 * @since 2.0.0
 */
class Provider_Registry {

	/**
	 * @var Security_Manager
	 */
	private $security;

	/**
	 * @var LLM_Provider_Interface[]
	 */
	private $providers = array();

	/**
	 * Provider slug to class mapping.
	 *
	 * @var array
	 */
	private $provider_classes = array(
		'anthropic' => Anthropic_Provider::class,
		'openai'    => OpenAI_Provider::class,
		'gemini'    => Gemini_Provider::class,
	);

	/**
	 * @param Security_Manager $security Injected security manager instance.
	 */
	public function __construct( Security_Manager $security ) {
		$this->security = $security;
	}

	/**
	 * Initialize all configured providers.
	 */
	public function init() {
		$settings         = get_option( 'wp_llm_connector_settings', array() );
		$provider_configs = $settings['providers'] ?? array();

		foreach ( $this->provider_classes as $slug => $class ) {
			$config   = $provider_configs[ $slug ] ?? array();
			$provider = new $class();
			$provider->init( $config );
			$this->providers[ $slug ] = $provider;
		}

		/**
		 * Fires after all built-in providers have been registered.
		 *
		 * Allows third-party plugins to register additional providers.
		 *
		 * @since 2.0.0
		 *
		 * @param Provider_Registry $registry The provider registry instance.
		 */
		do_action( 'wp_llm_connector_register_providers', $this );
	}

	/**
	 * Register providers with the WP AI Client API (WP 7.0+).
	 *
	 * Guarded so it silently no-ops on WP < 7.0.
	 */
	public function register_wp_ai_client_providers() {
		if ( ! $this->is_wp_ai_client_available() ) {
			return;
		}

		if ( function_exists( 'wp_ai_client' ) ) {
			$ai_client = wp_ai_client();
		} elseif ( class_exists( 'AI_Client' ) ) {
			$ai_client = \AI_Client::get_instance();
		} else {
			return;
		}

		foreach ( $this->get_active_providers() as $provider ) {
			$provider->register_with_wp_ai_client( $ai_client );
		}
	}

	/**
	 * Check if the WP AI Client API is available.
	 *
	 * @return bool
	 */
	public function is_wp_ai_client_available() {
		return function_exists( 'wp_ai_client' ) || class_exists( 'AI_Client' );
	}

	/**
	 * Get a provider by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return LLM_Provider_Interface|null
	 */
	public function get_provider( $slug ) {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return LLM_Provider_Interface[]
	 */
	public function get_all_providers() {
		return $this->providers;
	}

	/**
	 * Get providers that are enabled and have valid credentials.
	 *
	 * @return LLM_Provider_Interface[]
	 */
	public function get_active_providers() {
		$active = array();
		$settings         = get_option( 'wp_llm_connector_settings', array() );
		$provider_configs = $settings['providers'] ?? array();

		foreach ( $this->providers as $slug => $provider ) {
			$config = $provider_configs[ $slug ] ?? array();
			if ( ! empty( $config['enabled'] ) && $provider->validate_credentials() ) {
				$active[ $slug ] = $provider;
			}
		}

		return $active;
	}

	/**
	 * Get the first provider that supports a given capability.
	 *
	 * @param string $capability Capability string.
	 * @return LLM_Provider_Interface|null
	 */
	public function get_provider_for_capability( $capability ) {
		foreach ( $this->get_active_providers() as $provider ) {
			if ( in_array( $capability, $provider->get_capabilities(), true ) ) {
				return $provider;
			}
		}

		return null;
	}
}
