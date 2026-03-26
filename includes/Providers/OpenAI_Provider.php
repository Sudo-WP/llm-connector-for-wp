<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Providers;

/**
 * OpenAI provider implementation.
 *
 * @since 2.0.0
 */
class OpenAI_Provider implements LLM_Provider_Interface {

	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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
				'default_model' => 'gpt-4o',
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
		return 'openai';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_provider_display_name() {
		return 'OpenAI';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_config_fields() {
		return array(
			'enabled'       => array(
				'type'  => 'checkbox',
				'label' => __( 'Enable OpenAI Provider', 'wp-llm-connector' ),
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
		return array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_text( $prompt, array $options = array() ) {
		if ( ! $this->validate_credentials() ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'OpenAI API key is not configured.', 'wp-llm-connector' )
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
					'Authorization' => 'Bearer ' . $this->config['api_key'],
					'Content-Type'  => 'application/json',
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
				'openai_api_error',
				$data['error']['message'] ?? __( 'OpenAI API request failed.', 'wp-llm-connector' ),
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
