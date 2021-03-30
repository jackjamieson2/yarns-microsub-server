<?php

/**
 *  Yarns_Microsub_Admin Class
 *
 *  HTML and functions for the admin screen.
 */
class Yarns_Microsub_Admin {


	/**
	 * Stores the page query var for Yarns' options page.
	 *
	 * @var string
	 */
	public static $options_page_name = 'yarns_microsub_options';

	public static function admin_page_link( $args = array() ) {
		$default_args = array(
			'page' => static::$options_page_name,
		);

		$args = array_merge( $default_args, $args );

		return add_query_arg( $args, admin_url() . 'admin.php' );
	}

	public static function admin_channel_settings_link( $uid, $args = array() ) {
		$default_args = array(
			'mode'    => 'channel-settings',
			'channel' => $uid,
		);
		// merge additional args if defined.
		$args = array_merge( $default_args, $args );

		return static::admin_page_link( $args );
	}

	public static function admin_channel_feeds_link( $uid, $args = array() ) {
		$default_args = array(
			'mode'    => 'channel-feeds',
			'channel' => $uid,
		);
		// merge additional args if defined.
		$args = array_merge( $default_args, $args );

		return static::admin_page_link( $args );
	}


	/**
	 *  Initialize the admin screen by adding actions for ajax calls.
	 */
	public static function init() {
		add_action( 'wp_ajax_save_filters', array( 'Yarns_Microsub_Channels', 'save_filters' ) );
		add_action( 'wp_ajax_save_options', array( 'Yarns_Microsub_Admin', 'save_options' ) );
		add_action( 'wp_ajax_find_feeds', array( 'Yarns_Microsub_Admin', 'find_feeds' ) );
		add_action( 'wp_ajax_preview_feed', array( 'Yarns_Microsub_Admin', 'preview_feed' ) );
		add_action( 'wp_ajax_follow_feed', array( 'Yarns_Microsub_Admin', 'follow_feed' ) );
		add_action( 'wp_ajax_unfollow_feed', array( 'Yarns_Microsub_Admin', 'unfollow_feed' ) );
		add_action( 'wp_ajax_add_channel', array( 'Yarns_Microsub_Admin', 'add_channel' ) );
		add_action( 'wp_ajax_update_channel', array( 'Yarns_Microsub_Admin', 'update_channel' ) );
		add_action( 'wp_ajax_delete_channel', array( 'Yarns_Microsub_Admin', 'delete_channel' ) );
		add_action( 'wp_ajax_order_channels', array( 'Yarns_Microsub_Admin', 'order_channels' ) );
		add_action( 'wp_ajax_delete_posts', array( 'Yarns_Microsub_Posts', 'delete_all_posts' ) );
		add_action( 'wp_ajax_force_poll', array( 'Yarns_Microsub_Aggregator', 'force_poll' ) );

		add_filter( 'query_vars', array( 'Yarns_Microsub_Admin', 'add_query_vars_filter' ) );
	}

	public static function add_query_vars_filter( $vars ) {
		$vars[] = 'channel';
		$vars[] = 'mode';

		return $vars;
	}

	/**
	 * Enqueue scripts and styles for the yarns-microsub-admin page
	 *
	 * @param string $hook string.
	 */
	public static function yarns_microsub_admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_yarns_microsub_options' !== $hook && 'indieweb_page_yarns_microsub_options' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'yarns_microsub_server_js', plugin_dir_url( __FILE__ ) . '../js/yarns_microsub_server.js', array( 'jquery' ), Yarns_MicroSub_Plugin::$version, true );

		// also enqueue the css for the yarns_reader_admin page in the dashboard.
		wp_enqueue_style( 'yarns_microsub_server_admin_css', plugin_dir_url( __FILE__ ) . '../css/yarns_microsub_server_admin.css', array(), Yarns_MicroSub_Plugin::$version );
		wp_localize_script( 'yarns_microsub_server_js', 'yarns_microsub_server_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

		// Add required jquery-ui scripts
		wp_enqueue_script( 'jquery-ui-sortable' );
	}


