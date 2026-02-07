# Developer Guide

## Overview

The WP LLM Connector is built with extensibility in mind. This guide will help you understand the architecture and add new features.

## Architecture

### Directory Structure

```
wp-llm-connector/
├── wp-llm-connector.php          # Plugin bootstrap
├── uninstall.php                 # Cleanup on deletion
├── includes/
│   ├── Core/                     # Core plugin logic
│   ├── API/                      # REST API handlers
│   ├── Security/                 # Authentication & authorization
│   ├── Admin/                    # Admin interface
│   └── Providers/                # LLM provider integrations
└── assets/
    ├── css/                      # Stylesheets
    └── js/                       # JavaScript
```

### Class Structure

```
WP_LLM_Connector\
├── Core\
│   ├── Plugin (Singleton)
│   ├── Activator
│   └── Deactivator
├── API\
│   └── API_Handler
├── Security\
│   └── Security_Manager
├── Admin\
│   └── Admin_Interface
└── Providers\
    ├── LLM_Provider_Interface
    └── Claude_Provider
```

### Data Flow

```
1. Request → WordPress REST API
2. REST API → API_Handler::check_permissions()
3. Security_Manager → Validate API key
4. Security_Manager → Check rate limit
5. API_Handler → Process endpoint
6. Security_Manager → Log request
7. Response → Client
```

## Adding Custom Endpoints

### Step 1: Register the Route

In `includes/API/API_Handler.php`, add to `register_routes()`:

```php
register_rest_route($this->namespace, '/your-endpoint', [
    'methods' => 'GET',
    'callback' => [$this, 'get_your_data'],
    'permission_callback' => [$this, 'check_permissions'],
    'args' => [
        'param' => [
            'required' => false,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]
    ]
]);
```

### Step 2: Implement the Callback

```php
/**
 * Get your custom data
 */
public function get_your_data(\WP_REST_Request $request) {
    // Get parameters
    $param = $request->get_param('param');
    
    // Process data
    $data = [
        'result' => 'your data here',
        'param' => $param
    ];
    
    // Log success
    $this->log_success($request, 'your_endpoint');
    
    // Return response
    return rest_ensure_response($data);
}
```

### Step 3: Add to Default Allowed Endpoints

In `includes/Core/Activator.php`, add your endpoint to the allowed list:

```php
'allowed_endpoints' => [
    'site_info',
    'plugin_list',
    // ... existing endpoints
    'your_endpoint'  // Add yours here
],
```

### Step 4: Add to Admin Interface

In `includes/Admin/Admin_Interface.php`, update the `$available_endpoints` array:

```php
$available_endpoints = [
    'site_info' => 'Site Information',
    // ... existing endpoints
    'your_endpoint' => 'Your Endpoint Description'
];
```

## Adding POST Endpoints (Write Operations)

### Security Considerations

Writing data requires extra security:

1. **Double Authentication**: API key + WordPress nonce
2. **Capability Checks**: Verify user permissions
3. **Data Validation**: Sanitize all inputs
4. **Read-Only Check**: Respect read-only mode setting
5. **Audit Logging**: Log all write operations

### Example: Creating a Post

```php
public function register_routes() {
    register_rest_route($this->namespace, '/create-post', [
        'methods' => 'POST',
        'callback' => [$this, 'create_post'],
        'permission_callback' => [$this, 'check_write_permissions'],
        'args' => [
            'title' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'content' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post'
            ]
        ]
    ]);
}

public function check_write_permissions(\WP_REST_Request $request) {
    // First check API key (same as read endpoints)
    $check = $this->check_permissions($request);
    if (is_wp_error($check)) {
        return $check;
    }
    
    // Check if read-only mode is enabled
    if ($this->security->is_read_only_mode()) {
        return new \WP_Error(
            'read_only_mode',
            'Write operations are disabled in read-only mode',
            ['status' => 403]
        );
    }
    
    return true;
}

public function create_post(\WP_REST_Request $request) {
    $post_data = [
        'post_title' => $request->get_param('title'),
        'post_content' => $request->get_param('content'),
        'post_status' => 'draft', // Always create as draft for safety
        'post_type' => 'post'
    ];
    
    $post_id = wp_insert_post($post_data, true);
    
    if (is_wp_error($post_id)) {
        return $post_id;
    }
    
    $this->log_success($request, 'create_post');
    
    return rest_ensure_response([
        'post_id' => $post_id,
        'edit_url' => get_edit_post_link($post_id, 'raw')
    ]);
}
```

