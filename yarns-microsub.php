<?php
/**
 * Plugin Name: Microsub testing JJ 2
 * Plugin URI:
 * Description: Early in progress
 * Author:
 * Author URI:
 * Version:
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: yarns_microsub
 * Domain Path: /languages
 */


add_action( 'plugins_loaded', array( 'Yarns_MicroSub_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Yarns_MicroSub_Plugin', 'init' ) );


class Yarns_MicroSub_Plugin {

	public static function plugins_loaded() {
		

	}


	/**
	 * Initialize Yarns Microsub Server plugin Plugin
	 */
	public static function init() {
		



		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}

		// Initialize Microsub endpoint
		require_once dirname( __FILE__ ) . '/includes/class-microsub-endpoint.php';
		Yarns_Microsub_Endpoint::init();
		//add_action( 'init', array( 'Yarns_Microsub_Endpoint', 'init' ) );

		// Initialize Microsub posts
		require_once dirname( __FILE__ ) . '/includes/class-microsub-posts.php';
		Yarns_Microsub_Posts::init();


		// Functions to generate responses to endpoint queries
		require_once dirname( __FILE__ ) . '/includes/functions-microsub-actions.php';
		//add_action( 'init', array( 'Yarns_Microsub_Endpoint', 'init' ) );

		
		// list of various public helper functions
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		//Set up cron job to check for posts
		if ( !wp_next_scheduled( 'yarns_reader_generate_hook' ) ) {            
			wp_schedule_event( time(), 'sixtymins', 'yarns_reader_generate_hook' );
		}

		
		
	}

	
	

	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}