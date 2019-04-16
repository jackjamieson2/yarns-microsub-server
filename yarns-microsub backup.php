<?php
/**
 * Plugin Name: Yarns Microsub Server
 * Plugin URI: https://github.com/jackjamieson2/yarns-microsub-server
 * Description: Run a Microsub server on your WordPress site. This plugin allows you to follow and reply to many different kinds of websites using a Microsub client (like alltogether.now.io or monocole.p3k.io).  Still in development.
 * Author: Jack Jamieson
 * Author URI: https://jackjamieson.net
 * Version: 0.1.5 (beta)
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: yarns_microsub
 * Domain Path: /languages
 *
 * @package Yarns_Microsub_Server
 */

if ( ! defined( 'MICROSUB_NAMESPACE' ) ) {
	define( 'MICROSUB_NAMESPACE', 'yarns-microsub/1.0' );
}

if ( ! defined( 'MICROSUB_DISABLE_NAG' ) ) {
	define( 'MICROSUB_DISABLE_NAG', 0 );
}

if ( ! defined( 'MICROSUB_LOCAL_AUTH' ) ) {
	define( 'MICROSUB_LOCAL_AUTH', 0 );
}


// Global functions.
require_once dirname( __FILE__ ) . '/includes/functions.php';

// Class: Admin.        Admin menu and UI.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-admin.php';


function load_microsub_auth() {
	// Always disable local auth when the IndieAuth Plugin is installed.
	if ( class_exists( 'IndieAuth_Plugin' ) ) {
		return;
	}
	// If this configuration option is set to 0 then load this file.
	if ( 0 === MICROSUB_LOCAL_AUTH ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-yarns-microsub-authorize.php';
	}
}

add_action( 'plugins_loaded', 'load_microsub_auth', 20 );



// Class: Error.        Error handling.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-yarns-microsub-error.php';

// Class: Endpoint.     Microsub endpoint.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-endpoint.php';

// Class: Posts.        Read and write aggregated feeds as posts.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-posts.php';


// Class: Channels.     Return and modify stored channels.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-channels.php';

// Class: Parser.       Parse URLs during preview and aggregation.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-parser.php';

// Class: Aggregator.   Aggregate content from Feed URLs (polling functions).
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-aggregator.php';

// Class: Preview.      Construct HTML for feed previews.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-preview.php';

// Class: Channel List Table.       Construct list table to display channels.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-channel-list-table.php';

// Class: Feed List Table.          Construct list table to display feeds within a channel.
require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-feed-list-table.php';



/* Functions to run upon activation */
register_activation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'activate' ) );

/* Functions to run upon deactivation */
register_deactivation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'deactivate' ) );




/**
 * Class Yarns_MicroSub_Plugin
 */
class Yarns_MicroSub_Plugin {

	/**
	 * To be run on deactivation
	 */
	public static function deactivate() {
		// Disable the aggregation cron job.
		if ( wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_clear_scheduled_hook( 'yarns_microsub_server_cron' );
		}
	}


	/**
	 * To be run on activation
	 */
	public static function activate() {
		// Set up cron job to check for posts.
		add_filter( 'cron_schedules', array( 'Yarns_Microsub_Plugin', 'cron_definer' ) );
		if ( ! wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_schedule_event( time(), '15mins', 'yarns_microsub_server_cron' );
		}
		add_action( 'yarns_microsub_server_cron', array( 'Yarns_Microsub_Aggregator', 'poll' ) );

		// Set default period for storing aggregated posts.
		if ( ! get_site_option( 'yarns_storage_period' ) ) {
			update_option( 'yarns_storage_period', 14 );  // in days.
		}
	}








	/**
	 * Defines the interval for the cron job (15 minutes).
	 *
	 * @param array $schedules
	 *
	 * @return mixed
	 */
	public static function cron_definer($schedules){
		$schedules['15mins'] = array(
			'interval' => 900,
			'display'  => __( 'Once Every 15 Minutes' ),
		);
		return $schedules;
	}


	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'yarns_microsub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}



}