## Creating Custom Providers

### Step 1: Implement the Interface

```php
<?php
namespace WP_LLM_Connector\Providers;

class Custom_Provider implements LLM_Provider_Interface {
    private $config;
    
    public function init(array $config) {
        $this->config = $config;
    }
    
    public function validate_credentials() {
        // Implement validation logic
        return !empty($this->config['api_key']);
    }
    
    public function get_provider_name() {
        return 'custom_llm';
    }
    
    public function get_provider_display_name() {
        return 'Custom LLM Provider';
    }
    
    public function get_config_fields() {
        return [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'required' => true
            ],
            'endpoint' => [
                'label' => 'API Endpoint',
                'type' => 'text',
                'required' => true
            ]
        ];
    }
    
    public function supports_read_only() {
        return true;
    }
    
    public function get_endpoint_mappings() {
        return [
            'site_info' => '/custom/site-info',
            // Map other endpoints
        ];
    }
}
```

### Step 2: Register the Provider

Create a provider registry (future enhancement):

```php
class Provider_Registry {
    private static $providers = [];
    
    public static function register($provider_class) {
        $provider = new $provider_class();
        self::$providers[$provider->get_provider_name()] = $provider;
    }
    
    public static function get_provider($name) {
        return self::$providers[$name] ?? null;
    }
}
```

## Database Operations

### Querying Audit Logs

```php
global $wpdb;
$table_name = $wpdb->prefix . 'llm_connector_audit_log';

// Get recent requests
$recent_logs = $wpdb->get_results(
    "SELECT * FROM {$table_name} 
     WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY timestamp DESC
     LIMIT 100"
);

// Get requests by endpoint
$endpoint_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE endpoint = %s
         ORDER BY timestamp DESC",
        'site_info'
    )
);

// Count requests by API key
$key_stats = $wpdb->get_results(
    "SELECT api_key_hash, COUNT(*) as request_count
     FROM {$table_name}
     GROUP BY api_key_hash"
);
```

### Custom Cleanup

```php
// Delete old audit logs (older than 30 days)
function cleanup_old_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'llm_connector_audit_log';
    
    $wpdb->query(
        "DELETE FROM {$table_name} 
         WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
}

// Schedule cleanup
if (!wp_next_scheduled('wp_llm_connector_cleanup_logs')) {
    wp_schedule_event(time(), 'daily', 'wp_llm_connector_cleanup_logs');
}

add_action('wp_llm_connector_cleanup_logs', 'cleanup_old_logs');
```

## Hooks and Filters

### Available Hooks

```php
// Before API request is processed
do_action('wp_llm_connector_before_request', $endpoint, $request);

// After API request is processed
do_action('wp_llm_connector_after_request', $endpoint, $request, $response);

// Before API key validation
do_action('wp_llm_connector_before_auth', $api_key);

// After successful authentication
do_action('wp_llm_connector_after_auth', $api_key_data);
```

### Available Filters

```php
// Modify response data
$data = apply_filters('wp_llm_connector_response_data', $data, $endpoint);

// Modify rate limit
$rate_limit = apply_filters('wp_llm_connector_rate_limit', 60, $api_key_hash);

// Modify allowed endpoints
$endpoints = apply_filters('wp_llm_connector_allowed_endpoints', $endpoints);
```

### Using Hooks

