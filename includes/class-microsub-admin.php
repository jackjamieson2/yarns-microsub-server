<?php

// For debugging purposes this will set all Microsub posts to Draft
if ( ! defined( 'MICROSUB_DRAFT_MODE' ) ) {
	define( 'MICROSUB_DRAFT_MODE', '0' );
}

add_action( 'plugins_loaded', array( 'Microsub_Admin', 'init' ), 10 );
add_action( 'admin_menu', array( 'Microsub_Admin', 'admin_menu' ) );

/**
 * Microsub Admin Class
 */
class Microsub_Admin {
	/**
	 * Initialize the admin screens.
	 */
	public static function init() {
		$cls = get_called_class();

		add_action( 'admin_init', array( $cls, 'admin_init' ) );

		// Register Setting
		register_setting(
			'microsub',
			'microsub_default_post_status', // Setting Name
			array(
				'type'              => 'string',
				'description'       => 'Default Post Status for Microsub Server',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			)
		);
	}

	public static function admin_init() {
		$cls  = get_called_class();
		$page = 'microsub';
		add_settings_section(
			'microsub_writing',
			'Microsub Writing Settings',
			array( 'Microsub_Admin', 'writing_settings' ),
			$page
		);

		add_settings_field(
			'microsub_default_post_status',
			__( 'Default Status for Microsub Posts', 'microsub' ),
			array( $cls, 'default_post_status_setting' ),
			$page,
			'microsub_writing'
		);
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$title = 'Microsub';
		$cls   = get_called_class();
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			$options_page = add_submenu_page(
				'indieweb',
				$title,
				$title,
				'manage_options',
				'microsub',
				array( $cls, 'settings_page' )
			);
		} else {
			$options_page = add_options_page(
				$title,
				$title,
				'manage_options',
				'microsub',
				array( $cls, 'settings_page' )
			);
		}

	}

	public static function settings_page() {
		load_template( plugin_dir_path( __DIR__ ) . 'templates/microsub-settings.php' );
	}

	public static function writing_settings() {
		echo 'Default Settings for the Writing of Posts';
	}

	public static function default_post_status_setting() {
		load_template( plugin_dir_path( __DIR__ ) . 'templates/microsub-post-status-setting.php' );
	}
}
