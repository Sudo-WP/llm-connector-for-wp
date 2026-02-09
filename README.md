# LLM Connector for WordPress

**Version:** 0.1.2 (MVP)  
**Author:** SudoWP.com  
**License:** GPL v2 or later

A secure WordPress plugin that enables LLM agents to connect to your WordPress site in read-only mode for diagnostics, troubleshooting, and administration. Currently supports Claude Code and Gemini CLI via MCP (Model Context Protocol), with more LLM integrations coming in future versions.

## Purpose

This plugin creates a bridge between your WordPress site and AI LLM agents, allowing them to:
- Diagnose site issues
- Analyze plugin and theme configurations
- Review system health and performance
- Gather statistics and metadata
- Assist with troubleshooting

**All in a secure, read-only mode by default.**

## Key Features

### Security First
- **API Key Authentication**: Secure token-based access control
- **Read-Only by Design**: Enforced architecturally — no write endpoints exist in the codebase
- **Rate Limiting**: Configurable request limits per API key
- **Audit Logging**: Full request logging for security monitoring
- **IP Tracking**: Monitor where requests originate
- **Granular Permissions**: Enable only the endpoints you need

### Extensible Architecture
- **MCP Compatible**: Works with any MCP-compatible AI tool (Claude Code, Gemini CLI, Cursor, Windsurf, Cline)
- **Single Server File**: One `wordpress_mcp_server.py` serves all MCP clients
- **Standard REST API**: Uses WordPress REST API standards — works with any HTTP client
- **Clean Code**: PSR-4 autoloading, namespaced classes

### Admin-Friendly
- **Simple Interface**: Easy-to-use WordPress admin panel
- **One-Click API Keys**: Generate secure keys instantly
- **Visual Feedback**: Clear status indicators and messages
- **Documentation Built-In**: Connection examples in the admin

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `wp-llm-connector` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Navigate to **Settings > LLM Connector**
5. Generate an API key (this key will be used by LLM services to authenticate with your WordPress site)
6. Configure your allowed endpoints

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

1. Scroll to the "API Keys" section
2. Enter a descriptive name for your key (e.g., "Claude Production" or "Gemini Dev")
3. Click "Generate API Key"
4. **Copy and save the key immediately** — it will be partially hidden after you leave the page
5. You'll use this key to configure your LLM client

### 3. Install Python Dependencies

The MCP server requires Python 3.10+ and the following packages:

```bash
pip install mcp httpx pydantic
```

### 4. Test the Connection

```bash
curl -H "X-WP-LLM-API-Key: wpllm_your_api_key_here" \
     https://yoursite.com/wp-json/wp-llm-connector/v1/site-info
```

Replace `wpllm_your_api_key_here` with the actual API key you copied from WordPress.

## Available Endpoints

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

## Security Considerations

### Default Security Posture

- ✅ Read-only mode enforced by design (no write endpoints in codebase)
- ✅ API key authentication required
- ✅ Rate limiting enabled (60 req/hour default)
- ✅ All requests logged
- ✅ No endpoints enabled by default

### Best Practices

1. **Use Strong API Keys**: The plugin generates cryptographically secure keys
2. **Enable Only Needed Endpoints**: Don't give access to data you don't need to share
3. **Monitor the Audit Log**: Regularly review the access logs in your database
4. **Rotate Keys Regularly**: Revoke and regenerate API keys periodically
5. **Use HTTPS**: Always use SSL/TLS for your WordPress site
6. **Limit Rate Limits**: Adjust based on your actual needs

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
├── wp-llm-connector.php          # Main plugin file
├── includes/
│   ├── Core/                     # Plugin core, activation, deactivation
│   ├── API/                      # REST API endpoints
│   ├── Security/                 # Auth, rate limiting, audit logging
│   ├── Admin/                    # Settings page
│   └── Providers/                # LLM provider interfaces
├── mcp/
│   └── wordpress_mcp_server.py   # Universal MCP server (all clients)
├── assets/                       # CSS and JS
├── CLAUDE_CODE_SETUP.md          # Claude Code setup guide
├── GEMINI_CLI_SETUP.md           # Gemini CLI setup guide
└── README.md
```

## Roadmap

### Phase 1 (Current - MVP)
- ✅ Read-only REST API endpoints
- ✅ API key authentication
- ✅ Rate limiting & audit logging
- ✅ Admin interface
- ✅ Claude Code MCP integration
- ✅ Gemini CLI MCP integration

### Phase 2 (Planned)
- [ ] Auto-generated MCP configurations
- [ ] Webhook support for proactive alerts
- [ ] Custom endpoint builder (GUI)
- [ ] Advanced audit log filtering

### Phase 3 (Future)
- [ ] Write operations (with confirmation)
- [ ] Multi-tenant support for agencies
- [ ] Real-time notifications
- [ ] Dashboard widget for quick stats

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