```php
// Example: Log all requests to custom table
add_action('wp_llm_connector_after_request', function($endpoint, $request, $response) {
    error_log("LLM Request: {$endpoint} - " . $response->get_status());
});

// Example: Increase rate limit for specific API key
add_filter('wp_llm_connector_rate_limit', function($limit, $key_hash) {
    if ($key_hash === 'specific_key_hash') {
        return 200; // Higher limit
    }
    return $limit;
}, 10, 2);
```

## Testing

### Unit Testing Setup

```php
// tests/test-api-handler.php
class Test_API_Handler extends WP_UnitTestCase {
    
    public function setUp() {
        parent::setUp();
        $this->api = new \WP_LLM_Connector\API\API_Handler();
    }
    
    public function test_site_info_endpoint() {
        $request = new \WP_REST_Request('GET', '/wp-llm-connector/v1/site-info');
        $response = $this->api->get_site_info($request);
        
        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('site_name', $response->get_data());
    }
}
```

### Manual Testing

```bash
# Test health endpoint
curl https://yoursite.local/wp-json/wp-llm-connector/v1/health

# Test with API key
curl -H "X-WP-LLM-API-Key: wpllm_test_key" \
     https://yoursite.local/wp-json/wp-llm-connector/v1/site-info

# Test rate limiting (make 61 requests)
for i in {1..61}; do
    curl -H "X-WP-LLM-API-Key: wpllm_test_key" \
         https://yoursite.local/wp-json/wp-llm-connector/v1/site-info
done
```

## Performance Optimization

### Caching

```php
public function get_plugins(\WP_REST_Request $request) {
    // Cache for 5 minutes
    $cache_key = 'wp_llm_connector_plugins';
    $plugins = get_transient($cache_key);
    
    if (false === $plugins) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        // Process plugins...
        $plugins = [/* processed data */];
        
        set_transient($cache_key, $plugins, 5 * MINUTE_IN_SECONDS);
    }
    
    return rest_ensure_response($plugins);
}
```

### Database Indexing

Already included in activation:
- Index on `timestamp` for date queries
- Index on `api_key_hash` for key-specific queries

## Security Best Practices

### Input Validation

```php
// Always sanitize inputs
$input = sanitize_text_field($_POST['input']);

// Validate types
if (!is_numeric($id)) {
    return new \WP_Error('invalid_id', 'Invalid ID');
}

// Use prepared statements
$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
```

### Output Escaping

```php
// In admin templates
echo esc_html($data);
echo esc_attr($attribute);
echo esc_url($url);

// In API responses (already handled by REST API)
rest_ensure_response($data);
```

### Capability Checks

```php
if (!current_user_can('manage_options')) {
    return new \WP_Error('forbidden', 'Insufficient permissions');
}
```

## Debugging

### Enable Debug Logging

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// In plugin code
error_log('WP LLM Connector: ' . print_r($data, true));
```

### View Audit Logs

```sql
-- View recent requests
SELECT * FROM wp_llm_connector_audit_log 
ORDER BY timestamp DESC 
LIMIT 50;

-- Count by endpoint
SELECT endpoint, COUNT(*) as count 
FROM wp_llm_connector_audit_log 
GROUP BY endpoint;

-- Failed requests
SELECT * FROM wp_llm_connector_audit_log 
WHERE response_code >= 400 
ORDER BY timestamp DESC;
```

## Deployment

### Production Checklist

- [ ] Enable read-only mode
- [ ] Set appropriate rate limits
- [ ] Enable request logging
- [ ] Use strong API keys
- [ ] Enable HTTPS/SSL
- [ ] Test all endpoints
- [ ] Review security settings
- [ ] Set up log cleanup cron
- [ ] Document API keys securely
- [ ] Train users on security

### Staging to Production

1. Test on staging first
2. Export settings (future feature)
3. Generate new API keys in production (these keys will be used by LLM services to authenticate)
4. Update MCP configurations with the new WordPress API keys
5. Monitor logs for first 24 hours
6. Verify rate limiting works

---

**Happy Coding!**

For questions or contributions, see the main README.md file.
