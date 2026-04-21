<?php
namespace WP_LLM_Connector\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Interface {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_api_key_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_log_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_provider_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_access_log_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_wp_llm_connector_test_connection', array( $this, 'ajax_test_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_LLM_CONNECTOR_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'WP LLM Connector Settings', 'wp-llm-connector' ),
			__( 'LLM Connector', 'wp-llm-connector' ),
			'manage_options',
			'wp-llm-connector',
			array( $this, 'render_settings_page' )
		);

		// Access Log appears as its own item under Settings, sibling to the
		// main page. Keeping it distinct from the settings page avoids the
		// awkwardness of tabbing a WP_List_Table inside an options form.
		add_submenu_page(
			'options-general.php',
			__( 'LLM Connector — Access Log', 'wp-llm-connector' ),
			__( 'LLM Connector Log', 'wp-llm-connector' ),
			'manage_options',
			'wp-llm-connector-access-log',
			array( $this, 'render_access_log_page' )
		);
	}

	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=wp-llm-connector' ),
			__( 'Settings', 'wp-llm-connector' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function register_settings() {
		register_setting(
			'wp_llm_connector_settings_group',
			'wp_llm_connector_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled']        = isset( $input['enabled'] ) ? (bool) $input['enabled'] : false;
		$sanitized['read_only_mode'] = isset( $input['read_only_mode'] ) ? (bool) $input['read_only_mode'] : true;
		$sanitized['rate_limit']     = isset( $input['rate_limit'] ) ? absint( $input['rate_limit'] ) : 60;
		$sanitized['log_requests']   = isset( $input['log_requests'] ) ? (bool) $input['log_requests'] : true;
		$sanitized['preserve_settings_on_uninstall'] = isset( $input['preserve_settings_on_uninstall'] ) ? (bool) $input['preserve_settings_on_uninstall'] : false;

		// Clamp rate limit to valid range.
		$sanitized['rate_limit'] = max( 1, min( 1000, $sanitized['rate_limit'] ) );

		// Handle allowed endpoints.
		$sanitized['allowed_endpoints'] = isset( $input['allowed_endpoints'] ) && is_array( $input['allowed_endpoints'] )
			? array_map( 'sanitize_text_field', $input['allowed_endpoints'] )
			: array();

		// Preserve existing providers config (managed by its own form).
		$current_settings       = get_option( 'wp_llm_connector_settings', array() );
		$sanitized['providers'] = $current_settings['providers'] ?? array();

		// Preserve existing API keys if not provided in input (form submissions don't include them).
		// If api_keys are in the input, use them (programmatic updates via handle_api_key_actions).
		if ( isset( $input['api_keys'] ) && is_array( $input['api_keys'] ) ) {
			// Whitelist of permitted write scopes — enforced here so a
			// malformed submission can't smuggle an unknown scope into the
			// key record and have it be honored by a future write endpoint.
			$allowed_scopes = array( 'posts', 'plugins', 'options', 'users', 'cache' );

			// Sanitize each API key entry for security.
			$api_keys = array();
			foreach ( $input['api_keys'] as $key_id => $key_data ) {
				if ( is_array( $key_data ) ) {
					$raw_scopes = isset( $key_data['write_scopes'] ) && is_array( $key_data['write_scopes'] )
						? $key_data['write_scopes']
						: array();
					$scopes     = array_values( array_intersect(
						array_map( 'sanitize_text_field', $raw_scopes ),
						$allowed_scopes
					) );

					$api_keys[ sanitize_text_field( $key_id ) ] = array(
						'name'          => isset( $key_data['name'] ) ? sanitize_text_field( $key_data['name'] ) : '',
						'key_hash'      => isset( $key_data['key_hash'] ) ? sanitize_text_field( $key_data['key_hash'] ) : '',
						'key_prefix'    => isset( $key_data['key_prefix'] ) ? sanitize_text_field( $key_data['key_prefix'] ) : '',
						'created'       => isset( $key_data['created'] ) ? absint( $key_data['created'] ) : 0,
						'active'        => isset( $key_data['active'] ) ? (bool) $key_data['active'] : true,
						// Write-tier fields. Preserve through settings round-trips; default to closed.
						'write_enabled' => isset( $key_data['write_enabled'] ) ? (bool) $key_data['write_enabled'] : false,
						'write_scopes'  => $scopes,
					);
				}
			}
			$sanitized['api_keys'] = $api_keys;
		} else {
			$sanitized['api_keys'] = $current_settings['api_keys'] ?? array();
		}

		return $sanitized;
	}

	public function enqueue_admin_assets( $hook ) {
		$allowed = array( 'settings_page_wp-llm-connector', 'settings_page_wp-llm-connector-access-log' );
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		// Enqueue Dashicons
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'wp-llm-connector-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			WP_LLM_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'wp-llm-connector-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_LLM_CONNECTOR_VERSION,
			true
		);

		wp_localize_script(
			'wp-llm-connector-admin',
			'wpLlmConnector',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_llm_connector_ajax' ),
				'newKey'  => $this->get_new_key_for_js(),
				'mcp'     => $this->get_mcp_snippet_context(),
				'i18n'    => array(
					// Copy-button labels (providers + MCP line strings, unified).
					'copyLabel'        => __( 'Copy to clipboard', 'wp-llm-connector' ),
					'copiedLabel'      => __( 'Copied to clipboard', 'wp-llm-connector' ),
					'copyText'         => __( 'Copy', 'wp-llm-connector' ),
					'copiedText'       => __( 'Copied!', 'wp-llm-connector' ),
					'copyError'        => __( 'Failed to copy to clipboard. Please select and copy the key manually.', 'wp-llm-connector' ),
					// Reveal / hide — used by the key-display toggle.
					'revealText'       => __( 'Reveal', 'wp-llm-connector' ),
					'hideText'         => __( 'Hide', 'wp-llm-connector' ),
					'revealKeyLabel'   => __( 'Reveal key', 'wp-llm-connector' ),
					'hideKeyLabel'     => __( 'Hide key', 'wp-llm-connector' ),
					'hiddenKeyTitle'   => __( 'Click Reveal to view the key', 'wp-llm-connector' ),
					'revealedKeyTitle' => __( 'Click or select to copy', 'wp-llm-connector' ),
					// "Connect Your AI Client" MCP snippet + Test Connection.
					'copyConfigText'   => __( 'Copy full config', 'wp-llm-connector' ),
					'copyConfigDone'   => __( 'Copied!', 'wp-llm-connector' ),
					'testConnected'    => __( 'Connected', 'wp-llm-connector' ),
					'testFailed'       => __( 'Failed', 'wp-llm-connector' ),
					'testTesting'      => __( 'Testing...', 'wp-llm-connector' ),
					'placeholderKey'   => __( 'YOUR_API_KEY_HERE', 'wp-llm-connector' ),
					'noFullKeyNotice'  => __( 'Full API key is only available immediately after generation. Generate a new key above to get a pre-filled snippet; otherwise the placeholder will be copied and you can paste your key in manually.', 'wp-llm-connector' ),
				),
			)
		);
	}

	/**
	 * Build the context payload passed to admin.js for rendering MCP config
	 * snippets. Only includes the real API key if one was just generated and
	 * is still available via transient; older keys are stored hashed and cannot
	 * be recovered, so the snippet falls back to a placeholder.
	 *
	 * @return array
	 */
	private function get_mcp_snippet_context() {
		$settings = get_option( 'wp_llm_connector_settings', array() );
		$api_keys = $settings['api_keys'] ?? array();
		$new_key  = $this->get_new_key_for_js();

		// Pick a key to represent in the masked snippet: prefer a freshly
		// generated one, else the first active existing key, else empty.
		$masked_prefix = '';
		foreach ( $api_keys as $key_data ) {
			if ( ! empty( $key_data['active'] ) ) {
				$masked_prefix = substr( $key_data['key_prefix'] ?? '', 0, 8 );
				break;
			}
		}
		if ( $new_key ) {
			$masked_prefix = substr( $new_key, 0, 8 );
		}

		// Slug for the MCP server identifier — stable across sessions.
		$site_slug = sanitize_title( get_bloginfo( 'name' ) );
		if ( '' === $site_slug ) {
			$site_slug = 'site';
		}

		return array(
			'restUrl'      => esc_url_raw( rest_url( 'wp-llm-connector/v1/mcp' ) ),
			'healthUrl'    => esc_url_raw( rest_url( 'wp-llm-connector/v1/health' ) ),
			'headerName'   => 'X-WP-LLM-API-Key',
			'siteSlug'     => $site_slug,
			'maskedPrefix' => $masked_prefix,
			'hasFullKey'   => (bool) $new_key,
			'fullKey'      => $new_key ? $new_key : '',
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'wp_llm_connector_settings', array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection, no data mutation.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		$available_endpoints = array(
			'site_info'     => __( 'Site Information', 'wp-llm-connector' ),
			'plugin_list'   => __( 'Plugin List', 'wp-llm-connector' ),
			'theme_list'    => __( 'Theme List', 'wp-llm-connector' ),
			'user_count'    => __( 'User Count', 'wp-llm-connector' ),
			'post_stats'    => __( 'Post Statistics', 'wp-llm-connector' ),
			'system_status' => __( 'System Status', 'wp-llm-connector' ),
		);

		// Retrieve newly generated API key details (if any) from transients so
		// the API Keys tab can highlight + expose the one-time copy button.
		$new_key_full = false;
		$new_key_id   = false;
		if ( isset( $_GET['key_generated'] ) && '1' === $_GET['key_generated'] ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_key_generated' ) ) {
				$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
				$new_key_full  = get_transient( $transient_key );
				$new_key_id    = get_transient( $transient_key . '_id' );
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Display error message if available.
			if ( isset( $_GET['error'] ) && '1' === $_GET['error'] ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_error' ) ) {
					$error_message = get_transient( 'wp_llm_connector_error' );
					if ( $error_message ) {
						delete_transient( 'wp_llm_connector_error' );
						?>
						<div class="notice notice-error is-dismissible">
							<p><?php echo esc_html( $error_message ); ?></p>
						</div>
						<?php
					}
				}
			}

			// Lightweight success notice on key generation — the actual key
			// is displayed in the API Keys tab table row with its Copy button.
			if ( $new_key_full ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'API Key generated successfully!', 'wp-llm-connector' ); ?></strong>
						<?php esc_html_e( 'Open the API Keys tab — use the Copy Full Key button next to the highlighted row. The full key is only available temporarily.', 'wp-llm-connector' ); ?>
					</p>
				</div>
				<?php
			}

			// Display log purge success message.
			if ( isset( $_GET['log_purged'] ) && '1' === $_GET['log_purged'] ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_log_purged' ) ) {
					?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Audit log purged successfully.', 'wp-llm-connector' ); ?></p>
					</div>
					<?php
				}
			}
			?>

			<?php settings_errors( 'wp_llm_connector_messages' ); ?>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'general', admin_url( 'options-general.php?page=wp-llm-connector' ) ) ); ?>"
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'wp-llm-connector' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'providers', admin_url( 'options-general.php?page=wp-llm-connector' ) ) ); ?>"
					class="nav-tab <?php echo 'providers' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'AI Providers', 'wp-llm-connector' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'api-keys', admin_url( 'options-general.php?page=wp-llm-connector' ) ) ); ?>"
					class="nav-tab <?php echo 'api-keys' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Keys', 'wp-llm-connector' ); ?>
				</a>
			</nav>

			<?php if ( 'general' === $active_tab ) : ?>

			<div class="wp-llm-connector-admin-container">
				<div class="wp-llm-connector-main-settings">
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_llm_connector_settings_group' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Connector', 'wp-llm-connector' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[enabled]" value="1"
											<?php checked( $settings['enabled'] ?? false, true ); ?>>
										<?php esc_html_e( 'Allow LLM connections to this site', 'wp-llm-connector' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Master switch to enable/disable all API access', 'wp-llm-connector' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Read-Only Mode', 'wp-llm-connector' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[read_only_mode]" value="1"
											<?php checked( $settings['read_only_mode'] ?? true, true ); ?>>
										<?php esc_html_e( 'Enforce read-only access (recommended)', 'wp-llm-connector' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'All current REST data endpoints are read-only at the route level. This toggle is an additional belt-and-braces check for future write endpoints behind the premium write tier.', 'wp-llm-connector' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Rate Limit', 'wp-llm-connector' ); ?></th>
								<td>
									<input type="number" name="wp_llm_connector_settings[rate_limit]"
										value="<?php echo esc_attr( $settings['rate_limit'] ?? 60 ); ?>"
										min="1" max="1000" class="small-text">
									<span><?php esc_html_e( 'requests per hour per API key', 'wp-llm-connector' ); ?></span>
									<p class="description">
										<?php esc_html_e( 'Limit requests to prevent abuse', 'wp-llm-connector' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Logging', 'wp-llm-connector' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[log_requests]" value="1"
											<?php checked( $settings['log_requests'] ?? true, true ); ?>>
										<?php esc_html_e( 'Log all API requests.', 'wp-llm-connector' ); ?>
									</label>
									<p class="description">
										<?php
										esc_html_e( 'Keep an audit trail of all LLM access.', 'wp-llm-connector' );
										echo ' ';
										global $wpdb;
										$table_name = $wpdb->prefix . 'llm_connector_audit_log';
										/* translators: %s: database table name */
										printf( esc_html__( 'Logs are stored in the database table: %s', 'wp-llm-connector' ), '<code>' . esc_html( $table_name ) . '</code>' );
										?>
									</p>
									<?php
									// Validate table name before using in query.
									if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
										$log_count = 0;
									} else {
										$log_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
									}
									if ( $log_count > 0 ) :
										?>
										</form><!-- close options.php form to avoid nested <form> HTML. -->
										<form method="post" action="" style="margin-top: 10px;">
											<?php wp_nonce_field( 'wp_llm_connector_purge_log', 'wp_llm_connector_log_nonce' ); ?>
											<button type="submit" name="purge_log" class="button button-secondary"
												onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all log entries? This action cannot be undone.', 'wp-llm-connector' ) ); ?>');">
												<?php
												/* translators: %d: number of log entries */
												printf( esc_html__( 'Purge Log (%d entries)', 'wp-llm-connector' ), absint( $log_count ) );
												?>
											</button>
										</form>
										<form method="post" action="options.php"><!-- reopen options.php form -->
											<?php settings_fields( 'wp_llm_connector_settings_group' ); ?>
									<?php endif; ?>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Preserve Settings', 'wp-llm-connector' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[preserve_settings_on_uninstall]" value="1"
											<?php checked( $settings['preserve_settings_on_uninstall'] ?? false, true ); ?>>
										<?php esc_html_e( 'Keep settings when plugin is deleted', 'wp-llm-connector' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, plugin settings and API keys will be preserved even after the plugin is uninstalled. This is useful if you plan to reinstall the plugin later.', 'wp-llm-connector' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Allowed Endpoints', 'wp-llm-connector' ); ?></th>
								<td>
									<?php foreach ( $available_endpoints as $endpoint => $label ) : ?>
										<label class="wp-llm-endpoint-checkbox">
											<input type="checkbox"
												name="wp_llm_connector_settings[allowed_endpoints][]"
												value="<?php echo esc_attr( $endpoint ); ?>"
												<?php checked( in_array( $endpoint, $settings['allowed_endpoints'] ?? array(), true ) ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description">
										<?php esc_html_e( 'Select which data endpoints LLMs can access', 'wp-llm-connector' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Settings', 'wp-llm-connector' ) ); ?>
					</form>
				</div>

				<div class="wp-llm-connector-api-keys">
					<div class="wp-llm-connector-info">
						<h2><?php esc_html_e( 'Connection Information', 'wp-llm-connector' ); ?></h2>
						<div class="info-box">
							<h3><?php esc_html_e( 'API Endpoint', 'wp-llm-connector' ); ?></h3>
							<code><?php echo esc_html( rest_url( 'wp-llm-connector/v1/' ) ); ?></code>

							<h3><?php esc_html_e( 'Usage Example (cURL)', 'wp-llm-connector' ); ?></h3>
							<pre class="wp-llm-code-block">curl -H "X-WP-LLM-API-Key: YOUR_API_KEY" \
     <?php echo esc_html( rest_url( 'wp-llm-connector/v1/site-info' ) ); ?></pre>

							<h3><?php esc_html_e( 'Supported AI Tools', 'wp-llm-connector' ); ?></h3>
							<p><?php esc_html_e( 'The included MCP server + /mcp manifest endpoint work with:', 'wp-llm-connector' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Claude.ai (Web UI)', 'wp-llm-connector' ); ?></strong> &mdash; <?php esc_html_e( 'Verified', 'wp-llm-connector' ); ?></li>
								<li><strong>Claude Code</strong> &mdash; <?php esc_html_e( 'See CLAUDE_CODE_SETUP.md', 'wp-llm-connector' ); ?></li>
								<li><strong>Gemini CLI</strong> &mdash; <?php esc_html_e( 'See GEMINI_CLI_SETUP.md', 'wp-llm-connector' ); ?></li>
								<li><?php esc_html_e( 'Cursor, Windsurf, Cline, VS Code Copilot (via MCP)', 'wp-llm-connector' ); ?></li>
							</ul>

							<h3><?php esc_html_e( 'Available Endpoints', 'wp-llm-connector' ); ?></h3>
							<ul>
								<li><code>/health</code> - <?php esc_html_e( 'Health check (no auth required)', 'wp-llm-connector' ); ?></li>
								<li><code>/mcp</code> - <?php esc_html_e( 'MCP server manifest (auth required)', 'wp-llm-connector' ); ?></li>
								<li><code>/site-info</code> - <?php esc_html_e( 'Basic site information', 'wp-llm-connector' ); ?></li>
								<li><code>/plugins</code> - <?php esc_html_e( 'List all plugins', 'wp-llm-connector' ); ?></li>
								<li><code>/themes</code> - <?php esc_html_e( 'List all themes', 'wp-llm-connector' ); ?></li>
								<li><code>/system-status</code> - <?php esc_html_e( 'System health and configuration', 'wp-llm-connector' ); ?></li>
								<li><code>/user-count</code> - <?php esc_html_e( 'User statistics', 'wp-llm-connector' ); ?></li>
								<li><code>/post-stats</code> - <?php esc_html_e( 'Content statistics', 'wp-llm-connector' ); ?></li>
							</ul>
						</div>

						<?php $this->render_recent_activity_widget(); ?>
					</div>
				</div>
			</div>

			<?php elseif ( 'providers' === $active_tab ) : ?>

			<div class="wp-llm-connector-admin-container">
				<div class="wp-llm-connector-main-settings">
					<?php $this->render_providers_tab( $settings ); ?>
				</div>
			</div>

			<?php elseif ( 'api-keys' === $active_tab ) : ?>

			<div class="wp-llm-connector-admin-container">
				<div class="wp-llm-connector-main-settings">
					<h2><?php esc_html_e( 'API Keys', 'wp-llm-connector' ); ?></h2>

					<?php $this->render_api_keys_section( $settings, $new_key_full, $new_key_id ); ?>

					<?php $this->render_connect_client_section(); ?>
				</div>
			</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render the AI Providers settings tab.
	 *
	 * @param array $settings Current plugin settings.
	 */
	private function render_providers_tab( $settings ) {
		$providers_config = $settings['providers'] ?? array();

		$providers = array(
			'anthropic' => array(
				'label'  => 'Anthropic (Claude)',
				'models' => array( 'claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001' ),
			),
			'openai'    => array(
				'label'  => 'OpenAI',
				'models' => array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' ),
			),
			'gemini'    => array(
				'label'  => 'Google Gemini',
				'models' => array( 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash' ),
			),
		);

		?>
		<div class="notice notice-info inline">
			<p><strong><?php esc_html_e( 'Note:', 'wp-llm-connector' ); ?></strong> <?php esc_html_e( 'These settings configure WordPress as an AI text generation client for the WP 7.0 AI Client API. This is separate from connecting Claude Code, Gemini CLI, or other MCP tools — those connect via the', 'wp-llm-connector' ); ?> <strong><?php esc_html_e( 'API Keys', 'wp-llm-connector' ); ?></strong> <?php esc_html_e( 'tab and the MCP config in', 'wp-llm-connector' ); ?> <strong><?php esc_html_e( 'Connect Your AI Client', 'wp-llm-connector' ); ?></strong>.</p>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'wp_llm_connector_provider_settings', 'wp_llm_connector_provider_nonce' ); ?>

			<?php foreach ( $providers as $slug => $info ) :
				$config = $providers_config[ $slug ] ?? array();
				?>
				<h2><?php echo esc_html( $info['label'] ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'wp-llm-connector' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="providers[<?php echo esc_attr( $slug ); ?>][enabled]"
									value="1"
									<?php checked( ! empty( $config['enabled'] ) ); ?>>
								<?php
								/* translators: %s: provider display name */
								printf( esc_html__( 'Enable %s provider', 'wp-llm-connector' ), esc_html( $info['label'] ) );
								?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'wp-llm-connector' ); ?></th>
						<td>
							<input type="password"
								name="providers[<?php echo esc_attr( $slug ); ?>][api_key]"
								value="<?php echo esc_attr( $config['api_key'] ?? '' ); ?>"
								class="regular-text"
								autocomplete="off">
							<p class="description">
								<?php
								/* translators: %s: provider display name */
								printf( esc_html__( 'Enter your %s API key.', 'wp-llm-connector' ), esc_html( $info['label'] ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Model', 'wp-llm-connector' ); ?></th>
						<td>
							<select name="providers[<?php echo esc_attr( $slug ); ?>][default_model]">
								<?php foreach ( $info['models'] as $model ) : ?>
									<option value="<?php echo esc_attr( $model ); ?>"
										<?php selected( $config['default_model'] ?? $info['models'][0], $model ); ?>>
										<?php echo esc_html( $model ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save Provider Settings', 'wp-llm-connector' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the API keys section.
	 *
	 * @param array       $settings      Plugin settings.
	 * @param string|bool $new_key_full  The full API key if just generated, false otherwise.
	 * @param string|bool $new_key_id    The key ID of the newly generated key, false otherwise.
	 */
	private function render_api_keys_section( $settings, $new_key_full = false, $new_key_id = false ) {
		$api_keys = $settings['api_keys'] ?? array();
		?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ); ?>

			<div class="wp-llm-generate-key">
				<h3><?php esc_html_e( 'Generate New API Key', 'wp-llm-connector' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Generate an API key that LLM services (like Claude) will use to authenticate with your WordPress site.', 'wp-llm-connector' ); ?>
				</p>
				<input type="text" name="key_name"
					placeholder="<?php echo esc_attr__( 'Key name (e.g., Claude Production)', 'wp-llm-connector' ); ?>"
					class="regular-text" required>
				<button type="submit" name="generate_key" class="button button-primary">
					<?php esc_html_e( 'Generate API Key', 'wp-llm-connector' ); ?>
				</button>
			</div>
		</form>

		<h3 class="wp-llm-existing-keys-title"><?php esc_html_e( 'Existing API Keys', 'wp-llm-connector' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Key Name', 'wp-llm-connector' ); ?></th>
					<th><?php esc_html_e( 'Key Prefix', 'wp-llm-connector' ); ?></th>
					<th><?php esc_html_e( 'Created', 'wp-llm-connector' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-llm-connector' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-llm-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $api_keys ) ) : ?>
					<tr>
						<td colspan="5" class="wp-llm-empty-keys">
							<?php esc_html_e( 'No API keys generated yet. Create one using the form above.', 'wp-llm-connector' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $api_keys as $key_id => $key_data ) :
						$is_new_key = ( $new_key_full && $new_key_id === $key_id );
						?>
						<tr<?php echo $is_new_key ? ' class="wp-llm-new-key-row"' : ''; ?>>
							<td>
								<?php echo esc_html( $key_data['name'] ?? __( 'Unnamed', 'wp-llm-connector' ) ); ?>
								<?php if ( $is_new_key ) : ?>
									<span class="wp-llm-new-badge"><?php esc_html_e( 'NEW', 'wp-llm-connector' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<code class="api-key-display">
									<?php echo esc_html( $key_data['key_prefix'] ?? '****' ); ?>...
								</code>
								<?php if ( $is_new_key ) : ?>
									<div class="wp-llm-copy-row">
										<button type="button"
											class="button button-small button-primary wp-llm-copy-new-key"
											data-key="<?php echo esc_attr( $new_key_full ); ?>"
											onclick="(function(btn){var k=btn.getAttribute('data-key');if(!k){alert('Key already copied.');return;}navigator.clipboard.writeText(k).then(function(){btn.removeAttribute('data-key');btn.querySelector('.wp-llm-btn-text').textContent='Copied!';btn.style.backgroundColor='#00a32a';btn.style.borderColor='#008a20';setTimeout(function(){btn.querySelector('.wp-llm-btn-text').textContent='Copy Full Key';btn.style.backgroundColor='';btn.style.borderColor='';},2000);}).catch(function(){prompt('Copy this key manually:',k);});})(this);return false;"
											aria-label="<?php echo esc_attr__( 'Copy full API key to clipboard', 'wp-llm-connector' ); ?>">
											<span class="dashicons dashicons-clipboard"></span>
											<span class="wp-llm-btn-text"><?php esc_html_e( 'Copy Full Key', 'wp-llm-connector' ); ?></span>
										</button>
									</div>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $key_data['created'] ?? time() ) ); ?></td>
							<td>
								<?php if ( $key_data['active'] ?? true ) : ?>
									<span class="status-active"><?php esc_html_e( 'Active', 'wp-llm-connector' ); ?></span>
								<?php else : ?>
									<span class="status-inactive"><?php esc_html_e( 'Inactive', 'wp-llm-connector' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="" class="wp-llm-inline-form">
									<?php wp_nonce_field( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ); ?>
									<button type="submit" name="revoke_key" value="<?php echo esc_attr( $key_id ); ?>"
										class="button button-small button-link-delete"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to revoke this API key?', 'wp-llm-connector' ) ); ?>');">
										<?php esc_html_e( 'Revoke', 'wp-llm-connector' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Render the "Connect Your AI Client" section: client dropdown, masked
	 * config snippet preview, copy-full-config button, and health-check
	 * Test Connection badge. The snippet is rendered client-side by admin.js
	 * using the context provided via wp_localize_script.
	 */
	private function render_connect_client_section() {
		?>
		<div class="wp-llm-connect-client">
			<h2><?php esc_html_e( 'Connect Your AI Client', 'wp-llm-connector' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Pick an AI client to get a ready-to-use MCP config snippet. The preview masks your API key (first 8 characters). Use Copy full config to copy the complete config with the real key.', 'wp-llm-connector' ); ?>
			</p>

			<div class="wp-llm-connect-row">
				<label for="wp-llm-client-select" class="wp-llm-connect-label">
					<?php esc_html_e( 'AI Client', 'wp-llm-connector' ); ?>
				</label>
				<select id="wp-llm-client-select" class="wp-llm-client-select">
					<option value="claude-web"><?php esc_html_e( 'Claude.ai (Web UI)', 'wp-llm-connector' ); ?> &mdash; <?php esc_html_e( 'Verified', 'wp-llm-connector' ); ?></option>
					<option value="claude-code"><?php esc_html_e( 'Claude Code', 'wp-llm-connector' ); ?></option>
					<option value="gemini-cli"><?php esc_html_e( 'Gemini CLI', 'wp-llm-connector' ); ?></option>
					<option value="cursor-windsurf-vscode"><?php esc_html_e( 'Cursor / Windsurf / VS Code (Cline)', 'wp-llm-connector' ); ?></option>
				</select>
				<span id="wp-llm-client-verified" class="wp-llm-verified-badge" aria-live="polite">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Verified', 'wp-llm-connector' ); ?>
				</span>
			</div>

			<pre id="wp-llm-mcp-snippet" class="wp-llm-code-block wp-llm-mcp-snippet" aria-label="<?php esc_attr_e( 'MCP configuration snippet preview', 'wp-llm-connector' ); ?>"></pre>

			<div class="wp-llm-connect-actions">
				<button type="button" id="wp-llm-copy-config" class="button button-primary">
					<span class="dashicons dashicons-clipboard"></span>
					<span class="wp-llm-btn-text"><?php esc_html_e( 'Copy full config', 'wp-llm-connector' ); ?></span>
				</button>
				<button type="button" id="wp-llm-test-connection" class="button">
					<span class="dashicons dashicons-admin-plugins"></span>
					<span class="wp-llm-btn-text"><?php esc_html_e( 'Test Connection', 'wp-llm-connector' ); ?></span>
				</button>
				<span id="wp-llm-test-status" class="wp-llm-test-status" role="status" aria-live="polite"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "Recent Activity" widget used on the main settings page.
	 * Shows the five most recent log entries and a link to the full log.
	 */
	private function render_recent_activity_widget() {
		global $wpdb;
		$log_url = admin_url( 'options-general.php?page=wp-llm-connector-access-log' );

		if ( ! $this->audit_table_exists() ) {
			?>
			<div class="wp-llm-recent-activity">
				<h2><?php esc_html_e( 'Recent Activity', 'wp-llm-connector' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'The audit log table has not been created yet. Deactivate and reactivate the plugin to initialize it.', 'wp-llm-connector' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$table = $wpdb->prefix . 'llm_connector_audit_log';
		// Validate table name.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
			return;
		}
		$rows = $wpdb->get_results( "SELECT timestamp, endpoint, ip_address, response_code FROM {$table} ORDER BY timestamp DESC LIMIT 5", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wp-llm-recent-activity">
			<h2><?php esc_html_e( 'Recent Activity', 'wp-llm-connector' ); ?></h2>
			<?php if ( empty( $rows ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'No API requests logged yet. Once clients start connecting, their activity will show up here.', 'wp-llm-connector' ); ?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat striped wp-llm-recent-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'When', 'wp-llm-connector' ); ?></th>
							<th><?php esc_html_e( 'Endpoint', 'wp-llm-connector' ); ?></th>
							<th><?php esc_html_e( 'IP', 'wp-llm-connector' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-llm-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) :
							$ts         = strtotime( $row['timestamp'] . ' UTC' );
							$code       = (int) $row['response_code'];
							$code_class = 'wp-llm-log-code-unknown';
							if ( $code >= 200 && $code < 300 ) {
								$code_class = 'wp-llm-log-code-ok';
							} elseif ( $code >= 400 && $code < 500 ) {
								$code_class = 'wp-llm-log-code-warn';
							} elseif ( $code >= 500 ) {
								$code_class = 'wp-llm-log-code-err';
							}
							?>
							<tr>
								<td>
									<?php
									/* translators: %s: relative time, e.g. "2 mins ago" */
									echo esc_html( $ts ? sprintf( __( '%s ago', 'wp-llm-connector' ), human_time_diff( $ts, time() ) ) : '—' );
									?>
								</td>
								<td><code><?php echo esc_html( $row['endpoint'] ); ?></code></td>
								<td><?php echo esc_html( $row['ip_address'] ); ?></td>
								<td>
									<span class="wp-llm-log-code <?php echo esc_attr( $code_class ); ?>">
										<?php echo esc_html( $code ?: '—' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p class="wp-llm-recent-footer">
				<a href="<?php echo esc_url( $log_url ); ?>">
					<?php esc_html_e( 'View full log', 'wp-llm-connector' ); ?> &rarr;
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle provider settings form submission.
	 */
	public function handle_provider_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below when processing POST.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_POST['providers'] ) || ! check_admin_referer( 'wp_llm_connector_provider_settings', 'wp_llm_connector_provider_nonce' ) ) {
			return;
		}

		$settings  = get_option( 'wp_llm_connector_settings', array() );
		$providers = array();

		$allowed_slugs = array( 'anthropic', 'openai', 'gemini' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-field below.
		$input = wp_unslash( $_POST['providers'] );

		if ( is_array( $input ) ) {
			foreach ( $allowed_slugs as $slug ) {
				$data               = $input[ $slug ] ?? array();
				$providers[ $slug ] = array(
					'enabled'       => ! empty( $data['enabled'] ),
					'api_key'       => isset( $data['api_key'] ) ? sanitize_text_field( $data['api_key'] ) : '',
					'default_model' => isset( $data['default_model'] ) ? sanitize_text_field( $data['default_model'] ) : '',
				);
			}
		}

		$settings['providers'] = $providers;
		update_option( 'wp_llm_connector_settings', $settings );

		add_settings_error(
			'wp_llm_connector_messages',
			'providers_saved',
			__( 'Provider settings saved.', 'wp-llm-connector' ),
			'success'
		);
	}

	/**
	 * AJAX handler that pings the /health REST endpoint server-side.
	 *
	 * Running this through admin-ajax avoids surprises from browser CORS or
	 * security plugins that might block a fetch to the REST route from the
	 * admin origin, and keeps nonce + capability checks consistent.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'wp_llm_connector_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'wp-llm-connector' ) ), 403 );
		}

		$health_url = rest_url( 'wp-llm-connector/v1/health' );
		$response   = wp_remote_get(
			$health_url,
			array(
				'timeout'   => 10,
				'sslverify' => apply_filters( 'wp_llm_connector_sslverify', true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success(
				array(
					'status' => 'connected',
					'code'   => $code,
				)
			);
		}

		wp_send_json_error(
			array(
				'status' => 'failed',
				'code'   => $code,
			)
		);
	}

	/**
	 * Get the newly generated API key for JavaScript (passed via wp_localize_script).
	 * This avoids putting the full key in a data-attribute in the HTML.
	 * The key is only available temporarily via a user-specific transient.
	 *
	 * @return string Empty string if no new key, otherwise the full key.
	 */
	private function get_new_key_for_js() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['key_generated'] ) || '1' !== $_GET['key_generated'] ) {
			return '';
		}

		$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
		$api_key       = get_transient( $transient_key );
		return $api_key ? $api_key : '';
	}

	public function handle_api_key_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified later when processing forms.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector' !== $_GET['page'] ) {
			return;
		}

		// Generate new key.
		if ( isset( $_POST['generate_key'] ) && check_admin_referer( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ) ) {
			$key_name = sanitize_text_field( wp_unslash( $_POST['key_name'] ?? 'Unnamed' ) );

			// Check if key name already exists.
			$settings      = get_option( 'wp_llm_connector_settings', array() );
			$existing_keys = $settings['api_keys'] ?? array();
			$name_exists   = in_array( $key_name, array_column( $existing_keys, 'name' ), true );

			if ( $name_exists ) {
				set_transient(
					'wp_llm_connector_error',
					sprintf(
						/* translators: %s: the duplicate key name */
						__( 'An API key with the name "%s" already exists. Please use a unique name.', 'wp-llm-connector' ),
						esc_html( $key_name )
					),
					30
				);

				$redirect_url = add_query_arg(
					array(
						'page'     => 'wp-llm-connector',
						'tab'      => 'api-keys',
						'error'    => '1',
						'_wpnonce' => wp_create_nonce( 'wp_llm_connector_error' ),
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$api_key = \WP_LLM_Connector\Security\Security_Manager::generate_api_key();

			$settings['api_keys'] = $settings['api_keys'] ?? array();

			$key_id                          = wp_generate_uuid4();
			$settings['api_keys'][ $key_id ] = array(
				'name'          => $key_name,
				'key_hash'      => hash( 'sha256', $api_key ),
				'key_prefix'    => substr( $api_key, 0, 12 ),
				'created'       => time(),
				'active'        => true,
				// Write-tier fields. New keys are always read-only by default;
				// the per-key "Enable write access" toggle (future UI) is the
				// only code path that flips write_enabled to true.
				// See docs/WRITE_TIER.md for the design.
				'write_enabled' => false,
				'write_scopes'  => array(),
			);

			update_option( 'wp_llm_connector_settings', $settings );

			// Verify the key was actually saved by reading it back.
			$verified_settings = get_option( 'wp_llm_connector_settings', array() );
			if ( isset( $verified_settings['api_keys'][ $key_id ] ) ) {
				// Store the generated key and its ID in user-specific transients.
				// HOUR_IN_SECONDS TTL outlives the admin page load so the
				// Copy Full Key button and "Connect Your AI Client" snippet
				// can still access the plaintext after the redirect.
				$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
				set_transient( $transient_key, $api_key, HOUR_IN_SECONDS );
				set_transient( $transient_key . '_id', $key_id, HOUR_IN_SECONDS );

				$redirect_url = add_query_arg(
					array(
						'page'          => 'wp-llm-connector',
						'tab'           => 'api-keys',
						'key_generated' => '1',
						'_wpnonce'      => wp_create_nonce( 'wp_llm_connector_key_generated' ),
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			} else {
				add_settings_error(
					'wp_llm_connector_messages',
					'key_generation_failed',
					__( 'Failed to save API key. Please try again.', 'wp-llm-connector' ),
					'error'
				);
			}
		}

		// Revoke key.
		if ( isset( $_POST['revoke_key'] ) && check_admin_referer( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ) ) {
			$key_id = sanitize_text_field( wp_unslash( $_POST['revoke_key'] ) );

			$settings = get_option( 'wp_llm_connector_settings', array() );
			if ( isset( $settings['api_keys'][ $key_id ] ) ) {
				unset( $settings['api_keys'][ $key_id ] );

				update_option( 'wp_llm_connector_settings', $settings );

				$verified_settings = get_option( 'wp_llm_connector_settings', array() );
				if ( ! isset( $verified_settings['api_keys'][ $key_id ] ) ) {
					$redirect_url = add_query_arg(
						array(
							'page'        => 'wp-llm-connector',
							'tab'         => 'api-keys',
							'key_revoked' => '1',
							'_wpnonce'    => wp_create_nonce( 'wp_llm_connector_key_revoked' ),
						),
						admin_url( 'options-general.php' )
					);
					wp_safe_redirect( $redirect_url );
					exit;
				} else {
					add_settings_error(
						'wp_llm_connector_messages',
						'key_revocation_failed',
						__( 'Failed to revoke API key. Please try again.', 'wp-llm-connector' ),
						'error'
					);
				}
			}
		}

		// Show revocation success message after redirect.
		if ( isset( $_GET['key_revoked'] ) && '1' === $_GET['key_revoked'] ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_key_revoked' ) ) {
				return;
			}

			add_settings_error(
				'wp_llm_connector_messages',
				'key_revoked',
				__( 'API Key revoked successfully.', 'wp-llm-connector' ),
				'success'
			);
		}
	}

	public function handle_log_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified later when processing forms.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_POST['purge_log'] ) && check_admin_referer( 'wp_llm_connector_purge_log', 'wp_llm_connector_log_nonce' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'llm_connector_audit_log';

			// Validate table name before truncating.
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
				return;
			}

			$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$redirect_url = add_query_arg(
				array(
					'page'       => 'wp-llm-connector',
					'log_purged' => '1',
					'_wpnonce'   => wp_create_nonce( 'wp_llm_connector_log_purged' ),
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Render the full Access Log page: summary bar, filter form, list table,
	 * and action buttons (Clear Log, Export CSV). Gracefully handles a
	 * missing / empty audit log table.
	 */
	public function render_access_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters   = $this->get_access_log_filters_from_request();
		$has_table = $this->audit_table_exists();

		?>
		<div class="wrap wp-llm-access-log">
			<h1><?php esc_html_e( 'LLM Connector — Access Log', 'wp-llm-connector' ); ?></h1>

			<?php
			if ( isset( $_GET['cleared'] ) && '1' === $_GET['cleared']
				&& isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_log_cleared' ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Access log cleared.', 'wp-llm-connector' ); ?></p>
				</div>
				<?php
			}

			if ( ! $has_table ) {
				?>
				<div class="wp-llm-log-empty notice notice-info inline">
					<p>
						<strong><?php esc_html_e( 'No audit log table found.', 'wp-llm-connector' ); ?></strong>
					</p>
					<p>
						<?php esc_html_e( 'Deactivate and reactivate the plugin, or visit Settings > LLM Connector once, to create the table. New API requests will then be recorded automatically.', 'wp-llm-connector' ); ?>
					</p>
				</div>
				</div>
				<?php
				return;
			}

			$summary = $this->get_access_log_summary();
			?>

			<div class="wp-llm-log-summary">
				<div class="wp-llm-log-summary-item">
					<span class="wp-llm-log-summary-num"><?php echo esc_html( number_format_i18n( $summary['requests'] ) ); ?></span>
					<span class="wp-llm-log-summary-label"><?php esc_html_e( 'Requests today', 'wp-llm-connector' ); ?></span>
				</div>
				<div class="wp-llm-log-summary-item">
					<span class="wp-llm-log-summary-num"><?php echo esc_html( number_format_i18n( $summary['unique_ips'] ) ); ?></span>
					<span class="wp-llm-log-summary-label"><?php esc_html_e( 'Unique IPs today', 'wp-llm-connector' ); ?></span>
				</div>
				<div class="wp-llm-log-summary-item">
					<span class="wp-llm-log-summary-num"><?php echo esc_html( number_format_i18n( $summary['error_rate'], 1 ) ); ?>%</span>
					<span class="wp-llm-log-summary-label"><?php esc_html_e( 'Error rate today', 'wp-llm-connector' ); ?></span>
				</div>
			</div>

			<form method="get" class="wp-llm-log-filters">
				<input type="hidden" name="page" value="wp-llm-connector-access-log">

				<label>
					<?php esc_html_e( 'Range', 'wp-llm-connector' ); ?>
					<select name="range" class="wp-llm-log-range">
						<?php
						$ranges = array(
							'24h'    => __( 'Last 24 hours', 'wp-llm-connector' ),
							'7d'     => __( 'Last 7 days', 'wp-llm-connector' ),
							'30d'    => __( 'Last 30 days', 'wp-llm-connector' ),
							'all'    => __( 'All time', 'wp-llm-connector' ),
							'custom' => __( 'Custom…', 'wp-llm-connector' ),
						);
						foreach ( $ranges as $value => $label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $value ),
								selected( $filters['range'], $value, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
				</label>

				<label class="wp-llm-log-custom-range"<?php echo 'custom' === $filters['range'] ? '' : ' style="display:none;"'; ?>>
					<?php esc_html_e( 'From', 'wp-llm-connector' ); ?>
					<input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>">
				</label>
				<label class="wp-llm-log-custom-range"<?php echo 'custom' === $filters['range'] ? '' : ' style="display:none;"'; ?>>
					<?php esc_html_e( 'To', 'wp-llm-connector' ); ?>
					<input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>">
				</label>

				<label>
					<?php esc_html_e( 'Status', 'wp-llm-connector' ); ?>
					<select name="status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All', 'wp-llm-connector' ); ?></option>
						<option value="success" <?php selected( $filters['status'], 'success' ); ?>><?php esc_html_e( 'Success (2xx)', 'wp-llm-connector' ); ?></option>
						<option value="errors" <?php selected( $filters['status'], 'errors' ); ?>><?php esc_html_e( 'Errors (4xx/5xx)', 'wp-llm-connector' ); ?></option>
					</select>
				</label>

				<label>
					<?php esc_html_e( 'Search', 'wp-llm-connector' ); ?>
					<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'IP or endpoint', 'wp-llm-connector' ); ?>">
				</label>

				<?php submit_button( __( 'Filter', 'wp-llm-connector' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-llm-connector-access-log' ) ); ?>">
					<?php esc_html_e( 'Reset', 'wp-llm-connector' ); ?>
				</a>
			</form>

			<div class="wp-llm-log-actions">
				<form method="post" action="" class="wp-llm-log-export-form">
					<?php wp_nonce_field( 'wp_llm_connector_export_log', 'wp_llm_connector_export_nonce' ); ?>
					<?php foreach ( $filters as $k => $v ) : ?>
						<input type="hidden" name="<?php echo esc_attr( 'f_' . $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
					<?php endforeach; ?>
					<button type="submit" name="wp_llm_export_log" class="button">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'wp-llm-connector' ); ?>
					</button>
				</form>

				<form method="post" action="" class="wp-llm-log-clear-form">
					<?php wp_nonce_field( 'wp_llm_connector_clear_log', 'wp_llm_connector_clear_nonce' ); ?>
					<button type="submit" name="wp_llm_clear_log" class="button button-link-delete"
						onclick="return confirm('<?php echo esc_js( __( 'Delete all log entries? This action cannot be undone.', 'wp-llm-connector' ) ); ?>');">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear Log', 'wp-llm-connector' ); ?>
					</button>
				</form>
			</div>

			<?php
			$table = new Access_Log_Table( $filters );
			$table->prepare_items();
			?>
			<form method="get">
				<input type="hidden" name="page" value="wp-llm-connector-access-log">
				<?php foreach ( $filters as $k => $v ) : ?>
					<?php $name = ( 'search' === $k ) ? 's' : $k; ?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $v ); ?>">
				<?php endforeach; ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle Clear Log + Export CSV submissions from the Access Log page.
	 * Must run before headers are sent because CSV export streams a file.
	 */
	public function handle_access_log_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified per-action below.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector-access-log' !== $_GET['page'] ) {
			return;
		}

		// Export CSV.
		if ( isset( $_POST['wp_llm_export_log'] ) && check_admin_referer( 'wp_llm_connector_export_log', 'wp_llm_connector_export_nonce' ) ) {
			$filters = array(
				'range'  => isset( $_POST['f_range'] ) ? sanitize_key( wp_unslash( $_POST['f_range'] ) ) : '24h',
				'status' => isset( $_POST['f_status'] ) ? sanitize_key( wp_unslash( $_POST['f_status'] ) ) : 'all',
				'search' => isset( $_POST['f_search'] ) ? sanitize_text_field( wp_unslash( $_POST['f_search'] ) ) : '',
				'from'   => isset( $_POST['f_from'] ) ? sanitize_text_field( wp_unslash( $_POST['f_from'] ) ) : '',
				'to'     => isset( $_POST['f_to'] ) ? sanitize_text_field( wp_unslash( $_POST['f_to'] ) ) : '',
			);
			$this->stream_csv_export( $filters );
			exit;
		}

		// Clear log.
		if ( isset( $_POST['wp_llm_clear_log'] ) && check_admin_referer( 'wp_llm_connector_clear_log', 'wp_llm_connector_clear_nonce' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'llm_connector_audit_log';

			// Validate table name before truncating.
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
				return;
			}

			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'wp-llm-connector-access-log',
						'cleared'  => '1',
						'_wpnonce' => wp_create_nonce( 'wp_llm_connector_log_cleared' ),
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Collect filters for the Access Log page from the current request.
	 * Returns an associative array consumed by Access_Log_Table and the
	 * shared WHERE-clause builder.
	 *
	 * @return array
	 */
	private function get_access_log_filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$allowed_ranges = array( 'all', '24h', '7d', '30d', 'custom' );
		$allowed_status = array( 'all', 'success', 'errors' );

		$range  = isset( $_GET['range'] ) && in_array( $_GET['range'], $allowed_ranges, true ) ? sanitize_key( $_GET['range'] ) : '24h';
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], $allowed_status, true ) ? sanitize_key( $_GET['status'] ) : 'all';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'range'  => $range,
			'status' => $status,
			'search' => $search,
			'from'   => $from,
			'to'     => $to,
		);
	}

	/**
	 * Check if the audit log table actually exists. Returns false on fresh
	 * installs that somehow skipped activation so the UI can render an
	 * explicit empty state rather than a SQL error.
	 */
	private function audit_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . 'llm_connector_audit_log';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $found === $table;
	}

	/**
	 * Compute the top-of-page summary bar numbers (today, in site timezone).
	 *
	 * @return array{requests:int,unique_ips:int,error_rate:float}
	 */
	private function get_access_log_summary() {
		global $wpdb;
		$table = $wpdb->prefix . 'llm_connector_audit_log';

		// "Today" in the site's configured timezone, expressed as a UTC
		// datetime for comparison against the UTC-stored timestamp column.
		$start_local = new \DateTimeImmutable( 'now', wp_timezone() );
		$start_local = $start_local->setTime( 0, 0, 0 );
		$start_day   = $start_local->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s", $start_day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE timestamp >= %s", $start_day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$errors = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s AND response_code >= 400", $start_day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rate = $total > 0 ? ( $errors / $total ) * 100 : 0.0;

		return array(
			'requests'   => $total,
			'unique_ips' => $unique,
			'error_rate' => round( $rate, 1 ),
		);
	}

	/**
	 * Stream the filtered access log as a CSV download.
	 * Runs on admin_init so headers can still be set; calls exit() on success.
	 *
	 * @param array $filters Filter array (range/status/search/from/to).
	 */
	private function stream_csv_export( array $filters ) {
		global $wpdb;

		if ( ! $this->audit_table_exists() ) {
			wp_die( esc_html__( 'Audit log table does not exist.', 'wp-llm-connector' ) );
		}

		list( $where_sql, $where_args ) = Access_Log_Table::build_where_clause( $filters );
		$table = $wpdb->prefix . 'llm_connector_audit_log';

		$sql = "SELECT timestamp, endpoint, http_method, execution_time_ms, response_code, ip_address, user_agent, provider
				FROM {$table}
				{$where_sql}
				ORDER BY timestamp DESC
				LIMIT 100000";

		$rows = $where_args
			? $wpdb->get_results( $wpdb->prepare( $sql, $where_args ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="llm-connector-access-log-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Timestamp (UTC)', 'Endpoint', 'Method', 'Execution ms', 'Response Code', 'IP Address', 'User Agent', 'Provider' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row['timestamp'] ?? '',
					$row['endpoint'] ?? '',
					$row['http_method'] ?? '',
					$row['execution_time_ms'] ?? '',
					$row['response_code'] ?? '',
					$row['ip_address'] ?? '',
					$row['user_agent'] ?? '',
					$row['provider'] ?? '',
				)
			);
		}
		fclose( $output );
	}
}
