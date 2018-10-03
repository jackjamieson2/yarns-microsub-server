<?php
/**
 * Plugin Name: Yarns Microsub Server
 * Plugin URI: https://github.com/jackjamieson2/yarns-microsub-server
 * Description: Run a Microsub server on your WordPress site. This plugin allows you to follow and reply to many different kinds of websites using a Microsub client (like alltogether.now.io or monocole.p3k.io).  Still in development.
 * Author: Jack Jamieson
 * Author URI: https://jackjamieson.net
 * Version: 0.1 (alpha)
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: yarns_microsub
 * Domain Path: /languages
 */

//require_once dirname( __FILE__ ) . '/vendor/autoload.php';


add_action( 'plugins_loaded', array( 'Yarns_MicroSub_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Yarns_MicroSub_Plugin', 'init' ) );


/* Functions to run upon installation */
//register_activation_hook(__FILE__,'yarns_reader_install');
/* Functions to run upon deactivation */
register_deactivation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'deactivate' ) );

//register_activation_hook(__FILE__,'yarns_reader_create_tables');


class Yarns_MicroSub_Plugin {

	public static function plugins_loaded() {
		
		
		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}
		
		
		// Parse This
		if ( ! class_exists ( 'Parse_This_MF2') ) {
			require_once plugin_dir_path(__FILE__) . '/vendor/parse-this/parse-this.php';
		}
		
	}

	/**
	* To be run on deactivation
	*/
	public static function deactivate() {
		// Disable the aggregation cron job
		if ( wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_clear_scheduled_hook( 'yarns_microsub_server_cron' );
		}
	}

	/**
	 * Initialize Yarns Microsub Server plugin Plugin
	 */
	public static function init() {

		// Initialize Microsub endpoint
		require_once dirname( __FILE__ ) . '/includes/class-microsub-endpoint.php';
		Yarns_Microsub_Endpoint::init();
		
		// Initialize Microsub posts
		require_once dirname( __FILE__ ) . '/includes/class-microsub-posts.php';
		Yarns_Microsub_Posts::init();

		// Class: channels
		require_once dirname( __FILE__ ) . '/includes/class-microsub-channels.php';

		// Class: Parser
		require_once dirname( __FILE__ ) . '/includes/class-microsub-parser.php';

		// Class: Aggregator
		require_once dirname( __FILE__ ) . '/includes/class-microsub-aggregator.php';



		// list of various public helper functions
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		//Set up cron job to check for posts
		if ( ! wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'yarns_microsub_server_cron' );
		}
		add_action( 'yarns_microsub_server_cron', array( 'Yarns_Microsub_Aggregator', 'poll' ) );

	}




	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'yarns_microsub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}
