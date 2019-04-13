<?php
add_action( 'plugins_loaded', array( 'Yarns_Microsub_Endpoint', 'init' ) );


/**
 * Microsub Endpoint Class
 */
class Yarns_Microsub_Endpoint {
	// associative array
	public static $request_headers;

	// associative array, read from JSON or form-encoded input. populated by load_input().
	protected static $input;

	// file array populated by load_input
	protected static $files;

	// associative array, populated by authorize().
	protected static $microsub_auth_response = array();

	// Array of Scopes
	protected static $scopes = array();

	/**
	 * Initialize the plugin.
	 */
	public static function init() {

		$cls = get_called_class();

		// endpoint discovery
		add_action( 'wp_head', array( $cls, 'microsub_html_header' ), 99 );
		add_action( 'send_headers', array( $cls, 'microsub_http_header' ) );
		add_filter( 'host_meta', array( $cls, 'microsub_jrd_links' ) );
		add_filter( 'webfinger_user_data', array( $cls, 'microsub_jrd_links' ) );

		// register endpoint
		add_action( 'rest_api_init', array( $cls, 'register_route' ) );

		add_filter( 'rest_request_after_callbacks', array( $cls, 'return_microsub_error' ), 10, 3 );

	}

	public static function return_microsub_error( $response, $handler, $request ) {
		if ( '/microsub/1.0/endpoint' !== $request->get_route() ) {
			return $response;
		}
		if ( is_wp_error( $response ) ) {
			return microsub_wp_error( $response );
		}
		return $response;
	}

	public static function log_error( $message, $name = 'Microsub' ) {
		if ( empty( $message ) ) {
			return false;
		}
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = wp_json_encode( $message );
		}

