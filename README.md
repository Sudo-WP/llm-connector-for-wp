# LLM Connector for WordPress

**Version:** 2.0.0
**Author:** SudoWP.com
**License:** GPL v2 or later

A secure WordPress plugin that connects your site to LLM agents and the WordPress AI ecosystem. Provides read-only site diagnostics via REST API and MCP, multi-provider AI text generation (Anthropic, OpenAI, Gemini), and forward-compatible integration with the WP 7.0 AI Client API and WP 6.9+ Abilities API.

## 📹 Demo Video

Watch the LLM Connector in action with Claude Code:

[![LLM Connector Demo](https://img.youtube.com/vi/YOUR_VIDEO_ID/maxresdefault.jpg)](https://www.youtube.com/watch?v=RTll7S1rGFE)

*Click to watch on YouTube*

## Purpose

This plugin creates a bridge between your WordPress site and AI tools, allowing them to:
- Diagnose site issues and review system health
- Analyze plugin and theme configurations
- Gather statistics and metadata
- Generate text via configured LLM providers (Anthropic Claude, OpenAI GPT, Google Gemini)
- Register as a provider in the WP 7.0 AI Client ecosystem
- Expose site capabilities through the WP 6.9+ Abilities API

**All diagnostic endpoints are read-only by design. AI text generation requires explicit provider configuration and admin-level authentication.**

## Key Features

### Security First
- **API Key Authentication**: Secure token-based access control with SHA-256 hashed storage
- **Read-Only by Design**: Enforced architecturally — no write endpoints exist in the codebase
- **Rate Limiting**: Configurable request limits per API key (1–1,000 req/hour)
- **Audit Logging**: Full request logging with 90-day automatic cleanup
- **IP Tracking**: Monitor where requests originate
- **Granular Permissions**: Enable only the endpoints you need

### Multi-Provider AI Integration (v2.0)
- **Anthropic (Claude)**: claude-opus-4-6, claude-sonnet-4-6, claude-haiku-4-5-20251001
- **OpenAI**: gpt-4o, gpt-4o-mini, gpt-4-turbo
- **Google Gemini**: gemini-2.0-flash, gemini-1.5-pro, gemini-1.5-flash
- **Capability-based routing**: Request text generation by capability, not just provider name
- **Extensible**: Register custom providers via `wp_llm_connector_register_providers` action

### WordPress AI Stack (v2.0)
- **WP 7.0 AI Client API**: Providers auto-register when the AI Client is available
- **WP 6.9+ Abilities API**: 8 abilities registered under `llm-connector` category
- **Backward Compatible**: All new hooks are guarded — plugin works on WP 5.8+

### MCP Compatible
- **Single Server File**: One `wordpress_mcp_server.py` serves all MCP clients
- **Works with**: Claude Code, Gemini CLI, Cursor, Windsurf, Cline, VS Code Copilot
- **Standard REST API**: Uses WordPress REST API standards — works with any HTTP client

### Admin-Friendly
- **Tabbed Interface**: General settings, AI Providers, and API Keys on separate tabs
- **Per-Provider Configuration**: Enable/disable toggle, API key field, default model selector
- **One-Click API Keys**: Generate secure connector API keys instantly
- **Visual Feedback**: Clear status indicators and messages

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `wp-llm-connector` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Navigate to **Settings > LLM Connector**
5. Generate an API key (this key will be used by LLM services to authenticate with your WordPress site)
6. Configure your allowed endpoints
7. (Optional) Go to the **AI Providers** tab to configure Anthropic, OpenAI, or Gemini

### Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Quick Start

### 1. Enable the Connector

Navigate to **Settings > LLM Connector** and:
1. Check "Enable Connector"
2. Select which endpoints to allow
3. Save settings

### 2. Generate an API Key

1. Go to the **API Keys** tab
2. Enter a descriptive name for your key (e.g., "Claude Production" or "Gemini Dev")
3. Click "Generate API Key"
4. **Copy and save the key immediately** — it will be partially hidden after you leave the page
5. You'll use this key to configure your LLM client

### 3. Configure AI Providers (Optional)

1. Go to the **AI Providers** tab
2. Enable the providers you want (Anthropic, OpenAI, and/or Gemini)
3. Enter your API key for each enabled provider
4. Select a default model
5. Save Provider Settings

### 4. Install Python Dependencies (for MCP)

The MCP server requires Python 3.10+ and the following packages:

```bash
pip install mcp httpx pydantic
```

### 5. Test the Connection

```bash
curl -H "X-WP-LLM-API-Key: wpllm_your_api_key_here" \
     https://yoursite.com/wp-json/wp-llm-connector/v1/site-info
```

Replace `wpllm_your_api_key_here` with the actual API key you copied from WordPress.

## Available REST Endpoints

All endpoints require authentication via the `X-WP-LLM-API-Key` header.

| Endpoint | Auth | Description |
|----------|:----:|-------------|
| `GET /health` | No | Health check — connector status |
| `GET /site-info` | Yes | Site name, WP/PHP version, timezone |
| `GET /plugins` | Yes | All installed plugins with status |
| `GET /themes` | Yes | All installed themes with active status |
| `GET /system-status` | Yes | Server, database, memory, filesystem |
| `GET /user-count` | Yes | User statistics by role |
| `GET /post-stats` | Yes | Content counts by type and status |

## Abilities (WP 6.9+)

When running on WordPress 6.9 or later with the Abilities API available, the plugin registers these abilities under the `llm-connector` category:

| Ability | MCP Public | Description |
|---------|:----------:|-------------|
| `llm-connector/site-info` | Yes | Site information |
| `llm-connector/list-plugins` | Yes | Installed plugins |
| `llm-connector/system-status` | Yes | System diagnostics |
| `llm-connector/user-count` | Yes | User statistics |
| `llm-connector/post-stats` | Yes | Content statistics |
| `llm-connector/list-providers` | Yes | Registered LLM providers |
| `llm-connector/provider-status` | Yes | Provider configuration status |
| `llm-connector/generate-text` | No | Text generation (requires auth) |

All abilities require `manage_options` capability and the connector to be enabled.

## Connecting to Claude Code

For detailed instructions, see **[CLAUDE_CODE_SETUP.md](CLAUDE_CODE_SETUP.md)**.

### Quick Setup

```bash
claude mcp add wordpress-site \
    -e WP_LLM_SITE_URL=https://YOUR-SITE.COM \
    -e WP_LLM_API_KEY=YOUR_API_KEY \
    -- python3 /path/to/wordpress_mcp_server.py
```

### Manual Configuration (`~/.claude/claude_desktop_config.json`)

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "python3",
      "args": ["/path/to/wordpress_mcp_server.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-SITE.COM",
        "WP_LLM_API_KEY": "YOUR_API_KEY_HERE"
      }
    }
  }
}
```

## Connecting to Gemini CLI

For detailed instructions, see **[GEMINI_CLI_SETUP.md](GEMINI_CLI_SETUP.md)**.

### Quick Setup

```bash
gemini mcp add wordpress-site \
    -- python3 /path/to/wordpress_mcp_server.py
