<?php
/**
 * Plugin Name:       WooCommerce ArtsPay Gateway
 * Plugin URI:        https://artspay.com
 * Description:       Combine your current WordPress Woo Commerce installation with ArtsPay for a payments solution that gives back to the arts. Supports WooCommerce Subscriptions.
 * Version:           3.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            ArtsPay
 * Author URI:        https://artspay.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       artspay
 * Domain Path:       /languages
 *
 * @package ArtsPay
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ARTSPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARTSPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

const ARTSPAY_PLUGIN_VERSION = '3.1.1';
const ARTSPAY_PLUGIN_FILE    = __FILE__;

spl_autoload_register(
	function ( string $class_name ): void {
		$namespace = 'ArtsPay\\';

		if ( ! str_starts_with( $class_name, $namespace ) ) {
			return;
		}

		$class_name = str_replace( $namespace, '', $class_name );
		$class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );

		$file = ARTSPAY_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $class_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Register activation/deactivation hooks.
register_activation_hook( __FILE__, array( 'ArtsPay\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ArtsPay\Plugin', 'deactivate' ) );

// Initialize the plugin.
add_action(
	'plugins_loaded',
	function (): void {
		ArtsPay\Plugin::get_instance();
	}
);
