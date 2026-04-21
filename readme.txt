=== LLM Connector for WordPress ===
Contributors: SudoWP, WP Republic
Tags: llm, ai, mcp, rest-api, diagnostics
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only MCP bridge for WordPress. Verified with Claude.ai Web UI, Claude Code, and Gemini CLI. Built-in access logging and CSV export.

== Description ==

LLM Connector for WordPress exposes a read-only REST API that AI clients connect to via the Model Context Protocol (MCP). Unlike CLI-only bridges, it ships a verified remote MCP config for the Claude.ai web UI, and auto-generates matching configs for Claude Code, Gemini CLI, and Cursor / Windsurf / VS Code (Cline).

Every request is authenticated with a SHA-256 hashed API key, rate-limited per key, and recorded in a dedicated audit table that you can filter, search, and export from the Access Log admin panel.

Designed to be production-safe: no `eval()`, no filesystem writes from the REST layer, no third-party data transmission, and read-only enforcement at the permission layer. See SECURITY.md in the plugin folder for details.

**Key Features:**

* Auto-generated MCP config for Claude.ai (Web UI), Claude Code, Gemini CLI, Cursor / Windsurf / VS Code (Cline) with one-click copy
* Inline "Test Connection" button that pings `/health` from the settings page
* Access Log admin panel: color-coded response codes, HTTP method, IP, execution time, filters (range / status / search), pagination, CSV export, Clear Log
* Recent Activity widget on the main settings page (last 5 requests)
* SHA-256 hashed API key storage — plaintext shown once, never persisted
* Configurable per-endpoint access control
* Rate limiting per API key (1-1000 requests/hour)
* Read-only mode enforced by design
* Full audit logging with 90-day automatic cleanup
* HTTPS connection detection
* Minimal `/health` endpoint for uptime monitoring

**Available Endpoints:**

* `/health` - Health check (no authentication required)
* `/site-info` - Site name, URLs, WordPress and PHP versions, timezone
* `/plugins` - Complete plugin inventory with active status
* `/themes` - Theme listing with active theme identification
* `/system-status` - Server, database, and filesystem diagnostics
* `/user-count` - Total users and breakdown by role
* `/post-stats` - Content counts by post type and status

== Installation ==

1. Upload the `wp-llm-connector` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to Settings > LLM Connector.
4. Generate an API key (copy it immediately as it's shown only once; this key will be used by LLM services to authenticate with your WordPress site).
5. Enable the connector and select which endpoints to allow.
6. Configure your LLM client (Claude, GPT, etc.) to use the API key from step 4.

== Frequently Asked Questions ==

= Is my API key stored securely? =

Yes. The API keys you generate are stored as SHA-256 hashes in WordPress. The raw key is displayed only once at generation time and is never stored or shown again. These keys are used by LLM services (such as Claude and GPT) to authenticate when connecting to your WordPress site.

= Can LLMs modify my site? =

By default, the plugin enforces read-only mode. All endpoints return data without making any changes. Disabling read-only mode requires explicit confirmation in the admin panel.

= How does rate limiting work? =

Each API key has a one-hour rate limit window. The default is 60 requests per hour; it can be configured from 1 to 1000 in the settings.

= Does this plugin work with any LLM? =

Yes. The REST API is provider-agnostic. Any LLM agent or tool that can make HTTP requests with custom headers can use this plugin.

= What happens to my data if I uninstall the plugin? =

All plugin settings, API keys, and audit logs are permanently deleted when the plugin is uninstalled (deleted) from WordPress. Deactivating the plugin preserves all data.

== Screenshots ==

1. Settings page with endpoint configuration and API key management.
2. API key generation with one-time display.
3. Connection information with cURL example.

== Changelog ==

= 0.3.0 =
* Added: `GET /wp-llm-connector/v1/mcp` manifest endpoint — the auto-generated MCP config snippet now points at a real, authenticated endpoint
* Added: Static helper `API_Handler::build_mcp_manifest()` so manifest logic is unit-testable independently of the REST layer
* Added: `wp_full_diagnostics` composite tool entry (runs site-info, plugins, themes, system-status in sequence); advertised only when all four component endpoints are enabled
* Added: `CLAUDE.md` and `HANDOFF.md` repo-root docs for session orientation and handoff
* Changed: Manifest filters advertised tools to the current endpoint allowlist so clients receive an accurate tool catalog

= 0.2.1 =
* Added: Access Log admin panel (Settings > LLM Connector Log) built on WP_List_Table — columns for Timestamp, Endpoint, IP, HTTP Method, color-coded Response Code, and Execution time
* Added: Filters for date range (24h / 7d / 30d / all / custom), status (success / errors), and search by IP or endpoint
* Added: Summary bar showing today's Requests, Unique IPs, and Error rate
* Added: CSV export honoring the active filters; Clear Log with confirmation
* Added: Recent Activity widget on the main settings page
* Changed: Audit log schema extended (http_method, execution_time_ms columns, new indexes). Applied automatically on update via a schema version bump

= 0.2.0 =
* Added: "Connect Your AI Client" section in the settings page with a client picker
* Added: Auto-generated MCP config snippets for Claude.ai (Web UI, Verified), Claude Code, Gemini CLI, and Cursor / Windsurf / VS Code (Cline)
* Added: "Copy full config" button (uses the real API key when a freshly generated key is available; otherwise copies a placeholder)
* Added: Inline "Test Connection" badge that pings `/health` via admin-ajax

= 0.1.1 =
* Added: Display the path of the audit log database table in the settings
* Added: Purge log button to clear all audit log entries
* Improved: Updated logging description for better clarity
* Updated: Documentation to mention Claude Code LLM support with more LLMs coming in future versions

= 0.1.0 =
* Initial release.
* REST API endpoints for site info, plugins, themes, system status, user count, and post stats.
* API key authentication with SHA-256 hashed storage.
* Per-endpoint access control via admin settings.
* Rate limiting per API key with configurable thresholds.
* Audit logging with 90-day automatic cleanup.
* Read-only mode enforced by default.
* HTTPS detection with security warning headers.
* Admin settings page under Settings > LLM Connector.

== Upgrade Notice ==

= 0.3.0 =
Adds the `/mcp` manifest endpoint that the auto-generated config snippet already points at, and introduces a unit-testable static manifest builder.

= 0.2.1 =
Adds the Access Log admin panel with filters and CSV export. Audit log schema is upgraded automatically on first load after update.

= 0.2.0 =
Adds the "Connect Your AI Client" section with auto-generated MCP config for Claude.ai Web UI, Claude Code, Gemini CLI, and Cursor / Windsurf / VS Code (Cline), plus an inline Test Connection badge.

= 0.1.1 =
Added purge log feature and improved documentation. Now clearly states support for Claude Code LLM with more LLMs coming in future versions.

= 0.1.0 =
Initial release. Generate a new API key after installation.