```

Then add environment variables to `~/.gemini/settings.json`.

### Manual Configuration (`~/.gemini/settings.json`)

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "python3",
      "args": ["/path/to/wordpress_mcp_server.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-SITE.COM",
        "WP_LLM_API_KEY": "YOUR_API_KEY_HERE"
      }
    }
  }
}
```

Verify the connection by running `/mcp` inside Gemini CLI.

## Other MCP-Compatible Tools

The same `wordpress_mcp_server.py` works with any MCP-compatible AI tool. The only difference is where the configuration lives:

| Tool | Config File |
|------|------------|
| Claude Code | `~/.claude/claude_desktop_config.json` |
| Gemini CLI | `~/.gemini/settings.json` |
| Cursor | `.cursor/mcp.json` in project root |
| Windsurf | `~/.codeium/windsurf/mcp_config.json` |
| VS Code Copilot | `.vscode/mcp.json` in project root |

All use the same `command` + `args` + `env` pattern shown above.

## Provider Extensibility

Third-party plugins can register additional LLM providers:

```php
add_action( 'wp_llm_connector_register_providers', function( $registry ) {
    $provider = new My_Custom_Provider(); // Must implement LLM_Provider_Interface
    $provider->init( $config );
});
```

The `LLM_Provider_Interface` requires these methods: `init()`, `validate_credentials()`, `get_provider_name()`, `get_provider_display_name()`, `get_config_fields()`, `supports_read_only()`, `get_capabilities()`, `get_supported_models()`, `generate_text()`, `register_with_wp_ai_client()`.

## Security Considerations

### Default Security Posture

- Read-only mode enforced by design (no write endpoints in codebase)
- API key authentication required for all data endpoints
- Rate limiting enabled (60 req/hour default)
- All requests logged with IP tracking
- No endpoints enabled by default
- Provider API keys stored server-side only, never exposed to the browser

