#!/usr/bin/env python3
"""
WP LLM Connector - Claude Code MCP Integration Example

This script demonstrates how to integrate the WP LLM Connector
with Claude Code using the Model Context Protocol (MCP).

IMPORTANT: The API_KEY used here is the key you generated in WordPress
(Settings > LLM Connector), NOT a Claude/Anthropic API key. This key is
used by Claude to authenticate when connecting to your WordPress site.

Requirements:
    - Python 3.8+
    - requests library: pip install requests

Usage:
    python mcp_integration_example.py
"""

import json
import os
from typing import Dict, Any, List

# Configuration
# IMPORTANT: Replace these values with:
# 1. Your WordPress site URL
# 2. The API key you generated in WordPress (Settings > LLM Connector)
WORDPRESS_URL = "https://yoursite.com"
API_KEY = "wpllm_your_api_key_here"  # Use the API key from WordPress admin, NOT Claude API key
MCP_CONFIG_PATH = os.path.expanduser("~/.claude/mcp_config.json")


class WordPressMCPServer:
    """WordPress MCP Server for Claude Code integration"""
    
    def __init__(self, wordpress_url: str, api_key: str):
        self.wordpress_url = wordpress_url.rstrip('/')
        self.api_key = api_key
        self.base_url = f"{self.wordpress_url}/wp-json/wp-llm-connector/v1"
        self.headers = {
            "X-WP-LLM-API-Key": api_key,
            "Content-Type": "application/json"
        }
    
    def generate_mcp_config(self) -> Dict[str, Any]:
        """Generate MCP configuration for Claude Code"""
        return {
            "mcpServers": {
                "wordpress": {
                    "url": f"{self.base_url}/",
                    "transport": "http",
                    "headers": {
                        "X-WP-LLM-API-Key": self.api_key
                    },
                    "description": "WordPress site diagnostics and administration",
                    "endpoints": {
                        "health": {
                            "path": "/health",
                            "method": "GET",
                            "description": "Check connector health status"
                        },
                        "site_info": {
                            "path": "/site-info",
                            "method": "GET",
                            "description": "Get WordPress site information"
                        },
                        "plugins": {
                            "path": "/plugins",
                            "method": "GET",
                            "description": "List all installed plugins"
                        },
                        "themes": {
                            "path": "/themes",
                            "method": "GET",
                            "description": "List all installed themes"
                        },
                        "system_status": {
                            "path": "/system-status",
                            "method": "GET",
                            "description": "Get comprehensive system diagnostics"
                        },
                        "user_count": {
                            "path": "/user-count",
                            "method": "GET",
                            "description": "Get user statistics by role"
                        },
                        "post_stats": {
                            "path": "/post-stats",
                            "method": "GET",
                            "description": "Get content statistics"
                        }
                    },
                    "tools": self._generate_tools()
                }
            }
        }
    
    def _generate_tools(self) -> List[Dict[str, Any]]:
        """Generate tool definitions for MCP"""
        return [
            {
                "name": "wordpress_diagnose",
                "description": "Run comprehensive WordPress diagnostics",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "include_plugins": {
                            "type": "boolean",
                            "description": "Include plugin analysis",
                            "default": True
                        },
                        "include_themes": {
                            "type": "boolean",
                            "description": "Include theme analysis",
                            "default": True
                        },
                        "include_system": {
                            "type": "boolean",
                            "description": "Include system status",
                            "default": True
                        }
                    }
                }
            },
            {
                "name": "wordpress_security_audit",
                "description": "Perform security audit of WordPress installation",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "check_outdated": {
                            "type": "boolean",
                            "description": "Check for outdated plugins/themes",
                            "default": True
                        }
                    }
                }
            },
            {
                "name": "wordpress_performance_check",
                "description": "Analyze WordPress performance metrics",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            }
        ]
    
    def save_mcp_config(self, config_path: str = MCP_CONFIG_PATH):
        """Save MCP configuration to file"""
        config = self.generate_mcp_config()
        
        # Create directory if it doesn't exist
        os.makedirs(os.path.dirname(config_path), exist_ok=True)
        
        # Load existing config if it exists
        existing_config = {}
        if os.path.exists(config_path):
            with open(config_path, 'r') as f:
                existing_config = json.load(f)
        
        # Merge configurations
        if 'mcpServers' not in existing_config:
            existing_config['mcpServers'] = {}
        
        existing_config['mcpServers']['wordpress'] = config['mcpServers']['wordpress']
        
        # Save merged config
        with open(config_path, 'w') as f:
            json.dump(existing_config, f, indent=2)
        
        print(f"✓ MCP configuration saved to: {config_path}")
        return config_path


def main():
    """Main function to set up MCP integration"""
    print("WP LLM Connector - MCP Integration Setup")
    print("=" * 50)
    
    # Validate configuration
    if API_KEY == "wpllm_your_api_key_here":
        print("❌ Error: Please set your API key in the script")
        print("   Generate one in WordPress: Settings > LLM Connector")
        return
    
    if WORDPRESS_URL == "https://yoursite.com":
        print("❌ Error: Please set your WordPress URL in the script")
        return
    
    # Initialize MCP server
    print(f"\n📝 WordPress URL: {WORDPRESS_URL}")
    print(f"🔑 API Key: {API_KEY[:20]}...")
    
    mcp_server = WordPressMCPServer(WORDPRESS_URL, API_KEY)
    
    # Generate and save configuration
    print("\n⚙️  Generating MCP configuration...")
    config_path = mcp_server.save_mcp_config()
    
    # Display configuration
    print("\n📋 Generated MCP Configuration:")
    print("-" * 50)
    config = mcp_server.generate_mcp_config()
    print(json.dumps(config, indent=2))
    
    print("\n✅ Setup complete!")
    print("\nNext steps:")
    print("1. Ensure you've set the correct WordPress URL and API key (from WordPress admin) in this script")
    print("2. Restart Claude Code to load the new configuration")
    print("3. In Claude Code, you can now use WordPress diagnostics tools")
    print("4. Try: 'Run a WordPress security audit on my site'")
    print("5. Or: 'Check the system status of my WordPress site'")
    
    print("\n📚 Available tools:")
    for tool in mcp_server._generate_tools():
        print(f"   - {tool['name']}: {tool['description']}")


if __name__ == "__main__":
    main()
