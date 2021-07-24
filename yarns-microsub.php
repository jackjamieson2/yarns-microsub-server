<?php
/**
 * Plugin Name: Yarns Microsub Server
 * Plugin URI: https://github.com/jackjamieson2/yarns-microsub-server
 * Description: Run a Microsub server on your WordPress site. This plugin allows you to follow and reply to many different kinds of websites using a Microsub client (like alltogethernow.io or monocle.p3k.io).
 * Author: Jack Jamieson
 * Author URI: https://jackjamieson.net
 * Version: 1.1.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yarns-microsub-server
 * Domain Path:  /languages
 *
 * @package Yarns_Microsub_Server
 */

if ( ! defined( 'YARNS_MICROSUB_NAMESPACE' ) ) {
		define( 'YARNS_MICROSUB_NAMESPACE', 'yarns-microsub/1.0' );
}

add_action( 'plugins_loaded', array( 'Yarns_MicroSub_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Yarns_MicroSub_Plugin', 'init' ) );

/* Functions to run upon activation */
register_activation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'activate' ) );

/* Functions to run upon deactivation */
register_deactivation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'deactivate' ) );

function load_microsub_error() {
	// Initialize Microsub Error Handling Class.
	require_once dirname( __FILE__ ) . '/includes/class-microsub-error.php';
}

// Load error class at the plugins loaded stage before auth is loaded
add_action( 'plugins_loaded', 'load_microsub_error', 29 );

// Add filter for polling cron job
add_filter( 'cron_schedules', array( 'Yarns_Microsub_Plugin', 'cron_definer' ) );

// Add actions to be triggered by cron hooks
add_action( 'yarns_microsub_server_cron', array( 'Yarns_Microsub_Aggregator', 'poll' ) );

/**
 * Class Yarns_MicroSub_Plugin
 */
class Yarns_MicroSub_Plugin {
	public static $version = '1.1.0';

	/**
	 * Run when plugins are loaded.
	 */
	public static function plugins_loaded() {
		// list of various public helper functions.
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}
		if ( class_exists( 'Parse_This' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'lib/parse-this/includes/autoload.php';
		}
	}

	/**
	 * To be run on deactivation
	 */
	public static function deactivate() {
		// Disable the aggregation cron job.
		if ( wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_clear_scheduled_hook( 'yarns_microsub_server_cron' );
		}

		// Remove all stored posts.
		Yarns_Microsub_Posts::delete_all_posts();
	}

	/**
	 * To be run on activation
	 */
	public static function activate() {
		// Set up cron job to check for posts.
		if ( ! wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_schedule_event( time(), '15mins', 'yarns_microsub_server_cron' );
		}

		// Set defaults for general options.
		static::set_yarns_defaults();
	}

	/**
	 * Saves default options if they havent' been set previously.
	 */
	private static function set_yarns_defaults() {
		// Set default period for storing aggregated posts.
		if ( ! get_option( 'yarns_storage_period' ) ) {
			update_option( 'yarns_storage_period', 14 );  // in days.
		}

		// Set debug to false.
		if ( ! get_option( 'yarns_show_debug' ) ) {
			update_option( 'yarns_show_debug', false );  // in days.
		}
	}

	public static function indieauth_not_installed_notice() {
		?>
		<div class="notice notice-error">
			<p>To use Microsub, you must have IndieAuth support. Please install the IndieAuth plugin.</p>
		</div>
		<?php
	}

	/**
	 * Initialize Yarns Microsub Server plugin Plugin
	 */
	public static function init() {

		if ( ! class_exists( 'IndieAuth_Plugin' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'indieauth_not_installed_notice' ) );
			return;
		}

		// Initialize Microsub endpoint.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-endpoint.php';
		Yarns_Microsub_Endpoint::init();

		// Initialize Microsub posts.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-posts.php';
		Yarns_Microsub_Posts::init();

		// Class: channels.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-channels.php';

		// Class: Parser.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-parser.php';

		// Class: Aggregator.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-aggregator.php';

		// Class: Admin.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-admin.php';
		add_action( 'admin_menu', array( 'Yarns_Microsub_Admin', 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( 'Yarns_Microsub_Admin', 'yarns_microsub_admin_enqueue_scripts' ) );
		Yarns_Microsub_Admin::init();

		// Class: Preview.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-preview.php';

		// Class: Channel List Table.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-channel-list-table.php';

		// Class: Feed List Table.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-feed-list-table.php';

		// Parse This
		require_once plugin_dir_path( __FILE__ ) . 'lib/parse-this/includes/autoload.php';
		require_once plugin_dir_path( __FILE__ ) . 'lib/parse-this/includes/functions.php';

		// Display nag notice if IndieAuth Plugin is not installed
		add_action( 'admin_notices', array( 'Yarns_MicroSub_Plugin', 'indieauth_plugin_notice' ) );

	}


	/**
	 * Defines the interval for the cron job (15 minutes).
	 *
	 * @param array $schedules
	 *
	 * @return mixed
	 */
	public static function cron_definer( $schedules ) {

		$schedules['15mins'] = array(
			'interval' => 900,
			'display'  => __( 'Once Every 15 Minutes', 'yarns-microsub-server' ),
		);

		return $schedules;
	}

	/**
	 * Save debug logs
	 *
	 * @param string $message Message to be written to the log.
	 */
	public static function debug_log( $message ) {
		if ( get_option( 'debug_log' ) ) {
			$debug_log = json_decode( get_option( 'debug_log' ), true );
		} else {
			$debug_log = array();
		}

		$debug_entry = date( 'Y-m-d H:i:s' ) . '  ' . $message;

		if ( is_array( $debug_log ) && ! empty( $debug_log ) ) {
			array_unshift( $debug_log, $debug_entry ); // Add item to start of array.
			$debug_log = array_slice( $debug_log, 0, 30 ); // Limit log length to 30 entries.
		} else {
			$debug_log[] = $debug_entry;
		}
		update_option( 'debug_log', wp_json_encode( $debug_log ) );
	}

	/**
	 * Display nag notice if IndieAuth plugin is not installed
	 *
	 * @return string
	 */
	public static function indieauth_plugin_notice() {
		if ( ! class_exists( 'IndieAuth_Plugin' ) ) {
			$class   = 'notice notice-error';
			$message = __( '<b>Yarns Microsub Server notice:</b> WordPress IndieAuth Plugin is not active. Yarns Microsub Server requires this plugin to authorize microsub clients.', 'yarns-microsub-server' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		}
	}

}
