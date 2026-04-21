=== LLM Connector for WordPress ===
Contributors: SudoWP, WP Republic
Tags: llm, ai, mcp, rest-api, diagnostics, claude, gemini
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only MCP bridge for WordPress with optional AI provider integration. Claude.ai Web UI, Claude Code, Gemini CLI, Cursor, Windsurf, Cline — plus Anthropic, OpenAI, and Google Gemini as outbound providers.

== Description ==

LLM Connector for WordPress runs in two complementary directions.

**Inbound (MCP bridge):** The plugin exposes a read-only REST API that AI clients connect to via the Model Context Protocol (MCP). Unlike CLI-only bridges, it ships a verified remote MCP config for the Claude.ai web UI, and auto-generates matching configs for Claude Code, Gemini CLI, and Cursor / Windsurf / VS Code (Cline). Every request is authenticated with a SHA-256 hashed API key, rate-limited per key, and recorded in a dedicated audit table that you can filter, search, and export from the Access Log admin panel.

**Outbound (AI providers):** WordPress can optionally act as an AI text generation client via the WP 7.0 AI Client API and the WP 6.9+ Abilities API. Configure Anthropic (Claude), OpenAI, and Google Gemini credentials on the AI Providers tab; the plugin auto-registers them with WordPress's native AI infrastructure and exposes read-only + `generate_text` abilities.

Both directions are orthogonal: enabling providers does not open any inbound endpoints, and the inbound MCP bridge does not require any provider credentials.

**Supported inbound AI tools:**

* Claude.ai Web UI (verified)
* Claude Code (Anthropic)
* Gemini CLI (Google)
* Cursor, Windsurf, Cline, VS Code Copilot (via MCP)
* Any HTTP client via the REST API

**Supported outbound providers:**

* Anthropic (Claude)
* OpenAI
* Google Gemini

**Key Features:**

* Auto-generated MCP config for Claude.ai Web UI, Claude Code, Gemini CLI, Cursor / Windsurf / VS Code (Cline) with one-click copy
* `/mcp` manifest endpoint that filters advertised tools to what the site has enabled
* Inline "Test Connection" button that pings `/health` from the settings page
* Access Log admin panel: color-coded response codes, HTTP method, IP, execution time, filters (range / status / search), pagination, CSV export, Clear Log
* Recent Activity widget on the main settings page
* SHA-256 hashed API key storage — plaintext shown once, never persisted
* WP 7.0 AI Client + WP 6.9 Abilities API integration (`function_exists()`-guarded, no-op on older WordPress)
* Per-provider configuration tab (API key, default model, enable toggle)
* Configurable per-endpoint access control for the inbound REST surface
* Rate limiting per API key (1-1000 requests/hour)
* Read-only REST data endpoints — enforced at the permission layer
* Full audit logging with 90-day automatic cleanup
* HTTPS connection detection
* Minimal `/health` endpoint for uptime monitoring

**Available Endpoints:**

* `/health` - Health check (no authentication required)
* `/mcp` - MCP server manifest (auth required, filtered to enabled tools)
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
4. On the **General** tab, enable the connector and select which endpoints to allow.
5. On the **API Keys** tab, generate an API key (copy it immediately — it is shown only once).
6. Use the "Connect Your AI Client" section to copy a ready-to-use MCP config.
7. (Optional) On the **AI Providers** tab, enable and configure Anthropic / OpenAI / Gemini credentials for outbound `generate_text` use via the WP AI Client / Abilities API.

== Frequently Asked Questions ==

= Inbound vs. outbound — which one do I need? =

They're independent features. If you want Claude.ai, Claude Code, Gemini CLI, or similar clients to connect *into* your WordPress site for diagnostics, use the inbound MCP bridge (API Keys tab + Connect Your AI Client). If you want WordPress itself to *call* an LLM via the WP 7.0 AI Client / Abilities API, use the outbound providers (AI Providers tab). You can enable either, both, or neither.

= Is my API key stored securely? =

Yes. Inbound API keys are stored as SHA-256 hashes in WordPress. The raw key is displayed only once at generation time and is never stored or shown again. Outbound provider API keys (Anthropic, OpenAI, Gemini) are stored in `wp_options` and are only ever transmitted to the corresponding vendor API via `wp_remote_post()`.

= Can LLMs modify my site via the inbound bridge? =

No. The inbound REST data endpoints are read-only at the permission layer. No write endpoints (POST, PUT, DELETE) are exposed in this build; a premium write tier is documented in `docs/WRITE_TIER.md` but not active.

= Do I need separate MCP server files for each AI tool? =

No. The same `wordpress_mcp_server.py` works with all MCP-compatible tools. The `/mcp` REST manifest additionally lets remote-capable clients (like the Claude.ai Web UI) connect without running a local MCP server at all.

= How does rate limiting work? =

Each inbound API key has a rate-limit window; default is 60 requests per hour (configurable 1-1000).

= What happens to my data if I uninstall the plugin? =

