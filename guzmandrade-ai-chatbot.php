<?php
/**
 * Plugin Name:       Just Another Generic Chatbot
 * Plugin URI:        https://github.com/guzmandrade-dev/just-another-generic-chatbot
 * Description:       AI-powered frontend chatbot with a knowledge base, Akismet spam protection, and Cloudflare Turnstile captcha. Leverages the WordPress 7.0 Connectors API and the PHP AI Client SDK — no custom AI provider code required.
 * Version:           1.1.0
 * Author:            Mauricio Andrade
 * Author URI:        https://profiles.wordpress.org/h4l9k/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       just-another-generic-chatbot
 * Domain Path:       /languages
 * Requires at least: 7.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'JAGC_VERSION', '1.1.0' );
define( 'JAGC_PLUGIN_FILE', __FILE__ );
define( 'JAGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JAGC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JAGC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-plugin.php';

register_activation_hook( __FILE__, array( 'JAGC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JAGC_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {

		JAGC_Plugin::get_instance();
	}
);
