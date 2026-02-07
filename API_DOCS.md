# API Documentation

## Base URL

All API endpoints are relative to your WordPress REST API base:

```
https://yoursite.com/wp-json/wp-llm-connector/v1/
```

## Authentication

All endpoints (except `/health`) require authentication via API key.

**Important:** The API key is generated in your WordPress admin (Settings > LLM Connector) and then used by LLM services (like Claude, GPT) to authenticate when making requests to your WordPress site. You do NOT need to create this key in Claude or other LLM provider services.

### Header Format

```
X-WP-LLM-API-Key: wpllm_your_64_character_api_key_here
```

Use the API key you generated in WordPress admin in the `X-WP-LLM-API-Key` header for all authenticated requests.

### Example Request

```bash
curl -H "X-WP-LLM-API-Key: wpllm_abc123..." \
     https://yoursite.com/wp-json/wp-llm-connector/v1/site-info
```

## Rate Limiting

- Default: 60 requests per hour per API key
- Configurable via admin settings
- Rate limit is tracked per API key using transients
- Exceeding limits returns `429 Too Many Requests`

## Error Responses

### 401 Unauthorized
Missing or invalid API key.

```json
{
  "code": "invalid_api_key",
  "message": "Invalid API key",
  "data": {
    "status": 401
  }
}
```

### 429 Too Many Requests
Rate limit exceeded.

```json
{
  "code": "rate_limit_exceeded",
  "message": "Rate limit exceeded",
  "data": {
    "status": 429
  }
}
```

---

## Endpoints

### Health Check

Check connector status and version.

**Endpoint:** `GET /health`  
**Authentication:** None required

**Response:**
```json
{
  "status": "ok",
  "version": "0.1.0",
  "enabled": true,
  "read_only": true,
  "timestamp": "2025-02-07 10:30:00"
}
```

---

### Site Information

Get basic WordPress site information.

**Endpoint:** `GET /site-info`  
**Authentication:** Required

**Response:**
```json
{
  "site_name": "My WordPress Site",
  "site_url": "https://example.com",
  "home_url": "https://example.com",
  "wp_version": "6.4.2",
  "php_version": "8.1.12",
  "is_multisite": false,
  "language": "en-US",
  "charset": "UTF-8",
  "timezone": "America/New_York",
  "date_format": "F j, Y",
  "time_format": "g:i a"
}
```

**Use Cases:**
- Verify site configuration
- Check WordPress and PHP versions
- Determine timezone for log analysis
- Confirm site URLs for troubleshooting

---

### Plugin List

Get all installed plugins with status information.

**Endpoint:** `GET /plugins`  
**Authentication:** Required

**Response:**
```json
[
  {
    "name": "WooCommerce",
    "version": "8.4.0",
    "author": "Automattic",
    "description": "An open-source eCommerce solution...",
    "active": true,
    "network_active": false
  },
  {
    "name": "Yoast SEO",
    "version": "21.7",
    "author": "Team Yoast",
    "description": "Improve your WordPress SEO...",
    "active": true,
    "network_active": false
  }
]
```

**Use Cases:**
- Diagnose plugin conflicts
- Check for outdated plugins
- Identify security vulnerabilities
- Audit plugin inventory

---

### Theme List

Get all installed themes with active status.

**Endpoint:** `GET /themes`  
**Authentication:** Required

**Response:**
```json
[
  {
    "name": "Twenty Twenty-Four",
    "version": "1.0",
    "author": "WordPress.org",
    "description": "A default WordPress theme...",
    "active": true
  },
  {
    "name": "Astra",
    "version": "4.5.2",
    "author": "Brainstorm Force",
    "description": "Fast, lightweight theme...",
    "active": false
  }
]
```

**Use Cases:**
- Identify active theme
- Check theme versions
- Audit theme inventory
- Troubleshoot theme conflicts

---

### System Status

Get comprehensive system diagnostics.

**Endpoint:** `GET /system-status`  
**Authentication:** Required

**Response:**
```json
{
  "server": {
    "software": "nginx/1.24.0",
    "php_version": "8.1.12",
    "mysql_version": "8.0.35",
    "max_execution_time": "300",
    "memory_limit": "256M",
    "post_max_size": "64M",
    "upload_max_filesize": "64M"
  },
  "wordpress": {
    "version": "6.4.2",
    "multisite": false,
    "debug_mode": false,
    "memory_limit": "256M",
    "max_memory_limit": "256M"
  },
  "database": {
    "tables_count": 47,
    "database_size": "45.2 MB"
  },
  "filesystem": {
    "uploads_writable": true,
    "content_writable": true
  }
}
```

**Use Cases:**
- Diagnose performance issues
- Check server configuration
- Verify PHP settings
- Identify memory problems
- Confirm filesystem permissions

---

### User Count

Get user statistics by role.

**Endpoint:** `GET /user-count`  
**Authentication:** Required