	/**
	 *
	 * Add link to Yarns Microsub Server to the IndieWeb menu, or to the Options menu if indieweb is not installed
	 */
	public static function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Yarns Microsub Server', 'yarns-microsub-server' ), // page title.
				__( 'Yarns Microsub Server', 'yarns-microsub-server' ), // menu title.
				'manage_options', // access capability.
				static::$options_page_name,
				array( 'Yarns_Microsub_Admin', 'yarns_settings_html' )
			);
		} else {
			add_options_page(
				'',
				__(
					'Yarns Microsub Server',
					'yarns-microsub-server'
				),
				'manage_options',
				static::$options_page_name,
				array(
					'Yarns_Microsub_Admin',
					'yarns_settings_html',
				)
			);
		}
	}


	/**
	 * Base HTML template for admin screen
	 */
	public static function yarns_settings_html() {
		// check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include plugin_dir_path( __DIR__ ) . 'templates/yarns-microsub-admin-template.php';

	}


	/**
	 * Return array of accepted options
	 */
	private static function valid_options() {
		return array(
			'storage_period',
			'show_debug',
		);
	}

	/**
	 * Save general options
	 */
	public static function save_options() {
		if ( ! isset( $_POST['options'] ) ) {
			echo 'No options submitted';
			wp_die();
		} else {
			$options = $_POST['options'];

			$results = '';
			// Remove any unsupported option arguments.
			if ( is_array( $options ) ) {
				foreach ( $options as $key => $option ) {
					if ( ! in_array( $key, static::valid_options(), true ) ) {
						unset( $options[ $key ] );
					}
				}
			}

			// Validate individual options, and save them if valid.
			if ( isset( $options['storage_period'] ) ) {
				if ( (int) $options['storage_period'] >= 1 ) { // Validate that the value is an integer > 0.
					update_option( 'yarns_storage_period', (int) $options['storage_period'] );
					$results .= 'updated storage period.  ';
				}
			}

			if ( isset( $options['show_debug'] ) ) {
				$show_debug = 'true' === $options['show_debug'] ? true : false;
				update_option( 'yarns_show_debug', $show_debug );
				$results .= 'updated show_debug.  ';
			}
			echo wp_kses_post( $results );
			wp_die();
		}

	}


	/**
	 * Returns HTML with form for adding new channels
	 *
	 * @return string
	 */
	public static function yarns_add_channel_html() {

		$channels = Yarns_Microsub_Channels::get( true )['channels'];

		$html = '';

		foreach ( $channels as $channel ) {
			$name  = $channel['name'];
			$uid   = $channel['uid'];
			$html .= '<li class="yarns-channel" data-uid="' . $uid . '""><span>' . $name . '</span>';
			$html .= '</li>';
		}

		return $html;
	}

	/**
	 * Lists all of the feeds for a specific channel
	 *
	 * @param array $channel The channel to be displayed.
	 *
	 * @return string
	 */
	public static function list_feeds( $channel ) {
		$feed_table = new Yarns_Microsub_Feed_List_Table();
		$feed_table->set_channel( $channel['uid'] );
		$feed_table->prepare_items();
		$feed_table->display();
	}

	/**
	 * Returns a list of channels
	 */
	public static function list_channels() {
		$channel_table = new Yarns_Microsub_Channel_List_Table();
		$channel_table->prepare_items();
		$channel_table->display();
	}


	/**
	 * Echoes HTML for the debug log
	 */
	private static function debug_log() {
		$html  = '<h2> Debug log </h2>';
		$html .= '<div id="yarns-debug-log"><pre>';

		$log = json_decode( get_option( 'debug_log' ), true );
		if ( is_array( $log ) ) {
			foreach ( $log as $item ) {
				$html .= htmlspecialchars( $item ) . '<br>';
			}
		} else {
			$html = htmlspecialchars( $log );
		}

		$html .= '</pre></div>';

		return $html;

	}

	/**
	 * Echoes HTML for debug commands.
	 */
	private static function debug_commands() {
		$html  = '<h2> Debug commands </h2>';
		$html .= '<a class="button" id="yarns_force_poll">Force poll</a><br><br>';
		$html .= '<a class="button" id="yarns_delete_posts">Delete all posts</a>';

		return $html;
	}

	/**
	 * Searches for feeds at a URL, then outputs HTML for a list of feeds and a subscribe button.
	 */
	public static function find_feeds() {
		if ( isset( $_POST['query'] ) ) {
			$query = sanitize_text_field( wp_unslash( $_POST['query'] ) );

			$response = Yarns_Microsub_Parser::search( $query );
			$response = static::validate_results( $response, 'search' );

			if ( $response['error'] ) {
				echo wp_json_encode( $response );
				wp_die();
			} else {
				$html = '<h3>Select a feed to follow:</h3>';
				foreach ( $response['content']['results'] as $result ) {
					$html .= '<label><input type="radio" name="yarns-feed-picker" value="' . $result['url'] . '">' . $result['url'] . '</label>';
				}
				$html .= '<a class="button" id ="yarns-channel-preview-feed">Preview</a>';
				$html .= '<a class="button" id ="yarns-channel-add-feed">Subscribe</a>';

				echo wp_json_encode(
					array(
						'error'   => false,
						'content' => $html,
					)
				);
				wp_die();
			}
		}
	}

	private static function validate_results( $response, $action ) {
		// General error reporting.
		if ( is_wp_error( $response ) ) {
			return array(
				'error'   => true,
				'content' => $response->get_error_message(),
			);
		}

		// Additional errors for specific actions.
		switch ( $action ) {
			case 'search':
				// Check if the results are empty.
				if ( empty( $response['results'] ) ) {
					return array(
						'error'   => true,
						'content' => 'No feeds were found.',
					);
				}
		}

		// If there were no errors, return the validated results.
		return array(
			'error'   => false,
			'content' => $response,
		);
	}

	/**
	 * Follows single feed
	 *
	 * Echoes the channel JSON after it has been updated.
	 */
	public static function follow_feed() {
		// @@todo: Return an error message if the feed is already followed in this channel.
		// @@todo: Return success message when fo
		if ( isset( $_POST['uid'] ) && isset( $_POST['url'] ) ) {
			$uid = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$url = sanitize_text_field( wp_unslash( urldecode( $_POST['url'] ) ) );
			Yarns_Microsub_Channels::follow( $uid, $url );
			//$channel = Yarns_Microsub_Channels::get_channel( $uid );
			echo esc_url( static::admin_channel_feeds_link( $uid ) );
			//echo static::yarns_list_feeds( $channel );
		}

		wp_die();
	}

	/**
	 * Unfollows a single feed.
	 * Echoes the channel JSON after it has been updated.
	 */
	public static function unfollow_feed() {
		if ( isset( $_POST['uid'] ) && isset( $_POST['url'] ) ) {
			$uid = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$url = sanitize_text_field( wp_unslash( $_POST['url'] ) );
			Yarns_Microsub_Channels::follow( $uid, $url, $unfollow = true );
			//$channel = Yarns_Microsub_Channels::get_channel( $uid );
			echo esc_url( static::admin_channel_feeds_link( $uid ) );

			//echo static::yarns_list_feeds( $channel );
		}
		wp_die();
	}

	/**
	 * Add a new channel
	 *
	 * Echoes an updated list of channels.
	 */
	public static function add_channel() {
		if ( isset( $_POST['channel'] ) ) {
			$new_channel = sanitize_text_field( wp_unslash( $_POST['channel'] ) );
			// Return message if channel already exists
			$channels = json_decode( get_option( 'yarns_channels' ) );
			// check if the channel already exists.
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->name === $new_channel ) {
						wp_die( wp_json_encode( new WP_Microsub_Error( 'invalid_request', sprintf( 'A channel with name %1$s already exists', $new_channel ), 400 ) ) );
					}
				}
			}
			Yarns_Microsub_Channels::add( $new_channel );
			wp_die( wp_json_encode( new WP_HTTP_Response( 'Successfully added new channel', 200 ) ) );
		}
		wp_die( wp_json_encode( new WP_Microsub_Error( 'invalid_request', 'No channel name was submitted', 400 ) ) );

	}

	/**
	 * Update a channel with a new name.
	 *
	 * Echoes an updated list of channels.
	 */
	public static function update_channel() {
		if ( isset( $_POST['uid'] ) && isset( $_POST['channel'] ) ) {
			$uid     = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$channel = sanitize_text_field( wp_unslash( $_POST['channel'] ) );
			Yarns_Microsub_Channels::update( $uid, $channel );
			echo wp_kses_post( static::list_channels() );
		}
		wp_die();
	}

	/**
	 * Delete a channel.
	 *
	 * Echoes an updated list of channels.
	 */
	public static function delete_channel() {
		if ( isset( $_POST['uid'] ) ) {
			$uid = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			Yarns_Microsub_Channels::delete( $uid );
			echo wp_kses_post( static::list_channels() );
		}
		wp_die();
	}

	/**
	 * Echoes a preview of a feed.
	 */
	public static function preview_feed( $url = null ) {
		if ( ! $url ) {
			if ( isset( $_POST['url'] ) ) {
				$url = sanitize_text_field( wp_unslash( $_POST['url'] ) );
			}
		}

		$preview_data = Yarns_Microsub_Parser::preview( $url );
		//echo wp_json_encode($preview_data);
		//wp_die();

		$preview      = new Yarns_Microsub_Preview( $preview_data );
		$preview_html = $preview->html();
		echo wp_kses_post( $preview_html );

		wp_die();
	}


	/**
	 * Reorder channels
	 */

	public static function order_channels() {
		if ( isset( $_POST['channel_order'] ) ) {
			//$channel_order = wp_json_encode($_POST['channel_order'] );
			$channel_order = wp_unslash( $_POST['channel_order'] );
			$response      = Yarns_Microsub_Channels::order( $channel_order );
			if ( $response ) {
				echo 'Updated channel order.';
			}
		}
		wp_die();
	}

}
