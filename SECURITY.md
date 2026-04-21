# Security Model

This document describes the security posture of **LLM Connector for WordPress**. It is written for developers and site operators evaluating whether the plugin is safe to run on production sites.

## Design principles

1. **Read-only by design.** The REST layer exposes `GET` endpoints only. There are no write endpoints, no form submissions, no database mutations triggered by API calls. Read-only enforcement lives in the permission callback, not in a toggle a caller can influence.
2. **No dynamic code execution.** The plugin contains no calls to `eval()`, `create_function()`, `assert()` with string input, `preg_replace` with the `/e` modifier, or variable-variable invocation of user-controlled callbacks.
3. **No filesystem writes from the REST layer.** API handlers do not write, rename, chmod, or delete files. CSV export from the Access Log panel streams to `php://output` (HTTP response), not to disk. Persistent state is limited to the WordPress options table and a dedicated audit log database table.
4. **No third-party data transmission.** The plugin does not initiate outbound HTTP requests to external services, telemetry endpoints, or update servers of its own. The only outbound HTTP call the plugin makes on its own is a loopback `wp_remote_get()` to this same site's `/health` endpoint, fired by the admin "Test Connection" button. Data flows are caller → WordPress → caller.

   **Exception:** when an AI Provider (Anthropic, OpenAI, or Gemini) is explicitly enabled and configured by the site administrator via the AI Providers tab, the `generate_text()` method calls `wp_remote_post()` to that provider's API endpoint. This call is initiated only by explicit admin configuration and carries no WordPress site data — only the prompt text submitted by the caller. Provider API keys are stored in `wp_options` under `wp_llm_connector_settings['providers'][slug]['api_key']` and are never exposed via REST endpoints.
5. **Defense in depth.** Request handling goes through multiple independent checks (enabled flag → API key → endpoint allowlist → rate limit) so a single misconfiguration does not expose data.

## API key handling

- Keys are generated with `random_bytes(32)` and prefixed with `wpllm_` (64 hex chars of entropy).
- Only a **SHA-256 hash** of each key is persisted in `wp_options` (inside `wp_llm_connector_settings`). The plaintext key is returned to the browser once at generation time, stored in a **per-user transient** with a short TTL, and then discarded. There is no code path that reads the plaintext back from storage.
- Validation uses `hash_equals()` to compare hashes in constant time, mitigating timing attacks.
- Revocation is immediate: the key row is removed from the options array, and subsequent requests hashing to the same value fail authentication.
- The plugin does **not** reuse WordPress Application Passwords. Keys are namespaced to this plugin and can be revoked without affecting any other WordPress credential.

## Rate limiting

- Per-key window of 1 hour, configurable from 1 to 1000 requests/hour.
- Implemented via a transient keyed by the first 12 chars of the key hash. The TTL is set only on window entry, so subsequent requests do not silently reset the window.
- Denied requests are logged (as `rate_limited`) for auditing.

## Audit logging

- Every authenticated request (plus every auth / endpoint / rate-limit denial) is inserted into `{prefix}llm_connector_audit_log`.
- Logged fields: timestamp, SHA-256 of the API key, endpoint, HTTP method, response code, execution time, IP address, user agent, and a sanitized JSON dump of the request params.
- The table is indexed on `timestamp`, `api_key_hash`, `response_code`, and `ip_address` so audits on very large logs stay fast.
- Entries older than 90 days are deleted by a daily WP-Cron job. Operators can also purge the log from the admin.

## Data boundaries

Endpoints expose only the data they are documented to expose:

- `/health` — no authentication, returns `{status, timestamp}` only (designed to avoid fingerprinting).
- `/site-info`, `/plugins`, `/themes`, `/system-status`, `/user-count`, `/post-stats` — return aggregate or metadata-level information. None return post content, user passwords, email bodies, private post bodies, database credentials, server passwords, or raw file contents.

No endpoint accepts user-supplied SQL, raw callables, regex fragments, or serialized PHP data.

## Transport

- The plugin does not mandate HTTPS (that is the site operator's decision and responsibility) but it exposes `Security_Manager::is_secure_connection()` for callers and logs whether HTTPS was in use.
- All admin forms use nonces + `current_user_can( 'manage_options' )`. The Access Log CSV export and Clear Log actions are both nonce- and capability-gated.
- Output is escaped through WordPress helpers (`esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`) and database queries use `$wpdb->prepare()` with whitelisted `ORDER BY` columns.

## MCP config surface

The "Connect Your AI Client" panel only exposes the real plaintext API key in its copyable config **when a key was just generated in the current admin session** (the same transient used for the one-time display). For any other case the snippet falls back to a `YOUR_API_KEY_HERE` placeholder. The key is never embedded in rendered HTML attributes.

## Reporting a vulnerability

Please report security issues privately to the maintainers rather than opening a public GitHub issue. Include:

- Plugin version (see the Settings page or `wp-llm-connector.php` header).
- WordPress and PHP versions.
- A minimal reproduction — ideally a `curl` command plus a description of the observed vs. expected behavior.
- Any relevant audit log rows.

Reports will be acknowledged within a reasonable window and, where applicable, fixed in a patch release with credit in the changelog.
