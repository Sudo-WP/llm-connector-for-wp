<?php
/**
 * Plugin Name: LLM Connector for WordPress
 * Plugin URI: https://github.com/Sudo-WP/llm-connector-for-wp
 * Description: Connect your WordPress site to LLM agents for secure, read-only diagnostics and administration. Currently supports Claude Code LLM with more LLMs coming in future versions.
 * Version: 0.1.1
 * Author: SudoWP
 * Author URI: https://sudowp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-llm-connector
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_LLM_CONNECTOR_VERSION', '0.1.1' );
define( 'WP_LLM_CONNECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_LLM_CONNECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_LLM_CONNECTOR_PLUGIN_FILE', __FILE__ );

// PSR-4 Autoloader.
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'WP_LLM_Connector\\';
		$base_dir = WP_LLM_CONNECTOR_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function wp_llm_connector_init() {
	$plugin = WP_LLM_Connector\Core\Plugin::get_instance();
	$plugin->init();
}
add_action( 'plugins_loaded', 'wp_llm_connector_init' );

// Activation hook.
register_activation_hook(
	__FILE__,
	function () {
		WP_LLM_Connector\Core\Activator::activate();
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function () {
		WP_LLM_Connector\Core\Deactivator::deactivate();
	}
);
