<?php
namespace WP_LLM_Connector\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for LLM provider implementations.
 *
 * @since 2.0.0
 */
interface LLM_Provider_Interface {

	/**
	 * Initialize the provider with configuration.
	 *
	 * @param array $config Provider configuration array.
	 */
	public function init( array $config );

	/**
	 * Validate stored credentials.
	 *
	 * @return bool True if credentials are valid.
	 */
	public function validate_credentials();

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function get_provider_name();

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_provider_display_name();

	/**
	 * Get configuration fields for the admin UI.
	 *
	 * @return array
	 */
	public function get_config_fields();

	/**
	 * Whether this provider supports read-only mode.
	 *
	 * @return bool
	 */
	public function supports_read_only();

	/**
	 * Get the capabilities this provider supports.
	 *
	 * @return array List of capability strings.
	 */
	public function get_capabilities(): array;

	/**
	 * Get the models this provider supports.
	 *
	 * @return array List of model identifier strings.
	 */
	public function get_supported_models(): array;

	/**
	 * Generate text using the provider's API.
	 *
	 * @param string $prompt  The prompt text.
	 * @param array  $options Optional parameters (model, max_tokens, etc.).
	 * @return array|WP_Error Response data or error.
	 */
	public function generate_text( $prompt, array $options = array() );

	/**
	 * Register this provider with the WP AI Client API (WP 7.0+).
	 *
	 * @param object $ai_client The WP AI Client instance.
	 */
	public function register_with_wp_ai_client( $ai_client ): void;
}
