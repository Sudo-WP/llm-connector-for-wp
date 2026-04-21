# WordPress MCP Server — Claude Code Setup Guide

## Overview

This MCP server connects Claude Code to your WordPress site via the
WP LLM Connector plugin. Once configured, Claude Code can automatically
query your site's info, plugins, themes, system status, and more.

## Prerequisites

1. **WP LLM Connector plugin** active on your WordPress site
2. **API key** generated in WordPress > Settings > LLM Connector
3. **Python 3.10+** installed
4. **Claude Code** installed (`npm install -g @anthropic-ai/claude-code`)

## Step 1: Install Python Dependencies

```bash
pip install mcp httpx pydantic
```

## Step 2: Place the MCP Server File

Copy `wordpress_mcp.py` to a permanent location:

```bash
mkdir -p ~/mcp-servers
cp wordpress_mcp.py ~/mcp-servers/wordpress_mcp.py
```

## Step 3: Configure Claude Code

Add the MCP server to your Claude Code configuration. Edit (or create)
the file `~/.claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "python",
      "args": ["/home/sikam/mcp-servers/wordpress_mcp.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-SITE.COM",
        "WP_LLM_API_KEY": "YOUR_API_KEY_HERE"
      }
    }
  }
}
```

**Replace `YOUR_API_KEY_HERE`** with the actual API key you copied from
WordPress > Settings > LLM Connector.

**Windows path alternative** (if running Claude Code from Windows):
```json
{
  "mcpServers": {
    "wordpress": {
      "command": "wsl",
      "args": ["python3", "/home/sikam/mcp-servers/wordpress_mcp.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-SITE.COM",
        "WP_LLM_API_KEY": "YOUR_API_KEY_HERE"
      }
    }
  }
}
```

## Step 4: Restart Claude Code

After saving the configuration, restart Claude Code for the MCP server
to be loaded.

## Step 5: Test

In Claude Code, you can now ask things like:

- "Check the health of my WordPress site"
- "What plugins are installed?"
- "Show me the system status"
- "Run a full diagnostic on my WordPress site"
- "How many users does my site have?"
- "What content has been published?"

## Available Tools

| Tool | Description |
|------|-------------|
| `wp_health_check` | Quick connectivity test (no auth required) |
| `wp_get_site_info` | Site name, URL, WP version, PHP version |
| `wp_list_plugins` | All plugins with version & active status |
| `wp_list_themes` | All themes with active status |
| `wp_get_system_status` | Server, PHP, MySQL, memory, disk info |
| `wp_get_user_count` | User totals by role |
| `wp_get_post_stats` | Content counts by type and status |
| `wp_full_diagnostics` | Comprehensive report (all endpoints) |

## Multiple Sites

To connect multiple WordPress sites, add separate entries:

```json
{
  "mcpServers": {
    "wordpress-production": {
      "command": "python",
      "args": ["/home/sikam/mcp-servers/wordpress_mcp.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-SITE.COM",
        "WP_LLM_API_KEY": "prod_key_here"
      }
    },
    "wordpress-staging": {
      "command": "python",
      "args": ["/home/sikam/mcp-servers/wordpress_mcp.py"],
      "env": {
        "WP_LLM_SITE_URL": "https://YOUR-STAGING-SITE.COM",
        "WP_LLM_API_KEY": "staging_key_here"
      }
    }
  }
}
```

## Troubleshooting

**"WP_LLM_SITE_URL is not set"** — The environment variable is missing
from your Claude Code config. Check the `env` section.

**"Authentication failed"** — Your API key is wrong or revoked. Generate
a new one in WordPress > Settings > LLM Connector.

**"Cannot connect"** — The site URL is wrong or the site is down. Test
with `curl https://your-site.com/wp-json/wp-llm-connector/v1/health`.

**"Rate limit exceeded"** — Increase the rate limit in WordPress >
Settings > LLM Connector (default is 60/hour).
