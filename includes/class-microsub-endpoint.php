<?php
/**
 * Microsub endpoint class
 *
 * @author Jack Jamieson
 *
 */

/* For debugging purposes this will bypass MICROSUB authentication
 * in favor of WordPress authentication
 * Using this to test querying(q=) parameters quickly
 */
if ( ! defined( 'MICROSUB_LOCAL_AUTH' ) ) {
	define( 'MICROSUB_LOCAL_AUTH', '0' );
}

// Allows for a custom Authentication and Token Endpoint
if ( ! defined( 'MICROSUB_AUTHENTICATION_ENDPOINT' ) ) {
	define( 'MICROSUB_AUTHENTICATION_ENDPOINT', 'https://indieauth.com/auth' );
}
if ( ! defined( 'MICROSUB_TOKEN_ENDPOINT' ) ) {
	define( 'MICROSUB_TOKEN_ENDPOINT', 'https://tokens.indieauth.com/token' );
}



class Yarns_Microsub_Endpoint {

	// Array of Scopes
	protected static $scopes;

	// associative array
	public static $request_headers;

	// associative array, populated by authorize().
	protected static $microsub_auth_response;
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {

		// Configure the REST API route
		add_action( 'rest_api_init', array( 'Yarns_Microsub_Endpoint', 'register_routes' ) );
		static::register_routes();

		// endpoint discovery
		add_action( 'wp_head', array( 'Yarns_Microsub_Endpoint', 'html_header' ), 99 );
		add_action( 'send_headers', array( 'Yarns_Microsub_Endpoint', 'http_header' ) );
		add_filter( 'host_meta', array( 'Yarns_Microsub_Endpoint', 'jrd_links' ) );

	}


	/**
	 * Register the Route.
	 */
	public static function register_routes() {
		register_rest_route(
			'microsub',
			'/endpoint',
			array(
				array(
					//'methods'  => WP_REST_Server::CREATABLE,
					'methods'  => array( 'GET', 'POST' ),
					'callback' => array( 'Yarns_Microsub_Endpoint', 'serve_request' ),
				),
			)
		);
	}


	/**
	*  Serve requests to the endpoint
	*
	*
	*/
	public static function log_request($request ) {
		$message  = 'Request:';
		$message .= "\nmethod: " . $request->get_method();
		$message .= "\nparams: " . json_encode( $request->get_params() );
		error_log( $message );

	}