All plugin settings, API keys, and audit logs are permanently deleted when the plugin is uninstalled (deleted) from WordPress. Deactivating the plugin preserves all data. You can enable "Preserve Settings" in the admin to keep data on uninstall.

== Screenshots ==

1. Settings page — General tab with endpoint allowlist and log purge.
2. AI Providers tab — per-provider credentials and model selection.
3. API Keys tab — key table plus the "Connect Your AI Client" MCP config generator.
4. Access Log admin page — summary bar, filters, WP_List_Table.

== Changelog ==

= 2.1.0 =
* Added: `/mcp` REST manifest endpoint with filtered tool list + composite `wp_full_diagnostics` entry
* Added: Access Log admin panel (`Settings > LLM Connector Log`) — WP_List_Table with range / status / search filters, pagination, CSV export, Clear Log
* Added: Recent Activity widget on the main settings page
* Added: "Connect Your AI Client" section with auto-generated MCP config (Claude.ai Web UI verified, Claude Code, Gemini CLI, Cursor / Windsurf / Cline)
* Added: Inline Test Connection badge pinging `/health`
* Added: Write-tier permission scaffold (`Write_Permission_Manager`) — no write endpoints exposed, fails closed with 503
* Added: `CLAUDE.md`, `HANDOFF.md`, `SECURITY.md`, `docs/WRITE_TIER.md`
* Changed: DB schema to 2.1 (adds `http_method`, `execution_time_ms` alongside `provider` from 2.0; new indexes on `response_code` and `ip_address`)
* Changed: `Security_Manager::validate_api_key()` backfills `write_enabled: false` / `write_scopes: []` on read for legacy keys
* Changed: AI Providers tab gains a clarification notice separating provider config from MCP client config
* Merged: Keeps everything from 2.0.0 (Providers system, Abilities_Manager, WP 7.0 AI Client) and adds the MCP-bridge / access-log / write-tier groundwork on top

= 2.0.0 =
* Added: Provider system with Anthropic (Claude), OpenAI, and Google Gemini support
* Added: AI Providers admin tab for managing provider API keys and models
* Added: WP 7.0 AI Client API integration — providers auto-register when available
* Added: WP 6.9+ Abilities API integration with 8 abilities (site-info, list-plugins, system-status, user-count, post-stats, list-providers, generate-text, provider-status)
* Added: Provider Registry with capability-based provider selection
* Added: generate_text() method on all providers via wp_remote_post()
* Added: Optional Composer autoloader support
* Added: provider column and index to audit log table (dbDelta upgrade-safe)
* Changed: Admin settings page now uses tabbed navigation (General | AI Providers | API Keys)
* Changed: Database schema version bumped to 2.0
* Compatibility: All new WP 7.0/6.9 hooks are guarded — plugin still works on WP 5.8+

= 0.1.3 =
* Security: Added table name validation on all database queries to prevent SQL injection.
* Hardening: Added ABSPATH guards on 6 include files to prevent direct access.
* Hardening: Applied IP address sanitization on all logged IPs.
* Hardening: Reduced API key transient TTL for tighter session windows.
* Fix: Resolved nested form element causing validation errors in admin settings page.

= 0.1.2 =
* Added: Gemini CLI support via the same MCP server
* Added: GEMINI_CLI_SETUP.md setup guide
* Added: MCP compatibility table for additional AI tools (Cursor, Windsurf, Cline, VS Code Copilot)
* Updated: README.md with Gemini CLI setup instructions
* Updated: Plugin description to reflect multi-LLM support
* Updated: Admin connection info to mention both Claude Code and Gemini CLI

= 0.1.1 =
* Added: Display the path of the audit log database table in the settings
* Added: Purge log button to clear all audit log entries
* Added: MCP server (wordpress_mcp_server.py) for Claude Code integration
* Added: CLAUDE_CODE_SETUP.md with setup instructions
* Changed: Read-only mode enforced by design (toggle removed)
* Improved: Updated logging description for better clarity
* Updated: Documentation to mention Claude Code LLM support

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

= 2.1.0 =
Additive merge of the MCP-bridge / access-log / write-tier development line into 2.0.0. Adds `/mcp` manifest, Access Log admin panel, auto-generated MCP client config, and write-tier scaffold. Everything from 2.0.0 (Providers system, Abilities_Manager, WP 7.0 AI Client) is retained. Schema upgrades automatically via `Activator::maybe_upgrade()` on plugins_loaded.

= 2.0.0 =
Major update: adds Anthropic, OpenAI, and Gemini provider support with AI text generation. Integrates with WP 7.0 AI Client and WP 6.9 Abilities API. No breaking changes to existing REST endpoints.

= 0.1.2 =
Added Gemini CLI support. No breaking changes — the same MCP server file now works with both Claude Code and Gemini CLI.

= 0.1.1 =
Added MCP server for Claude Code integration and enforced read-only mode by design.

= 0.1.0 =
Initial release. Generate a new API key after installation.