		return error_log( sprintf( '%1$s: %2$s', $name, $message ) ); // phpcs:ignore
	}

	public static function get( $array, $key, $default = array() ) {
		if ( is_array( $array ) ) {
			return isset( $array[ $key ] ) ? $array[ $key ] : $default;
		}
		return $default;
	}

	public static function register_route() {
		$cls = get_called_class();
		register_rest_route(
			MICROSUB_NAMESPACE,
			'/endpoint',
			array(
				array(
					'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ),
					'callback'            => array( $cls, 'serve_request' ),
					//'permission_callback' => array( $cls, 'check_request_permissions' ),
				),
			)
		);
	}

	public static function load_auth() {
		static::$microsub_auth_response = apply_filters( 'indieauth_response', static::$microsub_auth_response );
		static::$scopes = apply_filters( 'indieauth_scopes', static::$scopes );

		// Every user should have this capability which reflects the ability to access your user profile and the admin dashboard
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'forbidden', 'Unauthorized', array( 'status' => 403 ) );
		}

		// If there is no auth response this is cookie authentication which should be rejected
		// https://www.w3.org/TR/microsub/#authentication-and-authorization - Requests must be authenticated by token
		if ( empty( static::$microsub_auth_response ) ) {
			return new WP_Error( 'unauthorized', 'Cookie Authentication is not permitted', array( 'status' => 401 ) );
		}
		return true;
	}

	public static function check_request_permissions( $request ) {
		$auth = self::load_auth();
		yarns_ms_debug_log("micrpub auth debug: ");
		yarns_ms_debug_log("auth = " . wp_json_encode($auth));
		yarns_ms_debug_log("user = " . wp_json_encode(get_current_user_id()));
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$query = $request->get_param( 'action' );
		if ( ! $query ) {
			return new WP_Error( 'invalid_request', 'Missing Action Parameter', array( 'status' => 400 ) );
		}

		return true;
	}



	/**
	 * Check scope.
	 * If a user id is supplied check the scope against the user permissions otherwise just check scopes
	 *
	 * @param string $scope
	 * @param id $user_id. Optional.
	 *
	 * @return boolean|WP_Microsub_Error
	 **/
	protected static function check_scope( $scope, $user_id = null ) {
		$inscope = in_array( $scope, static::$scopes, true ) || in_array( 'post', static::$scopes, true );
		if ( ! $inscope ) {
			return new WP_Microsub_Error( 'insufficient_scope', sprintf( 'scope insufficient to %1$s posts', $scope ), 401, static::$scopes );
		}
		// Because 0 is a user
		if ( is_null( $user_id ) ) {
			return true;
		}
		switch ( $scope ) {
			case 'update':
				if ( ! user_can( $user_id, 'edit_posts' ) ) {
					return new WP_Microsub_Error( 'forbidden', sprintf( 'user id %1$s cannot update posts', $user_id ), 403 );
				}
				return true;
			case 'undelete':
			case 'delete':
				if ( ! user_can( $user_id, 'delete_posts' ) ) {
					return new WP_Microsub_Error( 'forbidden', sprintf( 'user id %1$s cannot delete posts', $user_id ), 403 );
				}
				return true;
			case 'create':
				if ( ! user_can( $user_id, 'publish_posts' ) ) {
					return new WP_Microsub_Error( 'forbidden', sprintf( 'user id %1$s cannot create posts', $user_id ), 403 );
				}
				return true;
			default:
				return new WP_Microsub_Error( 'invalid_request', 'Unknown Action', 400 );
		}
	}


	/**
	 * Parse the microsub request and render the document
	 *
	 * @param WP_REST_Request $request.
	 */
	public static function post_handler( $request ) {


		$user_id  = get_current_user_id();
		$response = new WP_REST_Response();
		$load     = static::load_input( $request );
		if ( is_microsub_error( $load ) ) {
			return $load;
		}

		$action = ms_get( static::$input, 'action', 'create' );
		if ( ! self::check_scope( $action ) ) {
			return new WP_Microsub_Error( 'insufficient_scope', sprintf( 'scope insufficient to %1$s posts', $action ), 403 );
		}

		$url = ms_get( static::$input, 'url' );

		// check that we support all requested syndication targets
		$synd_supported = self::get_syndicate_targets( $user_id );
		$uids           = array();
		foreach ( $synd_supported as $syn ) {
			$uids[] = ms_get( $syn, 'uid' );
		}

		$properties     = ms_get( static::$input, 'properties' );
		$synd_requested = ms_get( $properties, 'mp-syndicate-to' );
		$unknown        = array_diff( $synd_requested, $uids );

		if ( $unknown ) {
			return new WP_Microsub_Error( 'invalid_request', sprintf( 'Unknown mp-syndicate-to targets: %1$s', implode( ', ', $unknown ) ), 400 );
		}
		// For all actions other than creation a url is required
		if ( ! $url && 'create' !== $action ) {
			return new WP_Microsub_Error( 'invalid_request', sprintf( 'URL is Required for %1$s action', $action ), 400 );
		}
		switch ( $action ) {
			case 'create':
				$args = static::create( $user_id );
				if ( ! is_microsub_error( $args ) ) {
					$response->set_status( 201 );
					$response->header( 'Location', get_permalink( $args['ID'] ) );
				}
				break;
			case 'update':
				$args = static::update( static::$input );
				break;
			case 'delete':
				$args = get_post( url_to_postid( $url ), ARRAY_A );
				if ( ! $args ) {
					return new WP_Microsub_Error( 'invalid_request', sprintf( '%1$s not found', $url ), 400 );
				}
				static::check_error( wp_trash_post( $args['ID'] ) );
				break;
			case 'undelete':
				$found = false;
				// url_to_postid() doesn't support posts in trash, so look for
				// it ourselves, manually.
				// here's another, more complicated way that customizes WP_Query:
				// https://gist.github.com/peterwilsoncc/bb40e52cae7faa0e6efc
				foreach ( get_posts(
					array(
						'post_status' => 'trash',
						'fields'      => 'ids',
					)
				) as $post_id ) {
					if ( get_the_guid( $post_id ) === $url ) {
						wp_untrash_post( $post_id );
						wp_publish_post( $post_id );
						$found = true;
						$args  = array( 'ID' => $post_id );
					}
				}
				if ( ! $found ) {
					return new WP_Microsub_Error( 'invalid_request', sprintf( 'deleted post %1$s not found', $url ), 400 );
				}
				break;
			default:
				return new WP_Microsub_Error( 'invalid_request', sprintf( 'unknown action %1$s', $action ), 400 );
		}
		if ( is_microsub_error( $args ) ) {
			return $args;
		}
		do_action( 'after_microsub', static::$input, $args );

		if ( ! empty( $synd_requested ) ) {
			do_action( 'microsub_syndication', $args['ID'], $synd_requested );
		}

		$response->set_data( $args );
		return $response;
	}

	private static function get_syndicate_targets( $user_id ) {
		return apply_filters( 'microsub_syndicate-to', array(), $user_id );
	}

	/**
	 * Handle queries to the microsub endpoint
	 *
	 * @param WP_REST_Request $request
	 */
	public static function query_handler( $request ) {
		$user_id = get_current_user_id();
		static::load_input( $request );

		switch ( static::$input['q'] ) {
			case 'config':
				$resp = array(
					'syndicate-to'   => static::get_syndicate_targets( $user_id ),
					'media-endpoint' => rest_url( MICROSUB_NAMESPACE . '/media' ),
					'mp'             => array(
						'slug',
						'syndicate-to',
					), // List of supported mp parameters
					'q'              => array(
						'config',
						'syndicate-to',
						'category',
						'source',
					), // List of supported query parameters https://github.com/indieweb/microsub-extensions/issues/7
					'properties'     => array(
						'location-visibility',
					), // List of support properties https://github.com/indieweb/microsub-extensions/issues/8
				);
				break;
			case 'syndicate-to':
				// return syndication targets with filter
				$resp = array( 'syndicate-to' => static::get_syndicate_targets( $user_id ) );
				break;
			case 'category':
				// https://github.com/indieweb/microsub-extensions/issues/5
				$resp = array_merge(
					get_tags( array( 'fields' => 'names' ) ),
					get_terms(
						array(
							'taxonomy' => 'category',
							'fields'   => 'names',
						)
					)
				);
				if ( array_key_exists( 'search', static::$input ) ) {
					$search = static::$input['search'];
					$resp   = array_values(
						array_filter(
							$resp,
							function( $value ) use ( $search ) {
								return ( false !== stripos( $value, $search ) );
							}
						)
					);
				}

				$resp = array( 'categories' => $resp );
				break;
			case 'source':
				if ( array_key_exists( 'url', static::$input ) ) {
					$post_id = url_to_postid( static::$input['url'] );
					if ( ! $post_id ) {
						return new WP_Microsub_Error( 'invalid_request', sprintf( 'not found: %1$s', static::$input['url'] ), 400 );
					}
					$resp = self::query( $post_id );
				} else {
					$numberposts = ms_get( static::$input, 'limit', 10 );
					$posts       = get_posts(
						array(
							'posts_per_page' => $numberposts,
							'fields'         => 'ids',
						)
					);
					$resp        = array();
					foreach ( $posts as $post ) {
						$resp[] = self::query( $post );
					}
					$resp = array( 'items' => $resp );
				}

				break;
			default:
				$resp = new WP_Microsub_Error( 'invalid_request', 'unknown query', 400, static::$input );
		}
		$resp = apply_filters( 'microsub_query', $resp, static::$input );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		do_action( 'after_microsub', static::$input, null );
		return new WP_REST_Response( $resp, 200 );
	}

	/* Query a format.
	 *
	 * @param int $post_id Post ID
	 *
	 * @return array MF2 Formatted Array
	 */
	public static function query( $post_id ) {
		$resp  = static::get_mf2( $post_id );
		$props = ms_get( static::$input, 'properties' );
		if ( $props ) {
			if ( ! is_array( $props ) ) {
				$props = array( $props );
			}
			$resp = array(
				'properties' => array_intersect_key(
					$resp['properties'],
					array_flip( $props )
				),
			);
		}
		return $resp;
	}


	/*
	 * Handle a create request.
	 */
	private static function create( $user_id ) {
		$args = static::mp_to_wp( static::$input );
		$args = static::store_microsub_auth_response( $args );

		$post_content = ms_get( $args, 'post_content', '' );
		$post_content = apply_filters( 'microsub_post_content', $post_content, static::$input );
		if ( $post_content ) {
			$args['post_content'] = $post_content;
		}

		$args = static::store_mf2( $args );
		$args = static::store_geodata( $args );
		if ( is_microsub_error( $args ) ) {
			return $args;
		}

		if ( $user_id ) {
			$args['post_author'] = $user_id;
		}
		$args['post_status'] = static::post_status( static::$input );
		if ( ! $args['post_status'] ) {
			return new WP_Microsub_Error( 'invalid_request', 'Invalid Post Status', 400 );
		}
		if ( WP_DEBUG ) {
			static::log_error( $args, 'wp_insert_post with args' );
		}

		kses_remove_filters();  // prevent sanitizing HTML tags in post_content
		$args['ID'] = static::check_error( wp_insert_post( $args, true ) );
		kses_init_filters();

		static::default_file_handler( $args['ID'] );
		return $args;
	}

	/*
	 * Handle an update request.
	 *
	 * This really needs a db transaction! But we can't assume the underlying
	 * MySQL db is InnoDB and supports transactions. :(
	 */
	private static function update( $input ) {
		$post_id = url_to_postid( $input['url'] );
		$args    = get_post( $post_id, ARRAY_A );
		if ( ! $args ) {
			return new WP_Microsub_Error( 'invalid_request', sprintf( '%1$s not found', $input['url'] ), 400 );
		}

		// add
		$add = ms_get( $input, 'add', false );
		if ( $add ) {
			if ( ! is_array( $add ) ) {
				return new WP_Microsub_Error( 'invalid_request', 'add must be an object', 400 );
			}
			if ( array_diff( array_keys( $add ), array( 'category', 'syndication' ) ) ) {
				return new WP_Microsub_Error( 'invalid_request', 'can only add to category and syndication; other properties not supported', 400 );
			}
			$add_args = static::mp_to_wp( array( 'properties' => $add ) );
			if ( $add_args['tags_input'] ) {
				// i tried wp_add_post_tags here, but it didn't work
				$args['tags_input'] = array_merge(
					$args['tags_input'] ?: array(),
					$add_args['tags_input']
				);
			}
			if ( $add_args['post_category'] ) {
				// i tried wp_set_post_categories here, but it didn't work
				$args['post_category'] = array_merge(
					$args['post_category'] ?: array(),
					$add_args['post_category']
				);
			}
		}
		// Delete was moved to before replace in versions greater than 1.4.3 due to the fact that all items should be removed before replacement
		// delete
		$delete = ms_get( $input, 'delete', false );
		if ( $delete ) {
			if ( is_assoc_array( $delete ) ) {
				if ( array_diff( array_keys( $delete ), array( 'category', 'syndication' ) ) ) {
					return new WP_Microsub_Error( 'invalid_request', 'can only delete individual values from category and syndication; other properties not supported', 400 );
				}
				$delete_args = static::mp_to_wp( array( 'properties' => $delete ) );
				if ( $delete_args['tags_input'] ) {
					$args['tags_input'] = array_diff(
						$args['tags_input'] ?: array(),
						$delete_args['tags_input']
					);
				}
				if ( $delete_args['post_category'] ) {
					$args['post_category'] = array_diff(
						$args['post_category'] ?: array(),
						$delete_args['post_category']
					);
				}
			} elseif ( is_array( $delete ) ) {
				foreach ( static::mp_to_wp( array( 'properties' => array_flip( $delete ) ) )
					as $name => $_ ) {
					$args[ $name ] = null;
				}
				if ( in_array( 'category', $delete, true ) ) {
					wp_set_post_tags( $post_id, '', false );
					wp_set_post_categories( $post_id, '' );
				}
			} else {
				return new WP_Microsub_Error( 'invalid_request', 'delete must be an array or object', 400 );
			}
		}

		// replace
		$replace = ms_get( $input, 'replace', false );
		if ( $replace ) {
			if ( ! is_array( $replace ) ) {
				return new WP_Microsub_Error( 'invalid_request', 'replace must be an object', 400 );
			}
			foreach ( static::mp_to_wp( array( 'properties' => $replace ) )
				as $name => $val ) {
				$args[ $name ] = $val;
			}
		}

		// tell WordPress to preserve published date explicitly, otherwise
		// wp_update_post sets it to the current time
		$args['edit_date'] = true;

		/* Filter Post Content
		 * Post Content is initially generated from content properties in the mp_to_wp function however this function is called
		 * multiple times for replace and delete
		*/
		$post_content = ms_get( $args, 'post_content', '' );
		$post_content = apply_filters( 'microsub_post_content', $post_content, static::$input );
		if ( $post_content ) {
			$args['post_content'] = $post_content;
		}

		// Store metadata from Microformats Properties
		$args = static::store_mf2( $args );
		$args = static::store_geodata( $args );

		if ( WP_DEBUG ) {
			static::log_error( $args, 'wp_update_post with args' );
		}

		kses_remove_filters();
		static::check_error( wp_update_post( $args, true ) );
		kses_init_filters();

		static::default_file_handler( $post_id );
		return $args;
	}

	private static function default_post_status() {
		$option = get_option( 'microsub_default_post_status', '' );
		if ( ! in_array( $option, array( 'publish', 'draft', 'private' ), true ) ) {
			return MICROSUB_DRAFT_MODE ? 'draft' : 'publish';
		}
		return $option;
	}

	private static function post_status( $mf2 ) {
		$props = $mf2['properties'];
		// If both are not set immediately return
		if ( ! isset( $props['post-status'] ) && ! isset( $props['visibility'] ) ) {
			return self::default_post_status();
		}
		if ( isset( $props['visibility'] ) ) {
			$visibilitylist = array( array( 'private' ), array( 'public' ) );
			if ( ! in_array( $props['visibility'], $visibilitylist, true ) ) {
				// Returning null will cause the server to return a 400 error
				return null;
			}
			if ( array( 'private' ) === $props['visibility'] ) {
				return 'private';
			}
		}
		if ( isset( $props['post-status'] ) ) {
			//  According to the proposed specification these are the only two properties supported.
			// https://indieweb.org/Microsub-extensions#Post_Status
			// For now these are the only two we will support even though WordPress defaults to 8 and allows custom
			// But makes it easy to change
			$statuslist = array( array( 'published' ), array( 'draft' ) );
			if ( ! in_array( $props['post-status'], $statuslist, true ) ) {
				// Returning null will cause the server to return a 400 error
				return null;
			}
			// Map published to the WordPress property publish.
			if ( array( 'published' ) === $props['post-status'] ) {
				return 'publish';
			}
			return 'draft';
		}
		// Execution will never reach here
	}

	/**
	 * Generates a suggestion for a title based on mf2 properties.
	 * This can be used to generate a post slug
	 * $mf2 MF2 Properties
	 *
	 */
	private static function suggest_post_title( $mf2 ) {
		$props = ms_get( $mf2, 'properties' );
		if ( isset( $props['name'] ) ) {
			return $props['name'];
		}
		return apply_filters( 'microsub_suggest_title', '', $props );
	}

	/**
	 * Converts Microsub create, update, or delete request to args for WordPress
	 * wp_insert_post() or wp_update_post().
	 *
	 * For updates, reads the existing post and starts with its data:
	 *  'replace' properties are replaced
	 *  'add' properties are added. the new value in $args will contain both the
	 *    existing and new values.
	 *  'delete' properties are set to NULL
	 *
	 * Uses $input, so load_input() must be called before this.
	 */
	private static function mp_to_wp( $mf2 ) {
		$props = ms_get( $mf2, 'properties' );
		$args  = array();

		foreach ( array(
			'mp-slug' => 'post_name',
			'name'    => 'post_title',
			'summary' => 'post_excerpt',
		) as $mf => $wp ) {
			if ( isset( $props[ $mf ] ) ) {
				$args[ $wp ] = static::get( $props[ $mf ], 0 );
			}
		}

		// perform these functions only for creates
		if ( ! isset( $args['ID'] ) && ! isset( $args['post_name'] ) ) {
			$slug = static::suggest_post_title( $mf2 );
			if ( ! empty( $slug ) ) {
				$args['post_name'] = $slug;
			}
		}
		if ( isset( $args['post_name'] ) ) {
			$args['post_name'] = sanitize_title( $args['post_name'] );
		}

		if ( isset( $props['published'] ) ) {
			$date = new DateTime( $props['published'][0] );
			// If for whatever reason the date cannot be parsed do not include one which defaults to now
			if ( $date ) {
				$tz_string = get_option( 'timezone_string' );
				if ( empty( $tz_string ) ) {
					$tz_string = 'UTC';
				}
				$date->setTimeZone( new DateTimeZone( $tz_string ) );
				$tz = $date->getTimezone();
				// Pass this argument to the filter for use
				$args['timezone']  = $tz->getName();
				$args['post_date'] = $date->format( 'Y-m-d H:i:s' );
				$date->setTimeZone( new DateTimeZone( 'GMT' ) );
				$args['post_date_gmt'] = $date->format( 'Y-m-d H:i:s' );
			}
		}

		if ( isset( $props['updated'] ) ) {
			$date = new DateTime( $props['updated'][0] );
			// If for whatever reason the date cannot be parsed do not include one which defaults to now
			if ( $date ) {
				$tz_string = get_option( 'timezone_string' );
				if ( empty( $tz_string ) ) {
					$tz_string = 'UTC';
				}
				$date->setTimeZone( new DateTimeZone( $tz_string ) );
				$tz = $date->getTimezone();
				// Pass this argument to the filter for use
				$args['timezone']      = $tz->getName();
				$args['post_modified'] = $date->format( 'Y-m-d H:i:s' );
				$date->setTimeZone( new DateTimeZone( 'GMT' ) );
				$args['post_modified_gmt'] = $date->format( 'Y-m-d H:i:s' );
			}
		}

		// Map microsub categories to WordPress categories if they exist, otherwise
		// to WordPress tags.
		if ( isset( $props['category'] ) ) {
			$args['post_category'] = array();
			$args['tags_input']    = array();
			foreach ( $props['category'] as $mp_cat ) {
				$wp_cat = get_category_by_slug( $mp_cat );
				if ( $wp_cat ) {
					$args['post_category'][] = $wp_cat->term_id;
				} else {
					$args['tags_input'][] = $mp_cat;
				}
			}
		}
		if ( isset( $props['content'] ) ) {
			$content = $props['content'][0];
			if ( is_array( $content ) ) {
				$args['post_content'] = $content['html'] ?:
					htmlspecialchars( $content['value'] );
			} elseif ( $content ) {
				$args['post_content'] = htmlspecialchars( $content );
			}
		}
		return $args;
	}

	/**
	 * Handles Photo Upload.
	 *
	 */
	public static function default_file_handler( $post_id ) {
		foreach ( array( 'photo', 'video', 'audio' ) as $field ) {
			$props   = static::$input['properties'];
			$att_ids = array();

			if ( isset( static::$files[ $field ] ) || isset( $props[ $field ] ) ) {
				if ( isset( static::$files[ $field ] ) ) {
					$files = static::$files[ $field ];
					if ( is_array( $files['name'] ) ) {
						$files = Microsub_Media::file_array( $files );
						foreach ( $files as $file ) {
							$att_ids[] = static::check_error(
								Microsub_Media::media_handle_upload( $file, $post_id )
							);
						}
					} else {
						$att_ids[] = static::check_error(
							Microsub_Media::media_handle_upload( $files, $post_id )
						);
					}
				} elseif ( isset( $props[ $field ] ) ) {
					foreach ( $props[ $field ] as $val ) {
						$url       = is_array( $val ) ? $val['value'] : $val;
						$desc      = is_array( $val ) ? $val['alt'] : null;
						$att_ids[] = static::check_error(
							Microsub_Media::media_sideload_url(
								$url,
								$post_id,
								$desc
							)
						);
					}
				}

				$att_urls = array();
				foreach ( $att_ids as $id ) {
					if ( is_microsub_error( $id ) ) {
						return $id;
					}
					$att_urls[] = wp_get_attachment_url( $id );
				}
				// Add to the input so will be visible to the after_microsub action
				if ( ! isset( static::$input['properties'][ $field ] ) ) {
					static::$input['properties'][ $field ] = $att_urls;
				} else {
					static::$input['properties'][ $field ] = array_merge( static::$input['properties'][ $field ], $att_urls );
				}
				add_post_meta( $post_id, 'mf2_' . $field, $att_urls, true );
			}
		}
	}

	/**
	 * Stores geodata in WordPress format.
	 *
	 * Reads from the location and checkin properties. checkin isn't an official
	 * mf2 property yet, but OwnYourSwarm sends it:
	 * https://ownyourswarm.p3k.io/docs#checkins
	 *
	 * WordPress geo data is stored in post meta: geo_address (free text),
	 * geo_latitude, geo_longitude, and geo_public:
	 * https://codex.wordpress.org/Geodata
	 * It is noted that should the HTML5 style geolocation properties of altitude, accuracy, speed, and heading are
	 * used they would use the same geo prefix. Simple Location stores these when available using accuracy to estimate
	 * map zoom when displayed.
	 */
	public static function store_geodata( $args ) {
		$properties = static::get( static::$input, 'properties' );
		$location   = static::get( $properties, 'location', static::get( $properties, 'checkin' ) );
		$location   = static::get( $location, 0, $location );
		// Location-visibility is an experimental property https://indieweb.org/Microsub-extensions#Location_Visibility
		// It attempts to mimic the geo_public property
		$visibility = static::get( $properties, 'location-visibility', null );
		if ( $visibility ) {
			$visibility = array_pop( $visibility );
			if ( ! isset( $args['meta_input'] ) ) {
				$args['meta_input'] = array();
			}
			switch ( $visibility ) {
				// Currently supported by https://github.com/dshanske/simple-location as part of the Geodata store noted in codex link above
				// Public indicates coordinates, map, and textual description displayed
				case 'public':
					$args['meta_input']['geo_public'] = 1;
					break;
				// Private indicates no display
				case 'private':
					$args['meta_input']['geo_public'] = 0;
					break;
				// Protected which is not in the original geodata spec is used by Simple Location to indicate textual description only
				case 'protected':
					$args['meta_input']['geo_public'] = 2;
					break;
				default:
					return new WP_Microsub_Error( 'invalid_request', sprintf( 'unsupported location visibility %1$s', $visiblity ), 400 );

			}
		}
		if ( $location ) {
			if ( ! isset( $args['meta_input'] ) ) {
				$args['meta_input'] = array();
			}
			// $location = self::parse_geo_uri( $location );
			if ( is_array( $location ) ) {
				$props = $location['properties'];
				if ( isset( $props['geo'] ) ) {
					$args['meta_input']['geo_address'] = $props['label'][0];
					$props                             = $props['geo'][0]['properties'];
				} else {
					$parts                             = array(
						$props['name'][0],
						$props['street-address'][0],
						$props['locality'][0],
						$props['region'][0],
						$props['postal-code'][0],
						$props['country-name'][0],
					);
					$args['meta_input']['geo_address'] = implode(
						', ',
						array_filter(
							$parts,
							function( $v ) {
								return $v;
							}
						)
					);
				}
				$args['meta_input']['geo_latitude']  = $props['latitude'][0];
				$args['meta_input']['geo_longitude'] = $props['longitude'][0];
				$args['meta_input']['geo_altitude']  = $props['altitude'][0];
				$args['meta_input']['geo_accuracy']  = $props['accuracy'][0];
			} elseif ( 'http' !== substr( $location, 0, 4 ) ) {
				$args['meta_input']['geo_address'] = $location;
			}
		}
		return $args;
	}

	/**
	 * Parse a GEO URI into an mf2 object for storage
	 */
	public static function parse_geo_uri( $uri ) {
		if ( ! is_string( $uri ) ) {
			return $uri;
		}
		// Ensure this is a geo uri
		if ( 'geo:' !== substr( $uri, 0, 4 ) ) {
			return $uri;
		}
		$properties = array();
		// Geo URI format:
		// http://en.wikipedia.org/wiki/Geo_URI#Example
		// https://indieweb.org/Microsub#h-entry
		//
		// e.g. geo:37.786971,-122.399677;u=35
		$geo                     = str_replace( 'geo:', '', urldecode( $uri ) );
		$geo                     = explode( ';', $geo );
		$coords                  = explode( ',', $geo[0] );
		$properties['latitude']  = array( trim( $coords[0] ) );
		$properties['longitude'] = array( trim( $coords[1] ) );
		// Geo URI optionally allows for altitude to be stored as a third csv
		if ( isset( $coords[2] ) ) {
			$properties['altitude'] = array( trim( $coords[2] ) );
		}
		// Store additional parameters
		array_shift( $geo ); // Remove coordinates to check for other parameters
		foreach ( $geo as $g ) {
			$g = explode( '=', $g );
			if ( 'u' === $g[0] ) {
				$g[0] = 'accuracy';
			}
			$properties[ $g[0] ] = array( $g[1] );
		}
		// If geo URI is overloaded h-card... e.g. geo:37.786971,-122.399677;u=35;h=card;name=Home;url=https://example.com
		if ( array_key_exists( 'h', $return ) ) {
			$type = array( 'h-' . $properties['h'][0] );
			unset( $properties['h'] );
		} else {
			$diff = array_diff(
				array_keys( $properties ),
				array( 'longitude', 'latitude', 'altitude', 'accuracy' )
			);
			// If empty that means this is a geo
			if ( empty( $diff ) ) {
				$type = array( 'h-geo' );
			} else {
				$type = array( 'h-card' );
			}
		}

		return array(
			'type'       => $type,
			'properties' => array_filter( $properties ),
		);
	}

	/**
	 * Store the return of the authorization endpoint as post metadata. Details:
	 * https://tokens.indieauth.com/
	 */
	public static function store_microsub_auth_response( $args ) {
		$microsub_auth_response = static::$microsub_auth_response;
		if ( $microsub_auth_response || ( is_assoc_array( $microsub_auth_response ) ) ) {
			$args['meta_input']                           = ms_get( $args, 'meta_input' );
			$args['meta_input']['microsub_auth_response'] = $microsub_auth_response;
		}
		return $args;
	}

	/**
	 * Store properties as post metadata. Details:
	 * https://indiewebcamp.com/WordPress_Data#Microformats_data
	 *
	 * Uses $input, so load_input() must be called before this.
	 *
	 * If the request is a create, this populates $args['meta_input']. If the
	 * request is an update, it changes the post meta values in the db directly.
	 */
	public static function store_mf2( $args ) {
		$props = ms_get( static::$input, 'properties', false );
		if ( ! isset( $args['ID'] ) && $props ) {
			$args['meta_input'] = ms_get( $args, 'meta_input' );
			$type               = static::$input['type'];
			if ( $type ) {
				$args['meta_input']['mf2_type'] = $type;
			}
			foreach ( $props as $key => $val ) {
				$args['meta_input'][ 'mf2_' . $key ] = $val;
			}
			return $args;
		}

		$replace = static::get( static::$input, 'replace', null );
		if ( $replace ) {
			foreach ( $replace as $prop => $val ) {
				update_post_meta( $args['ID'], 'mf2_' . $prop, $val );
			}
		}

		$meta = get_post_meta( $args['ID'] );
		$add  = static::get( static::$input, 'add', null );
		if ( $add ) {
			foreach ( $add as $prop => $val ) {
				$key = 'mf2_' . $prop;
				$cur = $meta[ $key ][0] ? unserialize( $meta[ $key ][0] ) : array();
				update_post_meta( $args['ID'], $key, array_merge( $cur, $val ) );
			}
		}

		$delete = static::get( static::$input, 'delete', null );
		if ( $delete ) {
			if ( is_assoc_array( $delete ) ) {
				foreach ( $delete as $prop => $to_delete ) {
					$key = 'mf2_' . $prop;
					if ( isset( $meta[ $key ] ) ) {
						$existing = unserialize( $meta[ $key ][0] );
						update_post_meta(
							$args['ID'],
							$key,
							array_diff( $existing, $to_delete )
						);
					}
				}
			} else {
				foreach ( $delete as $_ => $prop ) {
					delete_post_meta( $args['ID'], 'mf2_' . $prop );
					if ( 'location' === $prop ) {
						delete_post_meta( $args['ID'], 'geo_latitude' );
						delete_post_meta( $args['ID'], 'geo_longitude' );
					}
				}
			}
		}

		return $args;
	}

	/**
	 * Returns the mf2 properties for a post.
	 */
	public static function get_mf2( $post_id ) {
		$mf2 = array();

		foreach ( get_post_meta( $post_id ) as $field => $val ) {
			$val = maybe_unserialize( $val[0] );
			if ( 'mf2_type' === $field ) {
				$mf2['type'] = $val;
			} elseif ( 'mf2_' === substr( $field, 0, 4 ) ) {
				$mf2['properties'][ substr( $field, 4 ) ] = $val;
			}
		}

		return $mf2;
	}

	private static function check_error( $result ) {
		if ( ! $result ) {
			return new WP_Microsub_Error( 'invalid_request', $result, 400 );
		} elseif ( is_wp_error( $result ) ) {
			return microsub_wp_error( $result );
		}
		return $result;
	}

	public static function get_microsub_endpoint() {
		return rest_url( MICROSUB_NAMESPACE . '/endpoint' );
	}

	/**
	 * The microsub autodicovery meta tags
	 */
	public static function microsub_html_header() {
		// phpcs:ignore
		printf( '<link rel="microsub" href="%s" />' . PHP_EOL, static::get_microsub_endpoint() );
	}

	/**
	 * The microsub autodicovery http-header
	 */
	public static function microsub_http_header() {
		static::header( 'Link', '<' . static::get_microsub_endpoint() . '>; rel="microsub"' );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function microsub_jrd_links( $array ) {
		$array['links'][] = array(
			'rel'  => 'microsub',
			'href' => static::get_microsub_endpoint(),
		);
		return $array;
	}

	/* Takes form encoded input and converts to json encoded input */
	public static function form_to_json( $data ) {
		$input = array();
		foreach ( $data as $key => $val ) {
			if ( 'action' === $key || 'url' === $key ) {
				$input[ $key ] = $val;
			} elseif ( 'h' === $key ) {
				$input['type'] = array( 'h-' . $val );
			} elseif ( 'access_token' === $key ) {
				continue;
			} else {
				$input['properties']         = ms_get( $input, 'properties' );
				$input['properties'][ $key ] =
					( is_array( $val ) && wp_is_numeric_array( $val ) )
						? $val : array( $val );
			}
		}
		return $input;
	}

	protected static function get_header( $name ) {
		if ( ! static::$request_headers ) {
			$headers                 = getallheaders();
			static::$request_headers = array();
			foreach ( $headers as $key => $value ) {
				static::$request_headers[ strtolower( $key ) ] = $value;
			}
		}
		return static::$request_headers[ strtolower( $name ) ];
	}

	public static function header( $header, $value ) {
		header( $header . ': ' . $value, false );
	}


	/**
	 *
	 * Serves a request.
	 *
	 * @param string $request The request being served.
	 *
	 * @return array|int|mixed|string|void|
	 */
	public static function serve_request( $request ) {
		$permissions = static::check_request_permissions( $request );
		return $permissions;
		// For debugging, log all requests.
		static::log_request( $request );


		$user_id = get_current_user_id();
		$response = new WP_REST_Response();

		//$action = $request=>get_param('action');
		$action = ms_get( static::$input, 'channels', 'timeline', 'search' ,'preview', 'follow', 'unfollow', 'poll-test', 'poll' );


		if ( ! self::check_scope( $action ) ) {
			return new WP_Microsub_Error( 'insufficient_scope', sprintf( 'scope insufficient to %1$s posts', $action ), 403 );
		}


		// The WordPress IndieAuth plugin uses filters for this.
		/*
		static::$scopes = apply_filters( 'indieauth_scopes', static::$scopes );
		yarns_ms_debug_log('Scopes: ' . wp_json_encode( static::$scopes ) );
		static::$microsub_auth_response = apply_filters( 'indieauth_response', static::$microsub_auth_response );
		if ( ! $user_id ) {
			static::handle_authorize_error( 401, 'Unauthorized' );
		}
		*/


		/*
		* Once authorization is complete, respond to the query:
		*
		* Call functions based on 'action' parameter of the request
		*/
		switch ( $request->get_param( 'action' ) ) {
			case 'channels':
				if ( 'GET' === $request->get_method() ) {
					// return a list of the channels.
					// REQUIRED SCOPE: read.
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					$response = Yarns_Microsub_Channels::get();
					return static::json_response( $response );
				} elseif ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: channels.
					$action = 'channels';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					if ( $request->get_param( 'method' ) === 'delete' ) {
						// delete a channel. d
						Yarns_Microsub_Channels::delete( $request->get_param( 'channel' ) );
						break;
					} elseif ( $request->get_param( 'method' ) === 'order' ) {
						yarns_ms_debug_log( 'method == order');
						if ( $request->get_param( 'channels' ) ) {
							yarns_ms_debug_log( 'valid order action');
							$response = Yarns_Microsub_Channels::order( $request->get_param( 'channels' ) );
						} else {
							$response = false;
						}
						return static::json_response( $response );
					} elseif ( $request->get_param( 'name' ) ) {
						if ( $request->get_param( 'channel' ) ) {
							// update the channel.
							$response = Yarns_Microsub_Channels::update( $request->get_param( 'channel' ), $request->get_param( 'name' ) );
							return static::json_response( $response );
						} else {
							// create a new channel.
							$response = Yarns_Microsub_Channels::add( $request->get_param( 'name' ) );
							return static::json_response( $response );
						}
					}
				}
				break;

			case 'timeline':
				if ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: channels.
					$action = 'channels';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					// If method is 'mark_read' then mark post(s) as READ.
					if ( $request->get_param( 'method' ) === 'mark_read' ) {
						// mark one or more individual entries as read.
						if ( $request->get_param( 'entry' ) ) {

							$response = Yarns_Microsub_Posts::toggle_read( $request->get_param( 'entry' ), true );
							return static::json_response( $response );
						}
						// mark an entry read as well as everything before it in the timeline.
						if ( $request->get_param( 'last_read_entry' ) ) {
							$response = Yarns_Microsub_Posts::toggle_last_read( $request->get_param( 'last_read_entry' ), $request->get_param( 'channel' ), true );
							return static::json_response( $response );
						}
					}
					// If method is 'mark_unread then mark post(s) as UNREAD.
					if ( $request->get_param( 'method' ) === 'mark_unread' ) {
						// mark one or more individual entries as read.
						if ( $request->get_param( 'entry' ) ) {
							$response = Yarns_Microsub_Posts::toggle_read( $request->get_param( 'entry' ), false );
							return static::json_response( $response );
						}
						// mark an entry read as well as everything before it in the timeline.
						if ( $request->get_param( 'last_read_entry' ) ) {
							$response = Yarns_Microsub_Posts::toggle_last_read( $request->get_param( 'last_read_entry' ), $request->get_param( 'channel' ), false );
							return static::json_response( $response );
						}
					}
				} elseif ( 'GET' === $request->get_method() ) {
					// Return a timeline of the channel.
					// REQUIRED SCOPE: read.
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					$response = Yarns_Microsub_Channels::timeline( $request->get_param( 'channel' ), $request->get_param( 'after' ), $request->get_param( 'before' ) );
					return static::json_response( $response );
				}

				break;
			case 'search':
				// REQUIRED SCOPE: follow.
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}
				$response = Yarns_Microsub_Parser::search( $request->get_param( 'query' ) );
				return static::json_response( $response );
			case 'preview':
				// REQUIRED SCOPE: follow.
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}
				$response = Yarns_Microsub_Parser::preview( $request->get_param( 'url' ) );
				return static::json_response( $response );
			case 'follow':
				if ( 'GET' === $request->get_method() ) {
					// REQUIRED SCOPE: read.
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}

					// return a list of feeds being followed in the given channel.
					$response = Yarns_Microsub_Channels::list_follows( $request->get_param( 'channel' ) );
					return static::json_response( $response );
				} elseif ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: follow.
					$action = 'follow';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}

					// follow a new URL in the channel.
					$response = Yarns_Microsub_Channels::follow( $request->get_param( 'channel' ), $request->get_param( 'url' ) );
					return static::json_response( $response );
				}
				break;
			case 'unfollow':
				// REQUIRED SCOPE: follow.
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}

				$response = Yarns_Microsub_Channels::follow( $request->get_param( 'channel' ), $request->get_param( 'url' ), $unfollow = true );
				return static::json_response( $response );
			case 'poll-test':
				// REQUIRED SCOPE: local auth.
				if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}

				$response = Yarns_Microsub_Aggregator::test_aggregator( $request->get_param( 'url' ) );
				return static::json_response( $response );
			case 'test':
				// REQUIRED SCOPE: local auth.
				if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}

				$response = test();
				return static::json_response( $response );
			case 'delete_all':
				// REQUIRED SCOPE: local auth.
				if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}

				$response = Yarns_Microsub_Posts::delete_all_posts( $request->get_param( 'channel' ) );
				return static::json_response( $response );
			default:
				// The action was not recognized.
				$response = 'No action defined';
				return static::json_response( $response );
		}

	}


	/**
	 *  Logs requests for debug purposes.
	 *
	 * @param string $request The rest to be logged.
	 */
	public static function log_request( $request ) {
		if ( ! empty( $request ) ) {
			$message .= "   Method: " . $request->get_method();
			$message .= "   Scopes: " . wp_json_encode(static::$scopes);
			$message .= "   Params: " . wp_json_encode( $request->get_params() );

			yarns_ms_debug_log( $message );
		}
	}


	/**
	 * Returns a WP_REST_Response as JSON.
	 *
	 * @param mixed $data       The data to be returned (usually array, can be string).
	 * @param int   $status     Status code.
	 * @param array $headers    Headers.
	 *
	 * @return WP_REST_Response
	 */
	private static function json_response( $data, $status = 200, array $headers = [] ) {
		$status                  = 200;
		$headers['Content-Type'] = 'application/json';
		return new WP_REST_Response( $data, $status, $headers );
	}


}

