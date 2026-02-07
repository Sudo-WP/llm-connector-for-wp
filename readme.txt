=== LLM Connector for WordPress ===
Contributors: SudoWP, WP Republic
Tags: llm, ai, api, rest-api, diagnostics
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to LLM agents for secure, read-only diagnostics and administration. Currently supports Claude Code LLM with more LLMs coming in future versions.

== Description ==

LLM Connector for WordPress Self-Hosted Websites creates a secure REST API bridge between your WordPress site and Large Language Model agents such as Claude Code and other AI assistants. Currently supports Claude Code LLM with more LLMs coming in future versions. It enables LLMs to read site diagnostics, plugin and theme inventories, system status, and content statistics through authenticated, rate-limited endpoints.

**Key Features:**

* Secure API key authentication with SHA-256 hashed storage
* Configurable per-endpoint access control
* Rate limiting per API key (1-1000 requests/hour)
* Read-only mode enforced by default
* Full audit logging with 90-day automatic cleanup
* HTTPS connection detection with security warnings
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

= 0.1.1 =
Added purge log feature and improved documentation. Now clearly states support for Claude Code LLM with more LLMs coming in future versions.

= 0.1.0 =
Initial release. Generate a new API key after installation.
