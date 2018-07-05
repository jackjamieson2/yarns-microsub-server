<?php
/**
 * Plugin Name: Microsub testing JJ 2
 * Plugin URI:
 * Description:
 * Author:
 * Author URI:
 * Version:
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: yarns_microsub
 * Domain Path: /languages
 */


add_action( 'plugins_loaded', array( 'Yarns_MicroSub_Plugin', 'init' ) );

// initialize admin settings
//require_once dirname( __FILE__ ) . '/includes/class-webmention-admin.php';

/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Yarns_MicroSub_Plugin {

	/**
	 * Initialize Webmention Plugin
	 */
	public static function init() {
		// Add a new feature type to posts for microsub items
		// add_post_type_support( 'post', 'microsub_items' );

		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}

		// Initialize Microsub endpoint
		require_once dirname( __FILE__ ) . '/includes/class-microsub-endpoint.php';
		add_action( 'init', array( 'Yarns_Microsub_Endpoint', 'init' ) );

		// Initialize Microsub endpoint
		require_once dirname( __FILE__ ) . '/includes/functions-microsub-actions.php';
		//add_action( 'init', array( 'Yarns_Microsub_Endpoint', 'init' ) );

		
		// list of various public helper functions
		require_once dirname( __FILE__ ) . '/includes/functions.php';
		
	}

	

	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}