	public static function serve_request( $request ) {
		// For debugging, log all requests
		static::log_request( $request );

		/*
		* Attempt to authorize using indieauth plugin. If it is not installed fallback to
		* use indieauth.com
		* (code for using indieauth.com copied from github.com/snarfed/wordpress-micropub)
		*/
		if ( MICROSUB_LOCAL_AUTH == 1 ) {
			// For testing purposes, bypass the authorization
			$user_id = 1;
		} elseif ( class_exists( 'IndieAuth_Plugin' ) ) {
			$user_id = get_current_user_id();
			// The WordPress IndieAuth plugin uses filters for this
			static::$scopes = apply_filters( 'indieauth_scopes', static::$scopes );
			error_log( 'Scopes: ' . json_encode( static::$scopes ) );
			static::$microsub_auth_response = apply_filters( 'indieauth_response', static::$microsub_auth_response );

			if ( ! $user_id ) {
				static::handle_authorize_error( 401, 'Unauthorized' );
			}
		} else {
			// indieauth not installed, use authorize() function
			$user_id = static::authorize();

			error_log( 'Authorized: user_id == ' . $user_id );
		}

		/* Once authorization is complete, respond to the query:
		*
		* Call functions based on 'action' parameter of the request
		* (These functions are in functions-microsub-actions.php)
		*/
		switch ( $request->get_param( 'action' ) ) {
			case 'channels':
				if ( 'GET' === $request->get_method() ) {
					// GET
					//return a list of the channels
					// REQUIRED SCOPE: read
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					return Yarns_Microsub_Channels::get();
					break;
				} elseif ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: channels
					$action = 'channels';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					// POST
					if ( $request->get_param( 'method' ) === 'delete' ) {
						// delete a channel
						Yarns_Microsub_Channels::delete( $request->get_param( 'channel' ) );
						break;

					} elseif ( $request->get_param( 'name' ) ) {
						if ( $request->get_param( 'channel' ) ) {
							return Yarns_Microsub_Channels::update( $request->get_param( 'channel' ), $request->get_param( 'name' ) );
							//update the channel

							break;
						} else {
							//create a new channel
							//return Yarns_Microsub_Channels::add("$request->get_param('name')");
							return Yarns_Microsub_Channels::add( $request->get_param( 'name' ) );
							break;
						}
					}
				}
				break;

			case 'timeline':
<<<<<<< HEAD
				if ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: channels
					$action = 'channels';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
=======
                if ('POST' === $request->get_method()){
                    // REQUIRED SCOPE: channels
                    $action = 'channels';
                    if ( ! self::check_scope( $action ) ) {
                        static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
                    }

                    //If method is 'mark_read' then mark post(s) as READ
                    if ($request->get_param('method') == 'mark_read'){

                        //mark one or more individual entries as read
                        if ($request->get_param('entry')){
                            Yarns_Microsub_Posts::toggle_read($request->get_param('entry'),true );
                            break;
                        }
                        //mark an entry read as well as everything before it in the timeline
                        if ($request->get_param('last_read_entry')){
                            return Yarns_Microsub_Posts::toggle_last_read($request->get_param('last_read_entry'), $request->get_param('channel'),true);
                            break;
                        }
                    }
                    // If method is 'mark_unreadthen mark post(s) as UNREAD
                    if ($request->get_param('method') == 'mark_unread'){
                        //mark one or more individual entries as read
                        if ($request->get_param('entry')){
                            Yarns_Microsub_Posts::toggle_read($request->get_param('entry'), false);
                            break;
                        }
                        //mark an entry read as well as everything before it in the timeline
                        if ($request->get_param('last_read_entry')){
                            Yarns_Microsub_Posts::toggle_last_read($request->get_param('last_read_entry'), $request->get_param('channel'),false);
                            break;
                        }
                    }

                } else if ('GET' === $request->get_method()) {
                    // Return a timeline of the channel
                    // REQUIRED SCOPE: read
                    $action = 'read';
                    if ( ! self::check_scope( $action ) ) {
                        static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
                    }
                    return Yarns_Microsub_Channels::timeline($request->get_param('channel'),$request->get_param('after'),$request->get_param('before'));
                }
>>>>>>> parse-this-update

					//If method is 'mark_read' then mark post(s) as READ
					if ( $request->get_param( 'method' ) == 'mark_read' ) {

						//mark one or more individual entries as read
						if ( $request->get_param( 'entry' ) ) {
							Yarns_Microsub_Posts::toggle_read( $request->get_param( 'entry' ), true );
							break;
						}
						//mark an entry read as well as everything before it in the timeline
						if ( $request->get_param( 'last_read_entry' ) ) {
							return Yarns_Microsub_Posts::toggle_last_read( $request->get_param( 'last_read_entry' ), $request->get_param( 'channel' ), true );
							break;
						}
					}
					// If method is 'mark_unreadthen mark post(s) as UNREAD
					if ( $request->get_param( 'method' ) == 'mark_unread' ) {
						//mark one or more individual entries as read
						if ( $request->get_param( 'entry' ) ) {
							Yarns_Microsub_Posts::toggle_read( $request->get_param( 'entry' ), false );
							break;
						}
						//mark an entry read as well as everything before it in the timeline
						if ( $request->get_param( 'last_read_entry' ) ) {
							Yarns_Microsub_Posts::toggle_last_read( $request->get_param( 'last_read_entry' ), $request->get_param( 'channel' ), false );
							break;
						}
					}
				} elseif ( 'GET' === $request->get_method() ) {
					// Return a timeline of the channel
					// REQUIRED SCOPE: read
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}
					return Yarns_Microsub_Channels::timeline( $request->get_param( 'channel' ), $request->get_param( 'after' ), $request->get_param( 'before' ) );
				}

				break;
			case 'search':
				// REQUIRED SCOPE: follow
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}
				return Yarns_Microsub_Parser::search( $request->get_param( 'query' ) );
				break;
			case 'preview':
				// REQUIRED SCOPE: follow
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}
				return Yarns_Microsub_Parser::preview( $request->get_param( 'url' ) );
				break;
			case 'follow':
				if ( 'GET' === $request->get_method() ) {
					// REQUIRED SCOPE: read
					$action = 'read';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}

					// return a list of feeds being followed in the given channel
					return Yarns_Microsub_Channels::list_follows( $request->get_param( 'channel' ) );
					break;
				} elseif ( 'POST' === $request->get_method() ) {
					// REQUIRED SCOPE: follow
					$action = 'follow';
					if ( ! self::check_scope( $action ) ) {
						static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
					}

					// follow a new URL in the channel
					return Yarns_Microsub_Channels::follow( $request->get_param( 'channel' ), $request->get_param( 'url' ) );
					break;
				}
			case 'unfollow':
				// REQUIRED SCOPE: follow
				$action = 'follow';
				if ( ! self::check_scope( $action ) ) {
					static::error( 403, sprintf( 'Scope insufficient. Requires: %1$s', $action ) );
				}
				return Yarns_Microsub_Channels::follow( $request->get_param( 'channel' ), $request->get_param( 'url' ), $unfollow = true );
				break;
			case 'poll-test':
				// REQUIRED SCOPE: local auth
				if ( ! MICROSUB_LOCAL_AUTH == 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}
				return Yarns_Microsub_Aggregator::test_aggregator( $request->get_param( 'url' ) );
				break;
			case 'test':
				// REQUIRED SCOPE: local auth
				if ( ! MICROSUB_LOCAL_AUTH == 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}
				return test();
				break;
			case 'delete_all':
				// REQUIRED SCOPE: local auth
				if ( ! MICROSUB_LOCAL_AUTH == 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}
				return Yarns_Microsub_Posts::delete_all_posts( $request->get_param( 'channel' ) );
				break;
			default:
				return 'No action defined';
				// The action was not recognized
				break;

		}

	}

	private static function handle_authorize_error( $code, $msg ) {
		$home = untrailingslashit( home_url() );
		if ( 'http://localhost' === $home ) {
			error_log(
				'WARNING: ' . $code . ' ' . $msg .
				". Allowing only because this is localhost.\n"
			);
			return;
		}
		static::respond( $code, $msg );
	}


	/**
	 * The Microsub autodicovery meta-tags
	 */
	public static function html_header() {
		printf( '<link rel="microsub" href="%s" />' . PHP_EOL, static::get_microsub_endpoint() );

	}

	/**
	 * The Microsub autodicovery http-header
	 */
	public static function http_header() {

		header( sprintf( 'Link: <%s>; rel="microsub"', static::get_microsub_endpoint() ), false );

	}

	/** Wrappers for WordPress/PHP functions so we can mock them for unit tests.
	(Copied from wordpress-micropub plugin)
	 **/
	protected static function respond( $code, $resp = null, $args = null ) {
		status_header( $code );
		static::header( 'Content-Type', 'application/json' );
		exit( $resp ? wp_json_encode( $resp ) : '' );
	}

	public static function header( $header, $value ) {
		header( $header . ': ' . $value, false );
	}

	protected static function get_header( $name ) {
		if ( ! static::$request_headers ) {
			$headers = getallheaders();
			error_log( 'Headers ' . json_encode( $headers ) );
			error_log( 'name ' . $name );
			static::$request_headers = array();
			foreach ( $headers as $key => $value ) {
				static::$request_headers[ strtolower( $key ) ] = $value;
			}
		}
		//return 0;

		return static::$request_headers[ strtolower( $name ) ];
	}


	public static function error( $code, $message ) {
		respond(
			//return json_encode(
			$code,
			array(
				'error'             => ( 403 === $code ) ? 'forbidden' :
					( 401 === $code ) ? 'insufficient_scope' :
						'invalid_request',
				'error_description' => $message,
			)
		);
	}

	/**
	 *
	 * Authorization - copied from wordpress-micropub plugin
	 *
	 *
	 * Validate the access token at the token endpoint.
	 *
	 * https://indieauth.spec.indieweb.org/#access-token-verification
	 * If the token is valid, returns the user id to use as the post's author, or
	 * NULL if the token only matched the site URL and no specific user.
	 */
	public static function get_token( $array, $key, $default = array() ) {
		if ( is_array( $array ) ) {
			return isset( $array[ $key ] ) ? $array[ $key ] : $default;
		}
		return $default;
	}

	private static function authorize() {
		// find the access token
		$auth = static::get_header( 'authorization' );
		error_log( 'Auth: ' . $auth );
		$token = self::get_token( $_POST, 'access_token' );
		//error_log ("Token: ". $token);
		if ( ! $auth && ! $token ) {
			// for debugging - just return 1
			//return 1;
			static::handle_authorize_error( 401, 'missing access token' );
		}

		$resp = wp_remote_get(
			get_option( 'indieauth_token_endpoint', MICROSUB_TOKEN_ENDPOINT ),
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => $auth ?: 'Bearer ' . $token,
				),
			)
		);
		error_log( 'Resp: ' . json_encode( $resp ) );
		if ( is_wp_error( $resp ) ) {
			static::handle_authorize_error( 502, "couldn't validate token: " . implode( ' , ', $resp->get_error_messages() ) );
		}

		$code = wp_remote_retrieve_response_code( $resp );
		error_log( 'code: ' . $code );
		$body = wp_remote_retrieve_body( $resp );
		error_log( 'body: ' . $body );
		$params = json_decode( $body, true );
		error_log( 'params: ' . json_encode( $params ) );
		static::$scopes = explode( ' ', $params['scope'] );
		error_log( 'scopes: ' . json_encode( static::$scopes ) );
		if ( (int) ( $code / 100 ) !== 2 ) {
			static::handle_authorize_error(
				$code,
				'invalid access token: ' . $body
			);
		} elseif ( empty( static::$scopes ) ) {
			static::handle_authorize_error(
				401,
				'access token is missing scope'
			);
		}

		$me = untrailingslashit( $params['me'] );
		error_log( 'me: ' . $me );

		static::$microsub_auth_response = $params;

		// look for a user with the same url as the token's `me` value.
		$user = static::user_url( $me );
		error_log( 'user: ' . $user );
		if ( $user ) {
			return $user;
		}

		// no user with that url. if the token is for this site itself, allow it and
		// post as the default user
		$home = untrailingslashit( home_url() );
		error_log( 'home: ' . $home );
		if ( $home !== $me ) {
			static::handle_authorize_error(
				401,
				'access token URL ' . $me . " doesn't match site " . $home . ' or any user'
			);
		}

		return null;
	}

	/**
	 * Check scope
	 *
	 * @param string $scope
	 *
	 * @return boolean
	**/
	protected static function check_scope( $scope ) {
<<<<<<< HEAD
		error_log( "checking for scope {$scope}" );
=======
	    error_log("checking for scope {$scope}");
	    if (MICROSUB_LOCAL_AUTH==1){
	        return true;
        }
>>>>>>> parse-this-update

		return in_array( $scope, static::$scopes, true );
	}

	/**
	 * Try to match a user with a URL with or without trailing slash.
	 *
	 * @param string $me URL to match
	 *
	 * @return null|int Return user ID if matched or null
	**/
	public static function user_url( $me ) {
		if ( ! isset( $me ) ) {
			return null;
		}
		$search = array(
			'search'         => $me,
			'search_columns' => array( 'url' ),
		);
		$users  = get_users( $search );

		$search['search'] = $me . '/';
		$users            = array_merge( $users, get_users( $search ) );
		foreach ( $users as $user ) {
			if ( untrailingslashit( $user->user_url ) === $me ) {
				return $user->ID;
			}
		}
		return null;
	}

	/**
	 * Store the return of the authorization endpoint as post metadata. Details:
	 * https://tokens.indieauth.com/
	 */
	public static function store_micropub_auth_response( $args ) {
		$micropub_auth_response = static::$micropub_auth_response;
		if ( $micropub_auth_response || ( is_assoc_array( $micropub_auth_response ) ) ) {
			$args['meta_input']                           = self::get( $args, 'meta_input' );
			$args['meta_input']['micropub_auth_response'] = $micropub_auth_response;
		}
		return $args;
	}


	/**
	 * Return Microsub Endpoint
	 *
	 *
	 * @return string the Microsub endpoint
	 */
	public static function get_microsub_endpoint() {
		return apply_filters( 'microsub_endpoint', get_rest_url( null, '/microsub/endpoint' ) );
	}


}
