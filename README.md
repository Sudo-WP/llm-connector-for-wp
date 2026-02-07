# LLM Connector for WordPress

**Version:** 0.1.0 (MVP)  
**Author:** SudoWP.com  
**License:** GPL v2 or later

A secure WordPress plugin that enables LLM agents (like Claude Code) to connect to your WordPress site in read-only mode for diagnostics, troubleshooting, and administration.

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
- **Read-Only Mode**: Enforced by default - LLMs can only read, never modify
- **Rate Limiting**: Configurable request limits per API key
- **Audit Logging**: Full request logging for security monitoring
- **IP Tracking**: Monitor where requests originate
- **Granular Permissions**: Enable only the endpoints you need

### Extensible Architecture
- **Provider-Agnostic**: Built to support multiple LLM providers
- **Modular Design**: Easy to extend with new endpoints
- **Standard REST API**: Uses WordPress REST API standards
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
2. Keep "Read-Only Mode" enabled (recommended)
3. Select which endpoints to allow
4. Save settings

### 2. Generate an API Key

In this step, you'll create an API key that LLM services (like Claude) will use to authenticate when connecting to your WordPress site.

1. Scroll to the "API Keys" section
2. Enter a descriptive name for your key (e.g., "Claude Production")
3. Click "Generate API Key"
4. **Copy and save the key immediately** - it will be partially hidden after you leave the page
5. You'll use this key in step 3 to configure your LLM client

### 3. Test the Connection

Use the API key you generated in step 2 to test the connection with cURL:

```bash
curl -H "X-WP-LLM-API-Key: wpllm_your_api_key_here" \
     https://yoursite.com/wp-json/wp-llm-connector/v1/site-info
```

Replace `wpllm_your_api_key_here` with the actual API key you copied from WordPress.

## Available Endpoints

All endpoints require authentication via the `X-WP-LLM-API-Key` header.

### Health Check (No Auth)
```
GET /wp-json/wp-llm-connector/v1/health
```
Returns connector status and version.

### Site Information
```
GET /wp-json/wp-llm-connector/v1/site-info
```
Returns basic site configuration, WordPress version, PHP version, timezone, etc.

### Plugin List
```
GET /wp-json/wp-llm-connector/v1/plugins
```
Returns all installed plugins with version, status, and author information.

### Theme List
```
GET /wp-json/wp-llm-connector/v1/themes
```
Returns all installed themes with version and active status.

### System Status
```
GET /wp-json/wp-llm-connector/v1/system-status
```
Returns comprehensive system information: server configuration, PHP settings, database stats, filesystem permissions.

### User Count
```
GET /wp-json/wp-llm-connector/v1/user-count
```
Returns user statistics by role.

### Post Statistics
```
GET /wp-json/wp-llm-connector/v1/post-stats
```
Returns content statistics for all post types.

## Connecting to Claude Code

To use this plugin with Claude Code, you need to configure Claude to use the API key you generated in WordPress.

**Important:** You do NOT need to generate an API key in Claude or Anthropic. The API key is generated in your WordPress admin and then provided to Claude for authentication.

### Option 1: Manual Configuration

