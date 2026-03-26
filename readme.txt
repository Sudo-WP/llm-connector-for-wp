=== LLM Connector for WordPress ===
Contributors: SudoWP, WP Republic
Tags: llm, ai, api, rest-api, diagnostics, mcp, claude, gemini
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to LLM agents for secure diagnostics and AI-powered features. Supports WP 7.0 AI Client provider registration, Abilities API, and MCP for Claude Code, Gemini CLI, and more.

== Description ==

LLM Connector for WordPress creates a secure REST API bridge between your WordPress site and AI coding assistants. It enables LLMs to read site diagnostics, plugin and theme inventories, system status, and content statistics through authenticated, rate-limited endpoints.

The plugin ships with a universal MCP (Model Context Protocol) server that works with Claude Code, Gemini CLI, and any other MCP-compatible AI tool — no separate downloads or configurations needed per client.

**Supported AI Tools:**

* Claude Code (Anthropic)
* Gemini CLI (Google)
* Cursor, Windsurf, Cline, VS Code Copilot (via MCP)
* Any HTTP client via the REST API

**Key Features:**

* Secure API key authentication with SHA-256 hashed storage
* Read-only by design — no write endpoints exist in the codebase
* Configurable per-endpoint access control
* Rate limiting per API key (1-1000 requests/hour)
* Full audit logging with 90-day automatic cleanup
* HTTPS connection detection with security warnings
* Universal MCP server for all compatible AI tools
* Minimal health endpoint for uptime monitoring

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
4. Generate an API key (copy it immediately as it's shown only once).
5. Enable the connector and select which endpoints to allow.
6. Install Python dependencies: `pip install mcp httpx pydantic`
7. Configure your AI tool using the included setup guides:
   - Claude Code: See CLAUDE_CODE_SETUP.md
   - Gemini CLI: See GEMINI_CLI_SETUP.md

== Frequently Asked Questions ==

= Which AI tools does this work with? =

Currently ships with setup guides for Claude Code and Gemini CLI. The MCP server works with any MCP-compatible tool (Cursor, Windsurf, Cline, VS Code Copilot). The REST API works with any HTTP client.

= Is my API key stored securely? =

Yes. API keys are stored as SHA-256 hashes in WordPress. The raw key is displayed only once at generation time and is never stored or shown again.

= Can LLMs modify my site? =

No. The plugin is read-only by design. No write endpoints (POST, PUT, DELETE) exist in the codebase. This is an architectural decision, not a setting that can be misconfigured.

= Do I need separate MCP server files for each AI tool? =

No. The same `wordpress_mcp_server.py` file works with all MCP-compatible tools. Only the client-side configuration differs (different config file locations).

= How does rate limiting work? =

Each API key has a one-hour rate limit window. The default is 60 requests per hour; it can be configured from 1 to 1000 in the settings.

= What happens to my data if I uninstall the plugin? =

All plugin settings, API keys, and audit logs are permanently deleted when the plugin is uninstalled (deleted) from WordPress. Deactivating the plugin preserves all data. You can enable "Preserve Settings" in the admin to keep data on uninstall.

== Screenshots ==

1. Settings page with endpoint configuration and API key management.
2. API key generation with one-time display.
3. Connection information with cURL example.

== Changelog ==

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

= 2.0.0 =
Major update: adds Anthropic, OpenAI, and Gemini provider support with AI text generation. Integrates with WP 7.0 AI Client and WP 6.9 Abilities API. No breaking changes to existing REST endpoints.

= 0.1.2 =
Added Gemini CLI support. No breaking changes — the same MCP server file now works with both Claude Code and Gemini CLI.

= 0.1.1 =
Added MCP server for Claude Code integration and enforced read-only mode by design.

= 0.1.0 =
Initial release. Generate a new API key after installation.
