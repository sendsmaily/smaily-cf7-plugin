<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/sendsmaily/smaily-cf7-plugin
 * @since      1.0.0
 *
 * @package    smaily_for_cf7
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( wp_load_alloptions() as $option => $value ) {
	if ( strpos( $option, 'smailyforcf7_' ) === 0 ) {
		delete_option( $option );
	}
}
