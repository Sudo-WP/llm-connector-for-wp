<?php
namespace WP_LLM_Connector\Security;

class Security_Manager {
	private $settings;

	/**
	 * Microtime set when the plugin's REST route is about to dispatch. Used
	 * to compute execution_time_ms for the audit log. Static so it survives
	 * across the (multiple) Security_Manager instances that may exist in a
	 * single request lifecycle.
	 *
	 * @var float|null
	 */
	public static $request_start = null;

	public function __construct() {
		$this->settings = get_option( 'wp_llm_connector_settings', array() );
	}

	/**
	 * Record the start of a REST request so log_request() can emit an
	 * execution_time_ms. Hooked from API_Handler on rest_pre_dispatch for
	 * requests inside the plugin's namespace.
	 */
	public static function mark_request_start() {
		self::$request_start = microtime( true );
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

				// Forward-compatible write-tier backfill (0.4.0-dev).
				// Keys generated before the write tier shipped do not have
				// write_enabled / write_scopes fields. Callers expect these
				// fields to exist. Defaulting to closed guarantees that a
				// legacy key is never silently upgraded to write-capable.
				// See docs/WRITE_TIER.md for the full migration story.
				return self::apply_write_tier_defaults( $key_data );
			}
		}

		return false;
	}

	/**
	 * Ensure a key record has the write-tier fields, defaulting to closed.
	 *
	 * Pure function — does not touch storage. Callers that need the enriched
	 * record to persist should save it back to the option after calling.
	 * For the common validate-on-request path we don't persist; the next
	 * settings save will normalize the record via Admin_Interface::sanitize_settings().
	 *
	 * @param array $key_data Raw per-key record from the options row.
	 * @return array          Record guaranteed to have write_enabled and write_scopes.
	 */
	private static function apply_write_tier_defaults( array $key_data ) {
		if ( ! array_key_exists( 'write_enabled', $key_data ) ) {
			$key_data['write_enabled'] = false;
		}
		if ( ! array_key_exists( 'write_scopes', $key_data ) || ! is_array( $key_data['write_scopes'] ) ) {
			$key_data['write_scopes'] = array();
		}
		return $key_data;
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
			set_transient( $transient_key, 1, HOUR_IN_SECONDS );
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

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$http_method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: null;

		$execution_ms = null;
		if ( null !== self::$request_start ) {
			$execution_ms = round( ( microtime( true ) - self::$request_start ) * 1000, 2 );
		}

		$wpdb->insert(
			$table_name,
			array(
				'api_key_hash'      => sanitize_text_field( $api_key_hash ),
				'endpoint'          => sanitize_text_field( $endpoint ),
				'http_method'       => $http_method,
				'execution_time_ms' => $execution_ms,
				'request_data'      => wp_json_encode( $request_data ),
				'response_code'     => absint( $response_code ),
				'ip_address'        => $this->get_client_ip(),
				'user_agent'        => $user_agent,
			),
			array( '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s' )
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
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

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
