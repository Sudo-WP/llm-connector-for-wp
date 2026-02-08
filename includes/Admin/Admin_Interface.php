<?php
namespace WP_LLM_Connector\Admin;

class Admin_Interface {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_api_key_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_log_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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

		// Preserve existing API keys if not provided in input (form submissions don't include them).
		// If api_keys are in the input, use them (programmatic updates via handle_api_key_actions).
		if ( isset( $input['api_keys'] ) && is_array( $input['api_keys'] ) ) {
			// Sanitize each API key entry for security.
			$api_keys = array();
			foreach ( $input['api_keys'] as $key_id => $key_data ) {
				if ( is_array( $key_data ) ) {
					$api_keys[ sanitize_text_field( $key_id ) ] = array(
						'name'       => isset( $key_data['name'] ) ? sanitize_text_field( $key_data['name'] ) : '',
						'key_hash'   => isset( $key_data['key_hash'] ) ? sanitize_text_field( $key_data['key_hash'] ) : '',
						'key_prefix' => isset( $key_data['key_prefix'] ) ? sanitize_text_field( $key_data['key_prefix'] ) : '',
						'created'    => isset( $key_data['created'] ) ? absint( $key_data['created'] ) : 0,
						'active'     => isset( $key_data['active'] ) ? (bool) $key_data['active'] : true,
					);
				}
			}
			$sanitized['api_keys'] = $api_keys;
		} else {
			$current_settings      = get_option( 'wp_llm_connector_settings', array() );
			$sanitized['api_keys'] = $current_settings['api_keys'] ?? array();
		}