Add to your Claude Code MCP settings, using the API key you generated in WordPress (step 2):

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://yoursite.com/wp-json/wp-llm-connector/v1/",
      "transport": "http",
      "headers": {
        "X-WP-LLM-API-Key": "wpllm_your_api_key_here"
      },
      "description": "WordPress site diagnostics"
    }
  }
}
```

Replace `wpllm_your_api_key_here` with the actual API key from your WordPress LLM Connector settings.

### Option 2: Provider Integration (Future)

The plugin includes a provider system that will eventually auto-generate MCP configurations for supported LLM services.

## Security Considerations

### Default Security Posture

- ✅ Read-only mode enforced by default
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
- Private post content (unless you build a custom endpoint)
- Database credentials
- Server passwords
- File contents

## Architecture

### Directory Structure

```
wp-llm-connector/
├── wp-llm-connector.php          # Main plugin file
├── includes/
│   ├── Core/
│   │   ├── Plugin.php            # Main orchestrator
│   │   ├── Activator.php         # Activation logic
│   │   └── Deactivator.php       # Deactivation logic
│   ├── API/
│   │   └── API_Handler.php       # REST API endpoints
│   ├── Security/
│   │   └── Security_Manager.php  # Auth & rate limiting
│   ├── Admin/
│   │   └── Admin_Interface.php   # Settings page
│   └── Providers/
│       ├── LLM_Provider_Interface.php  # Provider contract
│       └── Claude_Provider.php         # Claude implementation
├── assets/
│   ├── css/
│   │   └── admin.css             # Admin styling
│   └── js/
│       └── admin.js              # Admin JavaScript
└── README.md
```

### Design Patterns

- **Singleton Pattern**: Core plugin instance
- **Dependency Injection**: Services passed to constructors
- **Interface Segregation**: Provider interfaces for extensibility
- **PSR-4 Autoloading**: Modern PHP class loading

## Extending the Plugin

### Adding New Endpoints

1. Create a new method in `includes/API/API_Handler.php`
2. Register the route in `register_routes()`
3. Add the endpoint to the default allowed list in `Activator.php`

Example:

```php
// Register route
register_rest_route($this->namespace, '/custom-data', [
    'methods' => 'GET',
    'callback' => [$this, 'get_custom_data'],
    'permission_callback' => [$this, 'check_permissions']
]);

// Implement callback
public function get_custom_data(\WP_REST_Request $request) {
    $data = [
        // Your custom data here
    ];
    
    $this->log_success($request, 'custom_data');
    return rest_ensure_response($data);
}
```

### Adding New Providers

1. Create a new class in `includes/Providers/`
2. Implement `LLM_Provider_Interface`
3. Register in the provider manager (future enhancement)

## Database Schema

### Audit Log Table

```sql
wp_llm_connector_audit_log
├── id (bigint, primary key)
├── timestamp (datetime)
├── api_key_hash (varchar 64)
├── endpoint (varchar 255)
├── request_data (text)
├── response_code (int)
├── ip_address (varchar 45)
└── user_agent (text)
```

### Options

- `wp_llm_connector_settings`: Main plugin configuration
- `wp_llm_connector_activated`: Activation timestamp

## Roadmap

### Phase 1 (Current - MVP)
- ✅ Read-only REST API endpoints
- ✅ API key authentication
- ✅ Rate limiting
- ✅ Audit logging
- ✅ Admin interface

### Phase 2 (Planned)
- [ ] Provider-specific configurations in UI
- [ ] Auto-generated MCP configurations
- [ ] Webhook support for proactive alerts
- [ ] Custom endpoint builder (GUI)
- [ ] Advanced filtering and search in audit logs

### Phase 3 (Future)
- [ ] Write operations (with explicit user confirmation)
- [ ] Multi-tenant support for agencies
- [ ] Integration with popular security plugins
- [ ] Real-time notifications via WebSockets
- [ ] Dashboard widget for quick stats

## Contributing

This is an MVP version. Contributions, feedback, and suggestions are welcome!

### Development Setup

1. Clone the repository
2. Install on a local WordPress instance
3. Enable `WP_DEBUG` and `WP_DEBUG_LOG`
4. Make your changes
5. Test thoroughly

## License

GPL v2 or later

## Links

- **Author**: [AmIHacked.com](https://sudowp.com)
- **Development**: [SudoWP.com](https://sudowp.com)
- **Support**: [WPRepublic.com](https://sudowp.com)

## Disclaimer

This plugin is designed for site diagnostics and administration. While it includes robust security features, always:
- Use strong, unique API keys
- Monitor access logs regularly
- Keep WordPress and all plugins updated
- Maintain regular backups
- Use HTTPS/SSL

The authors are not responsible for misuse or unauthorized access resulting from improper configuration.

## Support

For issues, questions, or feature requests:
1. Check the documentation above
2. Review the audit logs for errors
3. Contact via your preferred support channel

---

**Built with Security and Performance in mind**