**Response:**
```json
{
  "total": 127,
  "by_role": {
    "administrator": 2,
    "editor": 5,
    "author": 12,
    "contributor": 8,
    "subscriber": 100
  }
}
```

**Use Cases:**
- Audit user accounts
- Identify privilege escalation
- Plan user role restructuring
- Generate reports

**Security Note:** Does not expose user details, only counts.

---

### Post Statistics

Get content statistics for all post types.

**Endpoint:** `GET /post-stats`  
**Authentication:** Required

**Response:**
```json
{
  "post": {
    "label": "Posts",
    "publish": 156,
    "draft": 23,
    "pending": 5,
    "private": 2,
    "trash": 8
  },
  "page": {
    "label": "Pages",
    "publish": 42,
    "draft": 7,
    "pending": 1,
    "private": 0,
    "trash": 3
  },
  "product": {
    "label": "Products",
    "publish": 89,
    "draft": 12,
    "pending": 0,
    "private": 0,
    "trash": 5
  }
}
```

**Use Cases:**
- Content audit
- Identify orphaned drafts
- Clean up trash
- Monitor publishing workflow
- Generate content reports

---

## Complete Request Example

### Using cURL

```bash
#!/bin/bash

API_KEY="wpllm_your_api_key_here"
BASE_URL="https://yoursite.com/wp-json/wp-llm-connector/v1"

# Health check (no auth)
curl "${BASE_URL}/health"

# Site info
curl -H "X-WP-LLM-API-Key: ${API_KEY}" \
     "${BASE_URL}/site-info"

# System status
curl -H "X-WP-LLM-API-Key: ${API_KEY}" \
     "${BASE_URL}/system-status"
```

### Using Python

```python
import requests

API_KEY = "wpllm_your_api_key_here"
BASE_URL = "https://yoursite.com/wp-json/wp-llm-connector/v1"

headers = {
    "X-WP-LLM-API-Key": API_KEY
}

# Get site info
response = requests.get(f"{BASE_URL}/site-info", headers=headers)
if response.status_code == 200:
    site_info = response.json()
    print(f"Site: {site_info['site_name']}")
    print(f"WP Version: {site_info['wp_version']}")
else:
    print(f"Error: {response.status_code}")
```

### Using JavaScript (Node.js)

```javascript
const axios = require('axios');

const API_KEY = 'wpllm_your_api_key_here';
const BASE_URL = 'https://yoursite.com/wp-json/wp-llm-connector/v1';

async function getSiteInfo() {
  try {
    const response = await axios.get(`${BASE_URL}/site-info`, {
      headers: {
        'X-WP-LLM-API-Key': API_KEY
      }
    });
    
    console.log('Site:', response.data.site_name);
    console.log('WP Version:', response.data.wp_version);
  } catch (error) {
    console.error('Error:', error.response?.status, error.response?.data);
  }
}

getSiteInfo();
```

---

## MCP Configuration

For use with Claude Code or other MCP-compatible tools:

**Important:** Replace `wpllm_your_api_key_here` with the actual API key you generated in WordPress (Settings > LLM Connector). This is NOT a Claude/Anthropic API key.

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://yoursite.com/wp-json/wp-llm-connector/v1/",
      "transport": "http",
      "headers": {
        "X-WP-LLM-API-Key": "wpllm_your_api_key_here"
      },
      "description": "WordPress site diagnostics and administration",
      "endpoints": {
        "site_info": "/site-info",
        "plugins": "/plugins",
        "themes": "/themes",
        "system_status": "/system-status",
        "user_count": "/user-count",
        "post_stats": "/post-stats"
      }
    }
  }
}
```

---

## Best Practices

### Security
1. Always use HTTPS in production
2. Rotate API keys regularly (every 90 days recommended)
3. Use separate API keys for different environments
4. Monitor audit logs for suspicious activity
5. Enable only the endpoints you need

### Performance
1. Cache responses when appropriate
2. Respect rate limits
3. Use batch requests when possible (future feature)
4. Monitor database size of audit logs

### Integration
1. Handle rate limit errors gracefully
2. Implement exponential backoff for retries
3. Validate API responses before using data
4. Log all API interactions for debugging

---

## Troubleshooting

### 401 Errors
- Verify API key is correct
- Check that connector is enabled in settings
- Ensure API key hasn't been revoked

### 429 Errors
- Wait for rate limit to reset (1 hour)
- Reduce request frequency
- Request rate limit increase in settings

### Empty Responses
- Check endpoint permissions in admin
- Verify endpoint is enabled
- Review audit logs for errors

### Connection Refused
- Verify WordPress REST API is working: `/wp-json/`
- Check server firewall rules
- Confirm mod_rewrite is enabled

---

## Support

For API issues or questions:
1. Review this documentation
2. Check plugin settings in WordPress admin
3. Review audit logs in database
4. Contact support with specific error messages

---

**API Version:** v1  
**Plugin Version:** 0.1.0  
**Last Updated:** 2025-02-07