		return $sanitized;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_wp-llm-connector' !== $hook ) {
			return;
		}

		// Enqueue Dashicons
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'wp-llm-connector-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			WP_LLM_CONNECTOR_VERSION . '.' . time()
		);

		wp_enqueue_script(
			'wp-llm-connector-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_LLM_CONNECTOR_VERSION . '.' . time(),
			true
		);

		wp_localize_script(
			'wp-llm-connector-admin',
			'wpLlmConnector',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'wp_llm_connector_ajax' ),
				'newKey'             => $this->get_new_key_for_js(),
				'i18n'               => array(
					'copyLabel'      => __( 'Copy to clipboard', 'wp-llm-connector' ),
					'copiedLabel'    => __( 'Copied to clipboard', 'wp-llm-connector' ),
					'copyText'       => __( 'Copy Full Key', 'wp-llm-connector' ),
					'copiedText'     => __( 'Copied!', 'wp-llm-connector' ),
					'copyError'      => __( 'Failed to copy to clipboard. Please select and copy the key manually.', 'wp-llm-connector' ),
				),
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings            = get_option( 'wp_llm_connector_settings', array() );
		$available_endpoints = array(
			'site_info'     => __( 'Site Information', 'wp-llm-connector' ),
			'plugin_list'   => __( 'Plugin List', 'wp-llm-connector' ),
			'theme_list'    => __( 'Theme List', 'wp-llm-connector' ),
			'user_count'    => __( 'User Count', 'wp-llm-connector' ),
			'post_stats'    => __( 'Post Statistics', 'wp-llm-connector' ),
			'system_status' => __( 'System Status', 'wp-llm-connector' ),
		);

		// Check if a new key was just generated and retrieve the full key from transient.
		$new_key_full  = false;
		$new_key_id    = false;

		if ( isset( $_GET['key_generated'] ) && $_GET['key_generated'] === '1' ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_key_generated' ) ) {
				$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
				$new_key_full  = get_transient( $transient_key );

				// Also retrieve which key ID was just generated.
				$new_key_id = get_transient( $transient_key . '_id' );
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// Display error message if available.
			if ( isset( $_GET['error'] ) && $_GET['error'] === '1' ) {
				// Verify nonce to prevent URL manipulation.
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

			// Show a brief success notice (no key displayed here — it's in the table row).
			if ( $new_key_full ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'API Key generated successfully!', 'wp-llm-connector' ); ?></strong>
						<?php esc_html_e( 'Use the Reveal and Copy buttons in the table below to view and copy your new key. The full key is only available temporarily.', 'wp-llm-connector' ); ?>
					</p>
				</div>
				<?php
			}

			// Display log purge success message.
			if ( isset( $_GET['log_purged'] ) && $_GET['log_purged'] === '1' ) {
				// Verify nonce to prevent URL manipulation.
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
										<?php esc_html_e( 'When enabled, LLMs can only read data, not modify anything', 'wp-llm-connector' ); ?>
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
									// Get log count.
									$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
									if ( $log_count > 0 ) :
										?>
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
					<h2><?php esc_html_e( 'API Keys', 'wp-llm-connector' ); ?></h2>

					<?php $this->render_api_keys_section( $settings, $new_key_full, $new_key_id ); ?>

					<div class="wp-llm-connector-info">
						<h2><?php esc_html_e( 'Connection Information', 'wp-llm-connector' ); ?></h2>
						<div class="info-box">
							<h3><?php esc_html_e( 'API Endpoint', 'wp-llm-connector' ); ?></h3>
							<code><?php echo esc_html( rest_url( 'wp-llm-connector/v1/' ) ); ?></code>

							<h3><?php esc_html_e( 'Usage Example (cURL)', 'wp-llm-connector' ); ?></h3>
							<pre class="wp-llm-code-block">curl -H "X-WP-LLM-API-Key: YOUR_API_KEY" \
     <?php echo esc_html( rest_url( 'wp-llm-connector/v1/site-info' ) ); ?></pre>

							<h3><?php esc_html_e( 'Available Endpoints', 'wp-llm-connector' ); ?></h3>
							<ul>
								<li><code>/health</code> - <?php esc_html_e( 'Health check (no auth required)', 'wp-llm-connector' ); ?></li>
								<li><code>/site-info</code> - <?php esc_html_e( 'Basic site information', 'wp-llm-connector' ); ?></li>
								<li><code>/plugins</code> - <?php esc_html_e( 'List all plugins', 'wp-llm-connector' ); ?></li>
								<li><code>/themes</code> - <?php esc_html_e( 'List all themes', 'wp-llm-connector' ); ?></li>
								<li><code>/system-status</code> - <?php esc_html_e( 'System health and configuration', 'wp-llm-connector' ); ?></li>
								<li><code>/user-count</code> - <?php esc_html_e( 'User statistics', 'wp-llm-connector' ); ?></li>
								<li><code>/post-stats</code> - <?php esc_html_e( 'Content statistics', 'wp-llm-connector' ); ?></li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
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
						// Determine if this is the newly generated key.
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
	 * Get the newly generated API key for JavaScript (passed via wp_localize_script).
	 * This avoids putting the full key in a data-attribute in the HTML.
	 * The key is only available temporarily via a user-specific transient.
	 *
	 * @return string Empty string if no new key, otherwise the full key.
	 */
	private function get_new_key_for_js() {
		// Only provide the key if we're on the key_generated page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['key_generated'] ) || $_GET['key_generated'] !== '1' ) {
			return '';
		}

		// Retrieve from user-specific transient (set during key generation).
		$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
		$api_key = get_transient( $transient_key );
		return $api_key ? $api_key : '';
	}

	public function handle_api_key_actions() {
		// Only handle actions on our settings page.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only process on our admin page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified later when processing forms.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector' !== $_GET['page'] ) {
			return;
		}

		// Generate new key.
		if ( isset( $_POST['generate_key'] ) && check_admin_referer( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ) ) {
			$key_name = sanitize_text_field( wp_unslash( $_POST['key_name'] ?? 'Unnamed' ) );

			// Check if key name already exists.
			$settings = get_option( 'wp_llm_connector_settings', array() );
			$existing_keys = $settings['api_keys'] ?? array();
			$name_exists = in_array( $key_name, array_column( $existing_keys, 'name' ), true );

			if ( $name_exists ) {
				// Store error message in transient to display after redirect.
				set_transient(
					'wp_llm_connector_error',
					sprintf(
						/* translators: %s: the duplicate key name */
						__( 'An API key with the name "%s" already exists. Please use a unique name.', 'wp-llm-connector' ),
						esc_html( $key_name )
					),
					30
				);

				// Redirect to show error.
				$redirect_url = add_query_arg(
					array(
						'page'  => 'wp-llm-connector',
						'error' => '1',
						'_wpnonce' => wp_create_nonce( 'wp_llm_connector_error' ),
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$api_key  = \WP_LLM_Connector\Security\Security_Manager::generate_api_key();

			$settings['api_keys'] = $settings['api_keys'] ?? array();

			$key_id                          = wp_generate_uuid4();
			$settings['api_keys'][ $key_id ] = array(
				'name'       => $key_name,
				'key_hash'   => hash( 'sha256', $api_key ),
				'key_prefix' => substr( $api_key, 0, 12 ),
				'created'    => time(),
				'active'     => true,
			);

			// Update the option.
			update_option( 'wp_llm_connector_settings', $settings );
			
			// Verify the key was actually saved by reading it back.
			$verified_settings = get_option( 'wp_llm_connector_settings', array() );
			if ( isset( $verified_settings['api_keys'][ $key_id ] ) ) {
				// Success! Store the generated key and its ID in user-specific transients.
				$transient_key = 'wp_llm_connector_new_key_' . get_current_user_id();
				set_transient( $transient_key, $api_key, HOUR_IN_SECONDS );
				set_transient( $transient_key . '_id', $key_id, HOUR_IN_SECONDS );
				
				// Redirect to show the key.
				$redirect_url = add_query_arg(
					array(
						'page'          => 'wp-llm-connector',
						'key_generated' => '1',
						'_wpnonce'      => wp_create_nonce( 'wp_llm_connector_key_generated' ),
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			} else {
				// Key was not saved properly.
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
				
				// Update the option.
				update_option( 'wp_llm_connector_settings', $settings );
				
				// Verify the key was actually removed by reading it back.
				$verified_settings = get_option( 'wp_llm_connector_settings', array() );
				if ( ! isset( $verified_settings['api_keys'][ $key_id ] ) ) {
					// Success! Redirect to show confirmation.
					$redirect_url = add_query_arg(
						array(
							'page'        => 'wp-llm-connector',
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
		if ( isset( $_GET['key_revoked'] ) && $_GET['key_revoked'] === '1' ) {
			// Verify nonce to prevent URL manipulation.
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
		// Only handle actions on our settings page.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only process on our admin page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified later when processing forms.
		if ( ! isset( $_GET['page'] ) || 'wp-llm-connector' !== $_GET['page'] ) {
			return;
		}

		// Purge log.
		if ( isset( $_POST['purge_log'] ) && check_admin_referer( 'wp_llm_connector_purge_log', 'wp_llm_connector_log_nonce' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'llm_connector_audit_log';

			// Truncate the audit log table.
			$wpdb->query( "TRUNCATE TABLE {$table_name}" );

			// Redirect to show success message.
			$redirect_url = add_query_arg(
				array(
					'page'        => 'wp-llm-connector',
					'log_purged'  => '1',
					'_wpnonce'    => wp_create_nonce( 'wp_llm_connector_log_purged' ),
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
}
