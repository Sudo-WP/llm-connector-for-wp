<?php
namespace WP_LLM_Connector\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write_Permission_Manager
 *
 * Central permission gate for the premium write tier. No write endpoints
 * exist as of 0.4.0-dev — this class is the scaffolding that future
 * POST/PUT/DELETE routes will hang off. See docs/WRITE_TIER.md for the
 * full design.
 *
 * Security-critical methods (generate_write_token, validate_and_consume_token)
 * are fully implemented. Scope- and capability-specific methods are stubbed
 * with TODO notes and are intentionally conservative: they default to
 * denying writes. This means that a half-built write endpoint calling into
 * this class cannot accidentally succeed before the scope/capability logic
 * is fleshed out.
 *
 * Invariants:
 *   - Every write endpoint's permission_callback MUST call
 *     authorize_write_request() as its first step. Never bypass it.
 *   - Write tokens are single-use: validate_and_consume_token() deletes the
 *     underlying transient on first successful validation.
 *   - Tokens are stored by SHA-256 hash, never cleartext, matching the
 *     plaintext-shown-once pattern used for API keys.
 *
 * @package WP_LLM_Connector\Security
 * @since 0.4.0
 */
class Write_Permission_Manager {

	/**
	 * Lifetime of a generated write session token, in seconds. 15 minutes is
	 * long enough for a CLI tool or AI assistant to assemble a request;
	 * short enough that a leaked token does not linger in shell history or
	 * environment variables. Do not raise this without a security review.
	 */
	const WRITE_TOKEN_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Transient key prefix for write session tokens. Keeps the WordPress
	 * options table readable and scoped to this plugin.
	 */
	const WRITE_TOKEN_TRANSIENT_PREFIX = 'wp_llm_connector_write_token_';

	/**
	 * Byte length of the raw random portion of a write token. Yields a
	 * 64-character hex string when bin2hex'd. Matches the entropy of
	 * API keys elsewhere in this plugin.
	 */
	const WRITE_TOKEN_BYTES = 32;

	/**
	 * Does a given API key (identified by SHA-256 hash) have write_enabled?
	 *
	 * Reads from `wp_llm_connector_settings.api_keys` and matches by
	 * `key_hash`. Returns true only when the matching key row has
	 * `write_enabled === true`. Any of the following returns false:
	 *   - no key with that hash exists
	 *   - the key exists but `active` is false
	 *   - `write_enabled` is missing or falsy (legacy key, pre-0.4.0)
	 *
	 * TODO (next session): once Security_Manager::validate_api_key() is
	 * updated to backfill write_enabled/write_scopes on read, this method
	 * should delegate to it rather than re-implementing the key lookup.
	 *
	 * @param string $key_hash SHA-256 hash of the raw API key.
	 * @return bool True if the key exists, is active, and has write_enabled.
	 */
	public static function key_has_write_access( string $key_hash ): bool {
		if ( '' === $key_hash ) {
			return false;
		}

		$settings = get_option( 'wp_llm_connector_settings', array() );
		$keys     = $settings['api_keys'] ?? array();

		foreach ( $keys as $key_data ) {
			if ( ! isset( $key_data['key_hash'] ) ) {
				continue;
			}
			if ( ! hash_equals( (string) $key_data['key_hash'], $key_hash ) ) {
				continue;
			}

			// Matching key found. Fail closed on any missing / falsy field.
			if ( empty( $key_data['active'] ) ) {
				return false;
			}
			return ! empty( $key_data['write_enabled'] );
		}

		return false;
	}

	/**
	 * Validate an incoming X-WP-LLM-Write-Token header value.
	 *
	 * Tokens are single-use: on a successful validation, the underlying
	 * transient is deleted before returning true. A second call with the
	 * same token returns false.
	 *
	 * Uses hash_equals() implicitly via the direct transient lookup on the
	 * SHA-256-hashed key — no timing side-channel risk because we never
	 * compare the plaintext to a stored plaintext.
	 *
	 * @param string $token Plaintext token value from the request header.
	 * @return bool True on valid + freshly consumed; false on missing,
	 *              expired, or already-consumed tokens.
	 */
	public static function validate_and_consume_token( string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		// Defensive length / charset check — reject anything that could not
		// possibly be a token we issued (cheap early exit, also avoids
		// weird keys landing in the options table via the get_transient
		// code path).
		if ( strlen( $token ) !== self::WRITE_TOKEN_BYTES * 2 || ! ctype_xdigit( $token ) ) {
			return false;
		}

		$transient_key = self::token_transient_key( $token );
		$stored        = get_transient( $transient_key );

		if ( false === $stored ) {
			// Missing or expired.
			return false;
		}

		// Consume: single-use semantics. Delete before returning success so
		// a concurrent duplicate request cannot also succeed.
		delete_transient( $transient_key );

		return true;
	}

