<?php
namespace WP_LLM_Connector\Admin;

class Admin_Interface {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        add_options_page(
            'WP LLM Connector Settings',
            'LLM Connector',
            'manage_options',
            'wp-llm-connector',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('wp_llm_connector_settings_group', 'wp_llm_connector_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['read_only_mode'] = isset($input['read_only_mode']) ? (bool) $input['read_only_mode'] : true;
        $sanitized['rate_limit'] = isset($input['rate_limit']) ? absint($input['rate_limit']) : 60;
        $sanitized['log_requests'] = isset($input['log_requests']) ? (bool) $input['log_requests'] : true;
        
        // Handle allowed endpoints
        $sanitized['allowed_endpoints'] = isset($input['allowed_endpoints']) && is_array($input['allowed_endpoints']) 
            ? array_map('sanitize_text_field', $input['allowed_endpoints']) 
            : [];

        // Preserve existing API keys (managed separately)
        $current_settings = get_option('wp_llm_connector_settings', []);
        $sanitized['api_keys'] = $current_settings['api_keys'] ?? [];

        return $sanitized;
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_wp-llm-connector') {
            return;
        }

        wp_enqueue_style(
            'wp-llm-connector-admin',
            WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WP_LLM_CONNECTOR_VERSION
        );

        wp_enqueue_script(
            'wp-llm-connector-admin',
            WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WP_LLM_CONNECTOR_VERSION,
            true
        );

        wp_localize_script('wp-llm-connector-admin', 'wpLlmConnector', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_llm_connector_ajax')
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle API key actions
        $this->handle_api_key_actions();

        $settings = get_option('wp_llm_connector_settings', []);
        $available_endpoints = [
            'site_info' => 'Site Information',
            'plugin_list' => 'Plugin List',
            'theme_list' => 'Theme List',
            'user_count' => 'User Count',
            'post_stats' => 'Post Statistics',
            'system_status' => 'System Status'
        ];

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('wp_llm_connector_messages'); ?>

            <div class="wp-llm-connector-admin-container">
                <div class="wp-llm-connector-main-settings">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wp_llm_connector_settings_group');
                        ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Connector</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wp_llm_connector_settings[enabled]" value="1" 
                                            <?php checked($settings['enabled'] ?? false, true); ?>>
                                        Allow LLM connections to this site
                                    </label>
                                    <p class="description">Master switch to enable/disable all API access</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Read-Only Mode</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wp_llm_connector_settings[read_only_mode]" value="1" 
                                            <?php checked($settings['read_only_mode'] ?? true, true); ?>>
                                        Enforce read-only access (recommended)
                                    </label>
                                    <p class="description">When enabled, LLMs can only read data, not modify anything</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Rate Limit</th>
                                <td>
                                    <input type="number" name="wp_llm_connector_settings[rate_limit]" 
                                        value="<?php echo esc_attr($settings['rate_limit'] ?? 60); ?>" 
                                        min="1" max="1000" class="small-text">
                                    <span>requests per hour per API key</span>
                                    <p class="description">Limit requests to prevent abuse</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Logging</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wp_llm_connector_settings[log_requests]" value="1" 
                                            <?php checked($settings['log_requests'] ?? true, true); ?>>
                                        Log all API requests
                                    </label>
                                    <p class="description">Keep an audit trail of all LLM access</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Allowed Endpoints</th>
                                <td>
                                    <?php foreach ($available_endpoints as $endpoint => $label): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" 
                                                name="wp_llm_connector_settings[allowed_endpoints][]" 
                                                value="<?php echo esc_attr($endpoint); ?>"
                                                <?php checked(in_array($endpoint, $settings['allowed_endpoints'] ?? [], true)); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="description">Select which data endpoints LLMs can access</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>

                <div class="wp-llm-connector-api-keys">
                    <h2>API Keys</h2>
                    
                    <?php $this->render_api_keys_section($settings); ?>
                </div>

                <div class="wp-llm-connector-info">
                    <h2>Connection Information</h2>
                    <div class="info-box">
                        <h3>API Endpoint</h3>
                        <code><?php echo esc_html(rest_url('wp-llm-connector/v1/')); ?></code>
                        
                        <h3>Usage Example (cURL)</h3>
                        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
curl -H "X-WP-LLM-API-Key: YOUR_API_KEY" \
     <?php echo esc_html(rest_url('wp-llm-connector/v1/site-info')); ?>
                        </pre>

                        <h3>Available Endpoints</h3>
                        <ul>
                            <li><code>/health</code> - Health check (no auth required)</li>
                            <li><code>/site-info</code> - Basic site information</li>
                            <li><code>/plugins</code> - List all plugins</li>
                            <li><code>/themes</code> - List all themes</li>
                            <li><code>/system-status</code> - System health and configuration</li>
                            <li><code>/user-count</code> - User statistics</li>
                            <li><code>/post-stats</code> - Content statistics</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_api_keys_section($settings) {
        $api_keys = $settings['api_keys'] ?? [];
        ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Key Name</th>
                        <th>API Key</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($api_keys)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                No API keys generated yet. Create one below to get started.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($api_keys as $key_id => $key_data): ?>
                            <tr>
                                <td><?php echo esc_html($key_data['name'] ?? 'Unnamed'); ?></td>
                                <td>
                                    <div class="api-key-container">
                                        <code class="api-key-display" data-key-id="<?php echo esc_attr($key_id); ?>">
                                            <span class="api-key-hidden">
                                                <?php echo esc_html(substr($key_data['key'], 0, 20) . '...'); ?>
                                            </span>
                                            <span class="api-key-full" style="display: none;">
                                                <?php echo esc_html($key_data['key']); ?>
                                            </span>
                                        </code>
                                        <button type="button" 
                                            class="button button-small reveal-api-key" 
                                            data-key-id="<?php echo esc_attr($key_id); ?>"
                                            title="Click to reveal full API key">
                                            <span class="dashicons dashicons-visibility"></span> Reveal
                                        </button>
                                        <button type="button" 
                                            class="button button-small copy-api-key" 
                                            data-key="<?php echo esc_attr($key_data['key']); ?>"
                                            title="Copy full API key to clipboard">
                                            <span class="dashicons dashicons-clipboard"></span> Copy
                                        </button>
                                    </div>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', $key_data['created'] ?? time())); ?></td>
                                <td>
                                    <?php if ($key_data['active'] ?? true): ?>
                                        <span class="status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="submit" name="revoke_key" value="<?php echo esc_attr($key_id); ?>" 
                                        class="button button-small button-link-delete"
                                        onclick="return confirm('Are you sure you want to revoke this API key?');">
                                        Revoke
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <h3>Generate New API Key</h3>
                <input type="text" name="key_name" placeholder="Key name (e.g., Claude Production)" 
                    class="regular-text" required>
                <button type="submit" name="generate_key" class="button button-primary">
                    Generate API Key
                </button>
            </div>
        </form>

        <?php
    }

    private function handle_api_key_actions() {
        // Generate new key
        if (isset($_POST['generate_key']) && check_admin_referer('wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce')) {
            $key_name = sanitize_text_field($_POST['key_name'] ?? 'Unnamed');
            $api_key = \WP_LLM_Connector\Security\Security_Manager::generate_api_key();
            
            $settings = get_option('wp_llm_connector_settings', []);
            $settings['api_keys'] = $settings['api_keys'] ?? [];
            
            $key_id = uniqid('key_', true);
            $settings['api_keys'][$key_id] = [
                'name' => $key_name,
                'key' => $api_key,
                'created' => time(),
                'active' => true
            ];
            
            update_option('wp_llm_connector_settings', $settings);
            
            add_settings_error(
                'wp_llm_connector_messages',
                'key_generated',
                sprintf('API Key generated successfully: <code>%s</code><br><strong>Save this key now - it will be partially hidden after you leave this page.</strong>', $api_key),
                'success'
            );
        }

        // Revoke key
        if (isset($_POST['revoke_key']) && check_admin_referer('wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce')) {
            $key_id = sanitize_text_field($_POST['revoke_key']);
            
            $settings = get_option('wp_llm_connector_settings', []);
            if (isset($settings['api_keys'][$key_id])) {
                unset($settings['api_keys'][$key_id]);
                update_option('wp_llm_connector_settings', $settings);
                
                add_settings_error(
                    'wp_llm_connector_messages',
                    'key_revoked',
                    'API Key revoked successfully.',
                    'success'
                );
            }
        }
    }
}
