# Changelog

All notable changes to the WP LLM Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **`docs/WRITE_TIER.md`** — full design document for the premium write tier: in/out of scope operations, five-gate permission model (read-tier → per-key write flag → per-key scopes → one-time confirmation token → WP capability), audit-trail shape, API key schema additions, endpoint naming convention (`/wp-llm-connector/v1/write/*`), UI changes needed in the admin. **No write endpoints are exposed in this build** — this is scaffolding + design only.
- **`includes/Security/Write_Permission_Manager.php`** — permission scaffold. Security-critical methods fully implemented:
  - `generate_write_token()` — 64-char hex, stored as SHA-256-hashed transient with a 15-minute TTL.
  - `validate_and_consume_token()` — single-use; deletes the transient on the first successful validation so a replay cannot succeed. Length / charset guard rejects obviously malformed tokens before touching the options table.
  - `key_has_write_access()` — looks up the key by hash and returns true only when the record has `active: true` **and** `write_enabled: true`. Fails closed on any missing / falsy field.
  - `check_write_capability()` and `authorize_write_request()` are stubbed with TODOs, deliberately defaulting closed. `authorize_write_request()` returns a `503 write_tier_not_implemented` so any accidental early wiring of a write endpoint fails safe.
- Per-key `write_enabled` (bool, default false) and `write_scopes` (array, default `[]`) fields on new API keys.

### Changed
- `Security_Manager::validate_api_key()` now backfills `write_enabled: false` and `write_scopes: []` on every read for keys generated before 0.4.0 — forward-compatible, options-only migration, no DB version bump needed. Existing keys stay read-only until the site owner explicitly flips the flag.
- `Admin_Interface::sanitize_settings()` preserves the two new fields through settings round-trips, with a whitelist on `write_scopes` values so a malformed submission can't smuggle in an unknown scope.
- `CLAUDE.md` gains a permanent **Release workflow** section: smoke-test checklist (`/health`, `/site-info`, `/mcp`, settings page, access log), the ordered release routine, and the hard-rules list (no credentials / live URLs / `wp-config.php`, no `--no-verify`, no force-push to `main`).
- Plugin version bumped to `0.4.0-dev` (`wp-llm-connector.php` header + `WP_LLM_CONNECTOR_VERSION`). `readme.txt` `Stable tag` stays at `0.3.0` until the write tier actually ships.

## [0.3.0] - 2026-04-21

### Added
- **`GET /wp-llm-connector/v1/mcp`** — MCP server manifest endpoint. Authenticated via the standard `X-WP-LLM-API-Key` header; intentionally exempt from the per-endpoint allowlist so authenticated clients can always discover which tools the installation currently exposes. Every call is recorded in the audit log as the `mcp` endpoint.
- `API_Handler::build_mcp_manifest( array $settings ): array` — static, unit-testable manifest builder. Filters the advertised `tools[]` to the permission slugs in `$settings['allowed_endpoints']` so clients receive an accurate tool list instead of a catalog of endpoints that would 404.
- `wp_full_diagnostics` composite tool entry — runs `/site-info`, `/plugins`, `/themes`, and `/system-status` in sequence (`composite: true`, `steps[]`). Only advertised when all four component endpoints are enabled; otherwise omitted entirely so clients never see a composite that would fail mid-sequence.
- `CLAUDE.md` — session-start orientation doc: project summary, repo layout, non-negotiable architecture rules, verification checklist, known gotchas.
- `HANDOFF.md` — living session snapshot: current status, state per subsystem, prioritized open tasks, decisions log, gotchas.
- `API_DOCS.md` — new `### MCP Manifest` section documenting request, response, filtering behavior, and the static helper.

### Changed
- Plugin version bumped to 0.3.0 (`wp-llm-connector.php` header and `WP_LLM_CONNECTOR_VERSION` constant).

## [0.2.1] - 2026-04-21

### Added
- **Access Log admin panel** at Settings > LLM Connector Log (`?page=wp-llm-connector-access-log`), implemented with `WP_List_Table` in `includes/Admin/Access_Log_Table.php`
- Columns: Timestamp (absolute + relative "x ago"), Tool / Endpoint, IP Address, HTTP Method, Response Code (color-coded badges — 2xx green, 4xx amber, 5xx red), Execution time (ms)
- Filter bar: date range (Last 24h / 7d / 30d / All / Custom), status (All / Success / Errors), and search by IP or endpoint
- Summary bar with today's totals: Requests, Unique IPs, Error rate %
- **Clear Log** (confirm-guarded) and **Export CSV** (honors the active filters) actions, both nonce + capability-checked
- Pagination at 50 rows per page, with sortable columns (timestamp, endpoint, ip_address, response_code, execution_time_ms)
- **Recent Activity** widget on the main settings page showing the last 5 entries with a "View full log →" link
- Friendly empty state when the audit log table is missing or has no entries

### Changed
- Audit log schema bumped to DB version 1.1 — new nullable `http_method` (varchar 10) and `execution_time_ms` (float) columns, plus indexes on `response_code` and `ip_address` for filter/sort performance
- `Security_Manager::log_request()` now captures `http_method` from `$_SERVER['REQUEST_METHOD']` and `execution_time_ms` from a REST-dispatch start timer
- `API_Handler::register_routes()` hooks `rest_pre_dispatch` to mark the request start for entries inside the plugin's namespace
- `Activator::maybe_upgrade()` runs on `plugins_loaded` via `Plugin::init()` so schema upgrades apply on plugin updates without reactivation
- Plugin version bumped to 0.2.1 (`wp-llm-connector.php` header and `WP_LLM_CONNECTOR_VERSION` constant)

