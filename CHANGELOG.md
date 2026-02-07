# Changelog

All notable changes to the WP LLM Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
