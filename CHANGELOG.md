# Changelog

All notable changes to the WP LLM Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.2] - 2026-02-09

### Added
- Gemini CLI support via the existing MCP server (no new server code needed)
- GEMINI_CLI_SETUP.md with step-by-step Gemini CLI configuration guide
- MCP compatibility table in README for additional AI tools (Cursor, Windsurf, Cline, VS Code Copilot)
- Updated admin Connection Information box to mention both Claude Code and Gemini CLI

### Changed
- Updated plugin description to reflect multi-LLM support (Claude Code + Gemini CLI)
- Updated README.md with Gemini CLI setup instructions and quick setup commands
- Updated readme.txt with Gemini CLI in description, FAQ, and changelog
- Bumped version to 0.1.2

## [0.1.1] - 2026-02-07

### Added
- Display the path of the audit log database table in the settings page
- Purge log button to clear all audit log entries with confirmation dialog
- Shows the number of log entries in the purge button
- MCP server (mcp/wordpress_mcp_server.py) for Claude Code integration
- CLAUDE_CODE_SETUP.md with Claude Code setup instructions
- Read-only mode enforced by design (toggle removed, replaced with static indicator)

### Changed
- Improved logging description: changed "Log all API requests Keep an audit trail of all LLM access" to "Log all API requests. Keep an audit trail of all LLM access." (added period for better clarity)
- Updated plugin description to mention Claude Code LLM support with more LLMs coming in future versions
- Updated all documentation files (readme.txt, README.md) to reflect Claude Code LLM support
- Removed read-only toggle checkbox — read-only is now enforced architecturally
- Reverted cache-busting from development (time() removed from asset versions)

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

---

## Version Numbering

- **MAJOR** version: Incompatible API changes
- **MINOR** version: New functionality (backward compatible)
- **PATCH** version: Bug fixes (backward compatible)
