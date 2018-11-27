<?php

/**
 *  Yarns_Microsub_Admin Class
 *
 *  HTML and functions for the admin screen.
 */
class Yarns_Microsub_Admin {

	/**
	 *  Initialize the admin screen by adding actions for ajax calls.
	 */
	public static function init() {
		add_action( 'wp_ajax_save_filters', array( 'Yarns_Microsub_Channels', 'save_filters' ) );
		add_action( 'wp_ajax_get_options', array( 'Yarns_Microsub_Admin', 'yarns_channel_options' ) );
		add_action( 'wp_ajax_find_feeds', array( 'Yarns_Microsub_Admin', 'find_feeds' ) );
		add_action( 'wp_ajax_follow_feed', array( 'Yarns_Microsub_Admin', 'follow_feed' ) );
		add_action( 'wp_ajax_unfollow_feed', array( 'Yarns_Microsub_Admin', 'unfollow_feed' ) );
		add_action( 'wp_ajax_add_channel', array( 'Yarns_Microsub_Admin', 'add_channel' ) );
		add_action( 'wp_ajax_update_channel', array( 'Yarns_Microsub_Admin', 'update_channel' ) );
		add_action( 'wp_ajax_delete_channel', array( 'Yarns_Microsub_Admin', 'delete_channel' ) );
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
		wp_enqueue_script( 'yarns_microsub_server_js', plugin_dir_url( __FILE__ ) . '../js/yarns_microsub_server.js', array( 'jquery' ), null, true );

		// also enqueue the css for the yarns_reader_admin page in the dashboard.
		wp_enqueue_style( 'yarns_microsub_server_admin_css', plugin_dir_url( __FILE__ ) . '../css/yarns_microsub_server_admin.css' );
		wp_localize_script( 'yarns_microsub_server_js', 'yarns_microsub_server_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
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
				'yarns_microsub_options',
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
				'yarns_microsub_options',
				array(
					'Yarns_Microsub_Admin',
					'yarns_settings_html',
				)
			);
		}

