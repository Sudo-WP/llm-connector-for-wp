<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Providers;

/**
 * Google Gemini provider implementation.
 *
 * @since 2.0.0
 */
class Gemini_Provider implements LLM_Provider_Interface {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

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
				'default_model' => 'gemini-2.0-flash',
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
		return 'gemini';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_provider_display_name() {
		return 'Google Gemini';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_config_fields() {
		return array(
			'enabled'       => array(
				'type'  => 'checkbox',
				'label' => __( 'Enable Gemini Provider', 'wp-llm-connector' ),
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
		return array( 'text_generation', 'json_response' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_supported_models(): array {
		return array( 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_text( $prompt, array $options = array() ) {
		if ( ! $this->validate_credentials() ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Gemini API key is not configured.', 'wp-llm-connector' )
			);
		}

		$model = sanitize_text_field( $options['model'] ?? $this->config['default_model'] );

		$url = self::API_BASE . $model . ':generateContent';
		$url = add_query_arg( 'key', $this->config['api_key'], $url );

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
		);

		if ( ! empty( $options['max_tokens'] ) ) {
			$body['generationConfig'] = array(
				'maxOutputTokens' => absint( $options['max_tokens'] ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type' => 'application/json',
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
			$error_message = __( 'Gemini API request failed.', 'wp-llm-connector' );
			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}
			return new \WP_Error(
				'gemini_api_error',
				$error_message,
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
