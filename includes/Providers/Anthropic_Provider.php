<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Providers;

/**
 * Anthropic (Claude) provider implementation.
 *
 * @since 2.0.0
 */
class Anthropic_Provider implements LLM_Provider_Interface {

	const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
	const API_VERSION  = '2023-06-01';

	/**
	 * @var array Provider configuration.
	 */
	private $config = array();

	/**
	 * {@inheritdoc}
	 */
	public function init( array $config ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'enabled'       => false,
				'api_key'       => '',
				'default_model' => 'claude-sonnet-4-6',
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validate_credentials() {
		return ! empty( $this->config['api_key'] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_provider_name() {
		return 'anthropic';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_provider_display_name() {
		return 'Anthropic (Claude)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_config_fields() {
		return array(
			'enabled'       => array(
				'type'  => 'checkbox',
				'label' => __( 'Enable Anthropic Provider', 'wp-llm-connector' ),
			),
			'api_key'       => array(
				'type'  => 'password',
				'label' => __( 'API Key', 'wp-llm-connector' ),
			),
			'default_model' => array(
				'type'    => 'select',
				'label'   => __( 'Default Model', 'wp-llm-connector' ),
				'options' => $this->get_supported_models(),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_read_only() {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array( 'text_generation', 'json_response', 'function_calling' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_supported_models(): array {
		return array( 'claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_text( $prompt, array $options = array() ) {
		if ( ! $this->validate_credentials() ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Anthropic API key is not configured.', 'wp-llm-connector' )
			);
		}

		$model      = sanitize_text_field( $options['model'] ?? $this->config['default_model'] );
		$max_tokens = absint( $options['max_tokens'] ?? 1024 );

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $this->config['api_key'],
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return new \WP_Error(
				'anthropic_api_error',
				$data['error']['message'] ?? __( 'Anthropic API request failed.', 'wp-llm-connector' ),
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_with_wp_ai_client( $ai_client ): void {
		if ( is_object( $ai_client ) && method_exists( $ai_client, 'register_provider' ) ) {
			$ai_client->register_provider( $this->get_provider_name(), $this );
		}
	}
}
