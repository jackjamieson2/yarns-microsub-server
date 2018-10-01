<?php


Class Yarns_Microsub_Admin {
	
	public static function init() {
		
		
		add_action( 'wp_ajax_save_filters', array( 'Yarns_Microsub_Channels', 'save_filters' ) );
		add_action( 'wp_ajax_get_options', array( 'Yarns_Microsub_Admin', 'yarns_channel_options' ) );
		add_action( 'wp_ajax_find_feeds', array( 'Yarns_Microsub_Admin', 'find_feeds' ) );
		add_action( 'wp_ajax_follow_feed', array( 'Yarns_Microsub_Admin', 'follow_feed' ) );

	}
	
	/**
	 * Enqueue scripts and styles for the yarns-microsub-admin page
	 *
	 * @param $hook string
	 */
	public static function yarns_microsub_admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_yarns_microsub_options' !== $hook && 'indieweb_page_yarns_microsub_options' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'yarns_microsub_server_js', plugin_dir_url( __FILE__ ) . '../js/yarns_microsub_server.js', array( 'jquery' ), null, true );
		
		// also enqueue the css for the yarns_reader_admin page in the dashboard
		wp_enqueue_style( 'yarns_microsub_server_admin_css', plugin_dir_url( __FILE__ ).'../css/yarns_microsub_server_admin.css' );
		
		wp_localize_script( 'yarns_microsub_server_js', 'yarns_microsub_server_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' )
			)
		);
		
		
		
		//add_action( 'wp_ajax_save_options', 'save_options' );
		//add_action( 'wp_ajax_save_options', array( 'Yarns_Microsub_Channels', 'save_options' ) );
		
		
		
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
				__( 'Yarns Microsub Server', 'yarns-microsub-server' ), // page title
				__( 'Yarns Microsub Server', 'yarns-microsub-server' ), // menu title
				'manage_options', // access capability
				'yarns_microsub_options',
				array( 'Yarns_Microsub_Admin', 'yarns_settings_html' )
			);
		} else {
			add_options_page( '', __( 'Yarns Microsub Server', 'yarns-microsub-server' ), 'manage_options', 'yarns_microsub_options', array(
				'Yarns_Microsub_Admin',
				'yarns_settings_html',
			) );
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
		<div id='yarns-admin-area' data-test='this is a test'>
			<div>
				<h1>Yarns Microsub Server</h1>
				<h1> Manage channels </h1>
				<div id='yarns-channels'>
					<?php echo static::yarns_list_channels(); ?>
				</div>
				<div id='yarns-channel-options'>
					<?php //echo static::yarns_channel_options(); ?>
				</div>
				<div id='yarns-feeds'>
				</div>

			</div><!--#yarns-admin-area-->

		</div>
		<?php
	}
	
	/**
	 * Returns a list of channels. Includes options for each channel from static::yarns_channel_options
	 *
	 * @return string
	 */
	public static function yarns_list_channels() {
		$channels = Yarns_Microsub_Channels::get( $details = true )['channels'];
		
		$html = '';
		
		foreach ( $channels as $channel ) {
			$name = $channel['name'];
			$uid  = $channel['uid'];
			$html .= '<div class="yarns-channel" data-uid="' . $uid . '""><h2>' . $name . '</h2>';
			//$html .= static::yarns_channel_filters( $channel );
			//$html .= static::yarns_list_feeds( $channel );
			$html .= '</div>';
		}
		
		return $html;
	}
	
	/**
	 * Lists all of the feeds for a specific channel
	 *
	 * @param $channel
	 */
	private static function yarns_list_feeds( $channel ) {
		$html = '';
		
		if ( ! isset( $channel['items'] ) ) {
			$html = 'You are not following any feeds in this channel yet.';
		} else {
			$html .= '<div id="yarns-channel-feeds"><h2>Following:</h2>';
			
			$feeds = $channel['items'];
			foreach ( $feeds as $feed ) {
				if ( isset( $feed['url'] ) ) {
					$html .= urldecode( $feed['url'] ) . '<br>';
				} else {
					$html .= 'DEBUG: ' . json_encode( $feed );
				}
			}
			$html .= '</div><!--#yarns-channel-feeds-->';
			
		}
		
		return $html;
	}
	
	
	
	/**
	 * Returns html for setting channel filters
	 *
	 * @param $channel
	 *
	 * @return string|void
	 */
	private static function yarns_channel_filters( $channel ) {
		
		
		$filters_html = '';
		
		
		
		if ( ! isset( $channel['post-types'] ) ) {
			return;
		} else {
			$all_types     = Yarns_Microsub_Channels::all_post_types();
			$channel_types = $channel['post-types'];
			$filters_html  .= '<span class="yarns-channel-filters">';
			$filters_html  .= '<p> Display the following types of posts from this feed: </p>';
			
			foreach ( $all_types as $type ) {
				$filters_html .= '<label><input type="checkbox" ';
				if ( in_array( $type, $channel_types, true ) ) {
					$filters_html .= 'checked';
				}
				$filters_html .= '>' . $type . '</input></label>';
			}
			$filters_html .= '<button class="yarns-channel-filters-save" data-uid="' . $channel['uid'] . '">Update</button>';
			$filters_html .= '</span>';
		}
		
		
		
		return $filters_html;
		
	}
	
	/**
	 * Returns HTML for the channel options panel
	 *
	 * @return string
	 */
	public static function yarns_channel_options() {
		error_log("eff");
		$uid     = sanitize_text_field( $_POST['uid'] );
		$options_html = '';
		
		$channel = Yarns_Microsub_Channels::get_channel($uid);
		
		
		
		
		$options_html .= '<div id="yarns-channel-options" data-uid="' . $uid . '">';
		$options_html .= '<h2 id="yarns-option-heading">' . $channel['name'] . '</h2>';
		$options_html .= '<span id="yarns-options-uid">' . $uid . '</span>';
		
		$options_html .= static::yarns_channel_filters($channel);
		
		
		$options_html .= '<div id="yarns-add-subscription"><h2>Add a subscription:</h2>';
			//input box here
		
		
		
		
		$options_html .='<input type="text" id="yarns-URL-input" name="yarns-URL-input" value="" size="30"  placeholder = "Enter a URL to find its feeds."></input>';
		$options_html .= '<button id ="yarns-channel-find-feeds">Search</button>';
		$options_html .= '<div id="yarns-feed-picker-list"></div>';
		$options_html .= '</div><!--#yarns-add-subscription-->';
		
		
		$options_html .= static::yarns_list_feeds($channel);
		
		$options_html .= '</div><!--#yarns_channel_options-->';
		
	
		echo $options_html;
		wp_die();
	}
	
	
	
	public static function find_feeds() {
		$query     = sanitize_text_field( $_POST['query'] );
		
		$results = Yarns_Microsub_Parser::search($query)['results'];
		
		if ( empty ($results) ) {
			echo "No feeds found";
			wp_die();
		}
		
		
		
		$html = '';
		foreach ( $results as $result ){
			$html .= '<label><input type="radio" name="yarns-feed-picker" value="' . $result['url'] . '">' . $result['url'] . '</label>';
			
		}
		$html .= '<button id ="yarns-channel-add-feed">Subscribe</button>';
		
		
		
		echo $html;
		wp_die();
	}
	
	public static function follow_feed() {
		$uid     = sanitize_text_field( $_POST['uid'] );
		$url     = sanitize_text_field( $_POST['url'] );
		$result = Yarns_Microsub_Channels::follow( $uid, $url );
		echo wp_json_encode($result);
		wp_die();
	}
	


	
}


