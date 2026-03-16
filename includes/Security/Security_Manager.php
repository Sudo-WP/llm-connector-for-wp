<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace WP_LLM_Connector\Security;

class Security_Manager {
	private $settings;

	public function __construct() {
		$this->settings = get_option( 'wp_llm_connector_settings', array() );
	}

	/**
	 * Check if the connector is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Validate API key from request header.
	 *
	 * Keys are stored as SHA-256 hashes. The incoming key is hashed
	 * and compared with hash_equals() for timing-attack resistance.
	 *
	 * @param string $api_key Raw API key from the request header.
	 * @return array|false Key data array on success, false on failure.
	 */
	public function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		$incoming_hash = hash( 'sha256', $api_key );
		$stored_keys   = $this->settings['api_keys'] ?? array();

		foreach ( $stored_keys as $key_data ) {
			if ( hash_equals( $key_data['key_hash'], $incoming_hash ) ) {
				// Check if key is active.
				if ( isset( $key_data['active'] ) && ! $key_data['active'] ) {
					return false;
				}

				// Check expiration if set.
				if ( isset( $key_data['expires'] ) && $key_data['expires'] < time() ) {
					return false;
				}

				return $key_data;
			}
		}

		return false;
	}

	/**
	 * Check rate limiting for an API key.
	 *
	 * Uses a transient with a fixed expiry set only on the first request
	 * in the window, so subsequent requests do not reset the TTL.
	 *
	 * @param string $api_key_hash SHA-256 hash of the API key.
	 * @return bool True if within limit, false if exceeded.
	 */
	public function check_rate_limit( $api_key_hash ) {
		$rate_limit    = $this->settings['rate_limit'] ?? 60;
		$transient_key = 'llm_connector_rate_' . substr( $api_key_hash, 0, 12 );

		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			// First request — set the window.
			set_transient( $transient_key, 1, 60 );
			return true;
		}

		if ( $requests >= $rate_limit ) {
			return false;
		}

		// Increment without resetting TTL by using the options API directly.
		global $wpdb;
		$transient_option = '_transient_' . $transient_key;
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $requests + 1 ),
			array( 'option_name' => $transient_option ),
			array( '%d' ),
			array( '%s' )
		);

		// Refresh the object cache entry so subsequent reads are correct.
		wp_cache_delete( $transient_key, 'transient' );

		return true;
	}

	/**
	 * Log an API request to the audit table.
	 *
	 * @param string $api_key_hash SHA-256 hash of the API key.
	 * @param string $endpoint     Endpoint name.
	 * @param array  $request_data Request parameters.
	 * @param int    $response_code HTTP response code.
	 */
	public function log_request( $api_key_hash, $endpoint, $request_data, $response_code ) {
		if ( ! ( $this->settings['log_requests'] ?? true ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'llm_connector_audit_log';

		// Validate table name.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$wpdb->insert(
			$table_name,
			array(
				'api_key_hash' => sanitize_text_field( $api_key_hash ),
				'endpoint'     => sanitize_text_field( $endpoint ),
				'request_data' => wp_json_encode( $request_data ),
				'response_code' => absint( $response_code ),
				'ip_address'   => $this->get_client_ip(),
				'user_agent'   => $user_agent,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * Only trusts proxy headers when a known proxy is in use.
	 * Falls back to REMOTE_ADDR which cannot be spoofed at the TCP level.
	 *
	 * @return string Sanitized IP address.
	 */
	private function get_client_ip() {
		// Only trust proxy headers if the request comes from a known proxy.
		// Cloudflare IPs or a configured reverse proxy should be checked here.
		// For safety, default to REMOTE_ADDR.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		// Handle comma-separated IPs (take first one).
		if ( strpos( $ip, ',' ) !== false ) {
			$ip = explode( ',', $ip )[0];
		}

		$ip = trim( $ip );

		// Validate it looks like an IP.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Generate a secure API key.
	 *
	 * @return string Raw API key (must be shown to user immediately, then discarded).
	 */
	public static function generate_api_key() {
		return 'wpllm_' . bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Validate that an endpoint is allowed by the admin configuration.
	 *
	 * @param string $endpoint Endpoint slug.
	 * @return bool
	 */
	public function is_endpoint_allowed( $endpoint ) {
		$allowed = $this->settings['allowed_endpoints'] ?? array();
		return in_array( $endpoint, $allowed, true );
	}

	/**
	 * Check if read-only mode is enforced.
	 *
	 * @return bool
	 */
	public function is_read_only_mode() {
		return $this->settings['read_only_mode'] ?? true;
	}

	/**
	 * Check if the current request is over HTTPS.
	 *
	 * @return bool
	 */
	public function is_secure_connection() {
		return is_ssl();
	}
}