## [0.2.0] - 2026-04-21

### Added
- "Connect Your AI Client" section on the Settings > LLM Connector page
- Client dropdown with presets for Claude.ai (Web UI) — marked Verified, Claude Code, Gemini CLI, and Cursor / Windsurf / VS Code (Cline)
- Auto-generated MCP config snippet that uses the site's real REST URL, an `mcpServers` key derived from `get_bloginfo('name')` via `sanitize_title()` (`wordpress-<slug>`), and a masked API key (first 8 chars + `...`) in the preview
- Client-specific comment headers above the snippet explaining the expected file path (e.g. `~/.claude/mcp.json`, `~/.gemini/mcp.json`, Cursor/Windsurf/Cline paths)
- "Copy full config" button that copies the complete config with the real API key when a freshly generated key is still held in the user transient, and falls back to a `YOUR_API_KEY_HERE` placeholder otherwise (with an inline notice explaining why)
- "Test Connection" button that hits the `/health` endpoint server-side via admin-ajax and renders a green "Connected" / red "Failed" status badge inline
- New `wp_ajax_wp_llm_connector_test_connection` handler (capability + nonce guarded) for the health check

### Notes
- The generated snippet uses the plugin's actual REST namespace (`wp-llm-connector/v1/mcp`) and real auth header (`X-WP-LLM-API-Key`) so the config is directly usable once a matching `/mcp` endpoint is wired up
- Snippet styling uses existing WordPress admin color variables (`--wp-admin-theme-color`) for consistency with the rest of the settings page

### Changed
- Plugin version bumped to 0.2.0 (`wp-llm-connector.php` header and `WP_LLM_CONNECTOR_VERSION` constant)

## [0.1.1] - 2026-02-07

### Added
- Display the path of the audit log database table in the settings page
- Purge log button to clear all audit log entries with confirmation dialog
- Shows the number of log entries in the purge button

### Changed
- Improved logging description: changed "Log all API requests Keep an audit trail of all LLM access" to "Log all API requests. Keep an audit trail of all LLM access." (added period for better clarity)
- Updated plugin description to mention Claude Code LLM support with more LLMs coming in future versions
- Updated all documentation files (readme.txt, README.md) to reflect Claude Code LLM support

## [0.1.0] - 2025-02-07

### Added - Initial MVP Release

#### Core Features
- REST API endpoints for WordPress diagnostics
- API key authentication system
- Read-only mode enforcement (enabled by default)
- Rate limiting (60 requests/hour per API key by default)
- Comprehensive audit logging with IP tracking
- WordPress admin interface for configuration

#### Security Features
- Cryptographically secure API key generation
- SHA-256 hashing for API key storage in logs
- Configurable endpoint permissions
- Request validation and sanitization
- User agent and IP address tracking
- Protection against direct file access

#### API Endpoints
- `/health` - Health check (no authentication required)
- `/site-info` - Basic site information
- `/plugins` - List all installed plugins
- `/themes` - List all installed themes  
- `/system-status` - Comprehensive system diagnostics
- `/user-count` - User statistics by role
- `/post-stats` - Content statistics by post type

#### Admin Interface
- Settings page under Settings > LLM Connector
- API key management (generate, revoke, copy)
- Endpoint permission toggles
- Rate limit configuration
- Logging options
- Connection information and examples
- Visual status indicators

#### Architecture
- PSR-4 autoloading
- Namespaced classes (`WP_LLM_Connector\`)
- Modular directory structure
- Provider interface for future LLM integrations
- Claude provider reference implementation
- Singleton pattern for core plugin
- Clean separation of concerns

#### Developer Features
- Extensible provider system
- Interface-based design for LLM providers
- Well-documented code
- Example MCP configuration for Claude Code
- Database schema documentation
- Security-first defaults

#### Database
- Custom audit log table creation
- Automatic cleanup on uninstall
- Options storage for settings
- Transient-based rate limiting

### Technical Details
- **Minimum WordPress**: 5.8
- **Minimum PHP**: 7.4
- **Database**: Auto-creates audit log table
- **License**: GPL v2 or later
- **Text Domain**: wp-llm-connector

### Future Enhancements Planned
- Provider-specific UI configuration
- Auto-generated MCP configurations
- Webhook support for proactive monitoring
- GUI-based custom endpoint builder
- Advanced audit log filtering
- Write operations (with confirmation)
- Real-time notifications
- Dashboard widgets

---

## Version Numbering

- **MAJOR** version: Incompatible API changes
- **MINOR** version: New functionality (backward compatible)
- **PATCH** version: Bug fixes (backward compatible)

## Release Notes

### 0.1.0 - MVP Focus

This initial release focuses on establishing a secure, read-only connection between WordPress sites and LLM agents. The architecture is built for extensibility, allowing future versions to support multiple LLM providers and additional functionality while maintaining security as the top priority.

**Key Design Decisions:**
- Read-only by default (can be disabled, but not recommended)
- No endpoints enabled until explicitly selected
- Strong API key generation (64 characters + prefix)
- Comprehensive logging for security auditing
- Rate limiting to prevent abuse
- Interface-based provider system for future LLM support

**Not Included in MVP:**
- Write operations
- Custom endpoint GUI builder
- Automated MCP configuration export
- WebSocket support
- Multi-site network support
- Advanced analytics dashboard

These features are planned for future releases based on user feedback and real-world usage patterns.