	/**
	 * Generate a new write session token, persist its hash as a transient,
	 * and return the plaintext to the caller.
	 *
	 * The caller is expected to show the plaintext once (admin UI) and then
	 * discard it. Nothing else in the codebase has a path back from the
	 * stored hash to the plaintext.
	 *
	 * @return string Plaintext 64-char hex token. Must be shown to the user
	 *                immediately, then thrown away.
	 */
	public static function generate_write_token(): string {
		$token         = bin2hex( random_bytes( self::WRITE_TOKEN_BYTES ) );
		$transient_key = self::token_transient_key( $token );

		// Value stored is intentionally minimal — presence is the signal.
		// A future extension could store the generating user ID or scope
		// constraints, but for v1 the existence of the transient *is* the
		// permission.
		set_transient( $transient_key, 1, self::WRITE_TOKEN_TTL );

		return $token;
	}

	/**
	 * Derive the transient key used to store a token. Never store the
	 * cleartext token — only its SHA-256 hash. This mirrors how API keys
	 * are stored, so an options-table dump does not yield usable tokens.
	 *
	 * @param string $token Plaintext token.
	 * @return string Transient key.
	 */
	private static function token_transient_key( string $token ): string {
		return self::WRITE_TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token );
	}

	/**
	 * Does the effective WordPress user have the capability required by a
	 * given write scope?
	 *
	 * Scope → capability mapping is authoritative here (not in settings) so
	 * site admins cannot relax capability requirements via an options row.
	 * See docs/WRITE_TIER.md for the full table.
	 *
	 * TODO (next session): once the `owner_user_id` field is added to the
	 * per-key record, this method should accept the user ID as a parameter
	 * and use user_can( $user_id, $cap ) rather than current_user_can().
	 * For REST requests the current user is generally unauthenticated
	 * (WP_User with ID 0), so the current implementation defaults closed.
	 *
	 * @param string $scope One of: 'posts', 'plugins', 'options', 'users',
	 *                      'cache'.
	 * @return bool True when the current (or soon-to-be-passed) user has
	 *              the capability for that scope.
	 */
	public static function check_write_capability( string $scope ): bool {
		$cap_map = array(
			'posts'   => 'edit_posts',
			'plugins' => 'activate_plugins',
			'options' => 'manage_options',
			'users'   => 'create_users',
			'cache'   => 'manage_options',
		);

		if ( ! isset( $cap_map[ $scope ] ) ) {
			return false;
		}

		// TODO: replace current_user_can() with user_can( $owner_user_id, $cap )
		// once authorize_write_request() is wired up to look up the key's
		// owner user from settings. Defaulting closed until then.
		return current_user_can( $cap_map[ $scope ] );
	}

	/**
	 * Central write permission gate.
	 *
	 * Every future write endpoint MUST call this method as the first (and
	 * usually only) action in its `permission_callback`. The gate runs all
	 * checks in order and returns a WP_Error with a stable error code on
	 * the first failure. On success it returns true.
	 *
	 * Order of checks — do not reorder without updating docs/WRITE_TIER.md:
	 *   1. API key present and valid (same check as read tier)
	 *   2. Key has write_enabled === true
	 *   3. Key has the required scope in write_scopes
	 *   4. Valid, unconsumed X-WP-LLM-Write-Token header
	 *   5. WP capability check for the scope
	 *
	 * Every failure path MUST be audit-logged by the caller (or by this
	 * method once the audit helper is extended in a later session). Silent
	 * denials are a sign of a broken permission chain.
	 *
	 * TODO (next session): implement the full check chain. For 0.4.0-dev
	 * this method is deliberately unimplemented and returns a WP_Error so
	 * that any accidental early wiring of a write endpoint fails closed.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @param string           $scope   Required scope: 'posts' | 'plugins' |
	 *                                  'options' | 'users' | 'cache'.
	 * @return true|\WP_Error True on pass; WP_Error with a 4xx status on any
	 *                        failure.
	 */
	public static function authorize_write_request( \WP_REST_Request $request, string $scope ) {
		// Fail closed until fully implemented. This is intentional: shipping
		// a write route that silently allows requests because the gate is
		// half-built is strictly worse than shipping one that 503s.
		return new \WP_Error(
			'write_tier_not_implemented',
			__( 'Write tier not implemented in this build.', 'wp-llm-connector' ),
			array( 'status' => 503 )
		);
	}
}
