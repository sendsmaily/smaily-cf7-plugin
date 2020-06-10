<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/sendsmaily/smaily-cf7-plugin
 * @since             1.0.0
 * @package           Smaily_For_CF7
 *
 * @wordpress-plugin
 * Plugin Name: Smaily for Contact Form 7
 * Plugin URI: https://github.com/sendsmaily/smaily-cf7-plugin
 * Description: Integrate Contact Form 7 form(s) with Smaily to add subscribers directly to Smaily and trigger marketing automations.
 * Version: 1.0.2
 * License: GPL3
 * Author: Smaily
 * Author URI: https://smaily.com/
 * Text Domain: smaily-for-contact-form-7
 * Domain Path: languages
 *
 * Smaily for Contact Form 7 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Smaily for Contact Form 7 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Smaily for Contact Form 7. If not, see <http://www.gnu.org/licenses/>.
 */

// If accessed directly exit program.
defined( 'ABSPATH' ) || die( "This is a plugin you can't access directly" );

// Required to use functions is_plugin_active and deactivate_plugins.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

define( 'SMAILY_FOR_CF7_VERSION', '1.0.2' );

/**
 * The core plugin class that is used to define
 * admin-specific hook and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-smaily-for-cf7.php';

/**
 * Load translations files.
 *
 * @since 1.0.0
 */
function smaily_for_cf7_load_textdomain() {
	load_plugin_textdomain( 'smaily-for-contact-form-7', false, plugin_dir_path( __FILE__ ) . '/languages/' );
}
add_action( 'plugins_loaded', 'smaily_for_cf7_load_textdomain' );

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_smaily_for_cf7() {
	$plugin = new Smaily_For_CF7();
	// Check if Contact Form 7 is installed and activate plugin only if it is.
	if ( ! is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ), false );
		$message = __(
			'Smaily for Contact Form 7 is not able to activate.
			Contact Form 7 is needed to function properly. Is Contact Form 7 installed and activated?',
			'smaily-for-contact-form-7'
		);
		wp_die( esc_html( $message ) );
	}
	$plugin->run();
}
run_smaily_for_cf7();