		add_submenu_page(
			'settings.php',
			'Yarns Microsub Server settings',
			'Yarns Microsub Server ',
			'manage_options',
			'yarns_settings',
			'yarns_settings_html',
			plugin_dir_url( __FILE__ ) . 'images/icon_wporg.png',
			20
		);
	}


	/**
	 * Add link to Yarns Microsub Server to the IndieWeb menu, or to the Options menu if indieweb is not installed
	 */
	public static function yarns_settings_html() {
		// check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class='wrap'>
		<div id='yarns-admin-area'>
			<div>
				<div id="yarns-logo">
					<img src="<?php echo esc_url( plugins_url( '../images/yarns_logo.png', __FILE__ ) ); ?>"
						 alt="Yarns">
					<span id="yarns-subheading">Microsub Server</span>
				</div>


				<div id='yarns-sidebar'>
					<h2> Channels </h2>
					<ul id='yarns-channels'>
						<?php echo static::yarns_list_channels(); ?>
					</ul>
					<input id="yarns-new-channel-name" type="text" placeholder="New channel name">
					<button id="yarns-channel-add">+ Add channel</button>

				</div>
				<div id='yarns-channel-options'>
				</div>
				<div id='yarns-feeds'>
				</div>

			</div><!--#yarns-admin-area-->

		</div>
		<?php
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
	 * Returns a list of channels. Includes options for each channel from static::yarns_channel_options
	 *
	 * @return string
	 */
	public static function yarns_list_channels() {
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
	private static function yarns_list_feeds( $channel ) {
		$html = '';
		if ( ! isset( $channel['items'] ) ) {
			$html = 'You are not following any feeds in this channel yet.';
		} else {
			$feeds = $channel['items'];

			foreach ( $feeds as $feed ) {
				if ( isset( $feed['url'] ) ) {
					$html .= '<li>';
					$html .= '<a href="' . urldecode( $feed['url'] ) . '" target="_blank">' . urldecode( $feed['url'] ) . '</a>';
					$html .= '<button class="yarns-unfollow"></button>';
					// todo: add a preview feed option.
					$html .= '</li>';
				} else {
					$html .= 'DEBUG: ' . wp_json_encode( $feed );
				}
			}
		}

		return $html;
	}

	/**
	 * Returns html for setting channel filters
	 *
	 * @param array $channel The channel.
	 *
	 * @return string
	 */
	private static function yarns_channel_filters( $channel ) {
		$filters_html = '';

		if ( ! isset( $channel['post-types'] ) ) {
			return;
		} else {
			$all_types     = Yarns_Microsub_Channels::all_post_types();
			$channel_types = $channel['post-types'];
			$filters_html .= '<span class="yarns-channel-filters">';
			$filters_html .= '<h3>Channel options</h3>';
			$filters_html .= '<p> Include the following types of posts from this feed: </p>';

			foreach ( $all_types as $type ) {
				$filters_html .= '<label><input type="checkbox" ';
				if ( in_array( $type, $channel_types, true ) ) {
					$filters_html .= 'checked';
				}
				$filters_html .= '>' . $type . '</input></label>';
			}
			$filters_html .= '<br><button class="yarns-channel-filters-save" data-uid="' . $channel['uid'] . '">Update</button>';
			$filters_html .= '</span>';
		}

		return $filters_html;
	}

	/**
	 * Echoes HTML for the channel options panel
	 */
	public static function yarns_channel_options() {
		if ( isset( $_POST['uid'] ) ) {
			$uid          = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$options_html = '';
			$channel      = Yarns_Microsub_Channels::get_channel( $uid );

			$options_html .= '<div id="yarns-channel-options" data-uid="' . $uid . '">';
			$options_html .= '<h2 id="yarns-option-heading">' . $channel['name'] . '</h2>';
			$options_html .= '<span id="yarns-channel-update-options">';
			$options_html .= '<input type="text" id="yarns-channel-update-input" name="yarns-channel-update-input" value="' . $channel['name'] . '" size="30" ></input>';
			$options_html .= '<button id ="yarns-channel-update-save" data-uid="' . $uid . '">Save</button>';
			$options_html .= '</span><!--#yarns-channel-update-options-->';

			$options_html .= '<span id="yarns-channel-update" >Rename channel</span>';
			$options_html .= '<span id="yarns-channel-delete" data-uid="' . $uid . '">Delete channel</span>';
			$options_html .= '<span id="yarns-options-uid">' . $uid . '</span>';

			$options_html .= static::yarns_channel_filters( $channel );

			$options_html .= '<div id="yarns-add-subscription"><h2>Follow a new site:</h2>';
			$options_html .= '<input type="text" id="yarns-URL-input" name="yarns-URL-input" value="" size="30"  placeholder = "Enter a URL to find its feeds."></input>';
			$options_html .= '<button id ="yarns-channel-find-feeds">Search</button>';
			$options_html .= '<div id="yarns-feed-picker-list"></div>';
			$options_html .= '</div><!--#yarns-add-subscription-->';

			$options_html .= '<div id="yarns-channel-feeds"><h2>Following:</h2>';
			$options_html .= '<ul id="yarns-following-list">';
			$options_html .= static::yarns_list_feeds( $channel );
			$options_html .= '</ul><!--#yarns-following-list-->';
			$options_html .= '</div><!--#yarns-channel-feeds-->';

			$options_html .= '</div><!--#yarns_channel_options-->';
			echo $options_html;
		}

		wp_die();
	}

	/**
	 * Searches for feeds at a URL, then outputs HTML for a list of feeds and a subscribe button.
	 */
	public static function find_feeds() {
		if ( isset( $_POST['query'] ) ) {
			$query = sanitize_text_field( wp_unslash( $_POST['query'] ) );

			$results = Yarns_Microsub_Parser::search( $query )['results'];

			if ( empty( $results ) ) {
				echo 'No feeds found';
				wp_die();
			}

			$html = '<h3>Select a feed to follow:</h3>';
			foreach ( $results as $result ) {
				$html .= '<label><input type="radio" name="yarns-feed-picker" value="' . $result['url'] . '">' . $result['url'] . '</label>';

			}
			$html .= '<button id ="yarns-channel-add-feed">Subscribe</button>';
			echo $html;
		}
		wp_die();
	}

	/**
	 * Follows single feed
	 *
	 * Echoes the channel JSON after it has been updated.
	 */
	public static function follow_feed() {
		if ( isset( $_POST['uid'] ) && isset( $_POST['url'] ) ) {
			$uid = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$url = sanitize_text_field( wp_unslash ( $_POST['url'] ) );
			Yarns_Microsub_Channels::follow( $uid, $url );
			$channel = Yarns_Microsub_Channels::get_channel( $uid );
			echo static::yarns_list_feeds( $channel );
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
			$channel = Yarns_Microsub_Channels::get_channel( $uid );
			echo static::yarns_list_feeds( $channel );
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
			$channel = sanitize_text_field( wp_unslash( $_POST['channel'] ) );
			Yarns_Microsub_Channels::add( $channel );
			echo static::yarns_list_channels();
		}
		wp_die();
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
			echo static::yarns_list_channels();
		}
		wp_die();
	}

	/**
	 * Delete a channel.
	 *
	 * Echoes an updated list of channels.
	 *
	 */
	public static function delete_channel() {
		if ( isset( $_POST['uid'] ) ) {
			$uid = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			Yarns_Microsub_Channels::delete( $uid );
			echo static::yarns_list_channels();
		}
		wp_die();
	}
}