### Best Practices

1. **Use Strong API Keys**: The plugin generates cryptographically secure keys
2. **Enable Only Needed Endpoints**: Don't give access to data you don't need to share
3. **Monitor the Audit Log**: Regularly review the access logs in your database
4. **Rotate Keys Regularly**: Revoke and regenerate API keys periodically
5. **Use HTTPS**: Always use SSL/TLS for your WordPress site
6. **Limit Rate Limits**: Adjust based on your actual needs
7. **Protect Provider API Keys**: Treat provider API keys (Anthropic, OpenAI, Gemini) like passwords

### What Data is Exposed?

The plugin ONLY exposes data through the endpoints you explicitly enable. It does NOT expose:
- User passwords or credentials
- Email content
- Private post content
- Database credentials
- Server passwords
- File contents

## Architecture

```
wp-llm-connector/
├── wp-llm-connector.php          # Main plugin file (v2.0.0)
├── includes/
│   ├── Core/                     # Plugin bootstrap, activation, deactivation
│   ├── API/                      # REST API endpoints (v1, unchanged)
│   ├── Security/                 # Auth, rate limiting, audit logging
│   ├── Admin/                    # Tabbed settings page (General | AI Providers | API Keys)
│   ├── Providers/                # LLM provider system
│   │   ├── LLM_Provider_Interface.php   # Provider contract
│   │   ├── Anthropic_Provider.php       # Claude API integration
│   │   ├── OpenAI_Provider.php          # OpenAI API integration
│   │   ├── Gemini_Provider.php          # Gemini API integration
│   │   └── Provider_Registry.php        # Provider lifecycle & lookup
│   └── Abilities/                # WP 6.9+ Abilities API integration
│       └── Abilities_Manager.php
├── mcp/
│   └── wordpress_mcp_server.py   # Universal MCP server (all clients)
├── assets/                       # CSS and JS
├── CLAUDE_CODE_SETUP.md          # Claude Code setup guide
├── GEMINI_CLI_SETUP.md           # Gemini CLI setup guide
└── README.md
```

## Changelog

### 2.0.0 (2026-03-26)
- **Added**: Multi-provider system — Anthropic (Claude), OpenAI, and Google Gemini with `generate_text()` support
- **Added**: Provider Registry with capability-based provider selection and extensibility hook
- **Added**: WP 7.0 AI Client API integration — providers auto-register when available
- **Added**: WP 6.9+ Abilities API integration with 8 abilities
- **Added**: AI Providers admin tab with per-provider enable toggle, API key, and model selector
- **Added**: Tabbed admin navigation (General | AI Providers | API Keys)
- **Added**: `provider` column and index in audit log table (dbDelta upgrade-safe)
- **Added**: Optional Composer autoloader support
- **Changed**: Database schema version bumped to 2.0
- **Compatibility**: All new hooks guarded — plugin works on WP 5.8+

### 0.1.3 (2026-03-16)
- **Security**: Table name validation on all database queries to prevent SQL injection
- **Hardening**: ABSPATH guards on all include files
- **Hardening**: IP address sanitization on all logged IPs
- **Hardening**: Reduced API key transient TTL
- **Fix**: Resolved nested form element in admin settings page

### 0.1.2 (2026-02-09)
- **Added**: Gemini CLI support via the same MCP server
- **Added**: GEMINI_CLI_SETUP.md setup guide
- **Added**: MCP compatibility table for additional AI tools

### 0.1.1 (2026-02-07)
- **Added**: MCP server for Claude Code integration
- **Added**: CLAUDE_CODE_SETUP.md setup guide
- **Added**: Audit log display and purge button in admin
- **Changed**: Read-only mode enforced by design (toggle removed)

### 0.1.0 (2025-02-07)
- Initial release with REST API endpoints, API key auth, rate limiting, audit logging, and admin interface

## Contributing

Contributions, feedback, and suggestions are welcome!

1. Clone the repository
2. Install on a local WordPress instance
3. Enable `WP_DEBUG` and `WP_DEBUG_LOG`
4. Make your changes
5. Test thoroughly

## License

GPL v2 or later

## Links

- **Author**: [SudoWP.com](https://sudowp.com)
- **GitHub**: [github.com/Sudo-WP/llm-connector-for-wp](https://github.com/Sudo-WP/llm-connector-for-wp)

---

**Built with Security and Performance in mind**
