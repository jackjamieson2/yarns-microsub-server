<?php
/**
 * Microsub endpoint class
 *
 * @author Jack Jamieson
 */

/**
 * Class Yarns_Microsub_Endpoint
 */
class Yarns_Microsub_Endpoint {

	// associative array, read from JSON or form-encoded input. populated by load_input().
	protected static $input;

	/**
	 * Array of scopes populated by IndieAuth
	 *
	 * @var array $scopes
	 */
	protected static $scopes = array();

	/**
	 * Associative array, populated by IndieAuth.
	 *
	 * @var array $microsub_auth_response
	 */
	protected static $microsub_auth_response = array();

	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		$cls = get_called_class();

		// Configure the REST API route.
		add_action( 'rest_api_init', array( $cls, 'register_routes' ) );
		add_filter( 'rest_request_after_callbacks', array( $cls, 'return_error' ), 10, 3 );

		// endpoint discovery.
		add_action( 'wp_head', array( $cls, 'html_header' ), 99 );
		add_action( 'send_headers', array( $cls, 'http_header' ) );
		add_filter( 'host_meta', array( $cls, 'jrd_links' ) );
	}

	public static function return_error( $response, $handler, $request ) {
		if ( YARNS_MICROSUB_NAMESPACE . '/endpoint' !== $request->get_route() ) {
			return $response;
		}
		if ( is_wp_error( $response ) ) {
			return microsub_wp_error( $response );
		}
		return $response;
	}


	/**
	 * Register the Route.
	 */
	public static function register_routes() {
		$cls = get_called_class();
		register_rest_route(
			YARNS_MICROSUB_NAMESPACE,
			'/endpoint',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $cls, 'get_handler' ),
					'permission_callback' => array( $cls, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $cls, 'post_handler' ),
					'permission_callback' => array( $cls, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Parse the micropub request and render the document
	 *
	 * @param WP_REST_Request $request WordPress request
	 *
	 * @uses apply_filter() Calls 'before_microsub' on the default request
	 */
	protected static function load_input( $request ) {
		static::$input = $request->get_params();
		if ( empty( static::$input ) ) {
			return new WP_Microsub_Error( 'invalid_request', 'No input provided', 400 );
		}
		static::$input = apply_filters( 'before_microsub', static::$input );
	}

	/**
	 *  Logs requests for debug purposes.
	 *
	 * @param string $request The rest to be logged.
	 */
	public static function log_request( $request ) {
		if ( ! empty( $request ) ) {
			$message  = 'Request:';
			$message .= '   Method: ' . $request->get_method();
			$message .= '   Params: ' . wp_json_encode( $request->get_params() );
			Yarns_MicroSub_Plugin::debug_log( $message );
		}
	}

	public static function log_response ($response ) {
		$message  = 'Response:';
		$message .= wp_json_encode($response);
		Yarns_MicroSub_Plugin::debug_log( $message );
	}


	public static function load_auth() {
		static::$microsub_auth_response = apply_filters( 'indieauth_response', static::$microsub_auth_response );
		static::$scopes                 = apply_filters( 'indieauth_scopes', static::$scopes );

		// Every user should have this capability which reflects the ability to access your user profile and the admin dashboard
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'forbidden', 'Unauthorized', array( 'status' => 403 ) );
		}

		// If there is no auth response this is cookie authentication which should be rejected
		if ( empty( static::$microsub_auth_response ) ) {
			return new WP_Error( 'unauthorized', 'Cookie Authentication is not permitted', array( 'status' => 401 ) );
		}
		return true;
	}

	public static function check_permissions( $request ) {
		$auth = self::load_auth();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$action = $request->get_param( 'action' );
		if ( ! $action ) {
			return new WP_Error( 'invalid_request', 'Missing Action Parameter', array( 'status' => 400 ) );
		}

		if ( 'GET' === $request->get_method() ) {
			switch ( $action ) {
				case 'search':
				case 'preview':
					$scope = 'follow';
					break;
				case 'timeline':
				case 'channels':
					$scope = 'read';
					break;
				case 'follow':
				case 'unfollow':
					$scope = 'follow';
					break;
				case 'mute':
				case 'unmute':
					$scope = 'mute';
					break;
				case 'block':
				case 'unblock':
					$scope = 'block';
					break;
				default:
					return new WP_Microsub_Error( 'invalid_request', 'Unknown Action', 400 );
			}
		}
		if ( 'POST' === $request->get_method() ) {
			switch ( $action ) {
				case 'channels':
				case 'timeline':
					$scope = 'channels';
					break;
				case 'search':
				case 'preview':
					$scope = 'follow';
					break;
				case 'follow':
				case 'unfollow':
					$scope = 'follow';
					break;
				case 'mute':
				case 'unmute':
					$scope = 'mute';
					break;
				case 'block':
				case 'unblock':
					$scope = 'block';
					break;
				default:
					return new WP_Microsub_Error( 'invalid_request', 'Unknown Action', 400 );
			}
		}
		$permission = self::check_scope( $scope, get_current_user_id() );
		if ( is_microsub_error( $permission ) ) {
			return $permission->to_wp_error();
		}
		return $permission;
	}

	/**
	 *
	 * Serves a request.
	 *
	 * @param string $request The request being served.
	 *
	 * @return array|int|mixed|string|void|
	 */
	public static function get_handler( $request ) {
		// For debugging, log all requests.
		static::log_request( $request );
		$load = static::load_input( $request );
		if ( is_microsub_error( $load ) ) {
			return $load;
		}
		$response = new WP_REST_Response();
		$response->set_headers( [ 'Content-Type' => 'application/json' ] );

		/*
		* Once authorization is complete, respond to the query:
		*
		* Call functions based on 'action' parameter of the request
		*/
		switch ( static::$input['action'] ) {
			case 'channels': // return a list of the channels.
				$response->set_data( Yarns_Microsub_Channels::get() );
				break;
			case 'timeline': // Return a timeline of the channel.
				// Required parameters.
				if ( empty( static::$input['channel'] ) ) {
					return new WP_Microsub_Error( 'invalid_request', 'missing parameter: channel', 400 );
				}
				// Optional parameters.
				$after  = ( isset( static::$input['after'] ) ) ? static::$input['after'] : null;
				$before = ( isset( static::$input['before'] ) ) ? static::$input['before'] : null;
				$is_read = ( isset( static::$input['is_read'] ) ) ? static::$input['is_read'] : null;

				$response->set_data( Yarns_Microsub_Channels::timeline( static::$input['channel'], $after, $before, $is_read ) );
				break;
			case 'follow': // return a list of feeds being followed in the given channel.
				// Required parameters.
				if ( empty( static::$input['channel'] ) ) {
					return new WP_Microsub_Error( 'invalid_request', 'missing parameter: channel', 400 );
				}
				$response->set_data( Yarns_Microsub_Channels::list_follows( static::$input['channel'] ) );
				break;
			default:
				return new WP_Microsub_Error( 'invalid_request', sprintf( 'unknown action %1$s', $action ), 400 );
		}

		static::log_response($response);
		return $response;

	}

	/*
	*
	* Serves a request.
	*
	* @param string $request The request being served.
	*
	* @return array|int|mixed|string|void|
	*/
	public static function post_handler( $request ) {
		// For debugging, log all requests.
		static::log_request( $request );
		$load = static::load_input( $request );
		if ( is_microsub_error( $load ) ) {
			return $load;
		}
		$response = new WP_REST_Response();
		$response->set_headers( [ 'Content-Type' => 'application/json' ] );


		/*
		* Once authorization is complete, respond to the query:
		*
		* Call functions based on 'action' parameter of the request
		*/
		switch ( static::$input['action'] ) {
			case 'channels':
				if ( 'delete' === static::$input['method'] ) {
					// delete a channel.
					Yarns_Microsub_Channels::delete( static::$input['channel'] );
					break;
				} elseif ( 'order' === static::$input['method'] ) {
					Yarns_MicroSub_Plugin::debug_log( 'method == order' );
					if ( static::$input['channels'] ) {
						Yarns_MicroSub_Plugin::debug_log( 'valid order action' );
						$response->set_data( Yarns_Microsub_Channels::order( static::$input['channels'] ) );
					} else {
						$response = false;
					}
				} elseif ( static::$input['name'] ) {
					if ( static::$input['channel'] ) {
						// update the channel.
						$response->set_data( Yarns_Microsub_Channels::update( static::$input['channel'], static::$input['name'] ) );
					} else {
						// create a new channel.
						$response->set_data( Yarns_Microsub_Channels::add( static::$input['name'] ) );
					}
				}
				break;
			case 'timeline':
					// If method is 'mark_read' then mark post(s) as READ.
				if ( 'mark_read' === static::$input['method'] ) {
					// mark one or more individual entries as read.
					if ( isset ( static::$input['entry'] ) ) {
						$response->set_data( Yarns_Microsub_Posts::toggle_read( static::$input['entry'], true ) );
					}
					// mark an entry read as well as everything before it in the timeline.
					if (isset ( static::$input['last_read_entry'] ) ){
						$response->set_data( Yarns_Microsub_Posts::toggle_last_read( static::$input['last_read_entry'], static::$input['channel'], true ) );
					}
				}
					// If method is 'mark_unread then mark post(s) as UNREAD.
				if ( 'mark_unread' === static::$input['method'] ) {
					// mark one or more individual entries as read.
					if ( isset (static::$input['entry'] ) ) {
						$response->set_data( Yarns_Microsub_Posts::toggle_read( static::$input['entry'], false ) );
					}
					// mark an entry read as well as everything before it in the timeline.
					if ( isset( static::$input['last_read_entry'] ) ){
						$response->set_data( Yarns_Microsub_Posts::toggle_last_read( static::$input['last_read_entry'], static::$input['channel'], false ) );
					}
				}
				break;
			case 'search':
				$response->set_data( Yarns_Microsub_Parser::search( static::$input['query'] ) );
				break;
			case 'preview':
				$response->set_data( Yarns_Microsub_Parser::preview( static::$input['url'] ) );
				break;
			case 'follow':
				// follow a new URL in the channel.
				$response->set_data( Yarns_Microsub_Channels::follow( static::$input['channel'], static::$input['url'] ) );
				break;
			case 'unfollow':
				$response->set_data( Yarns_Microsub_Channels::follow( static::$input['channel'], $static::input['url'], true ) );
				break;
			case 'poll-test':
				// REQUIRED SCOPE: local auth.
				if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}

				$response->set_data( Yarns_Microsub_Aggregator::test_aggregator( $request->get_param( 'url' ) ) );
				break;

			case 'delete_all':
				// REQUIRED SCOPE: local auth.
				if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
					static::error( 403, sprintf( 'scope insufficient for local admin actions' ) );
				}

				$response->set_data( Yarns_Microsub_Posts::delete_all_posts( static::$input['channel'] ) );
				break;
			default:
				return new WP_Microsub_Error( 'invalid_request', sprintf( 'unknown action %1$s', $action ), 400 );
		}
		
		static::log_response($response);
		return $response;

	}

	/**
	 * The Microsub autodiscovery meta-tags
	 */
	public static function html_header() {
		printf( '<link rel="microsub" href="%s" />' . PHP_EOL, static::get_microsub_endpoint() );

	}

	/**
	 * The Microsub autodiscovery http-header
	 */
	public static function http_header() {
		header( sprintf( 'Link: <%s>; rel="microsub"', static::get_microsub_endpoint() ), false );
	}

	public static function header( $header, $value ) {
		header( $header . ': ' . $value, false );
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
		$inscope = in_array( $scope, static::$scopes, true );
		if ( ! $inscope ) {
			return new WP_Microsub_Error( 'insufficient_scope', sprintf( 'scope insufficient to %1$s posts', $scope ), 401, static::$scopes );
		}
		return true;
	}

	/**
	 * Return Microsub Endpoint
	 *
	 * @return string the Microsub endpoint
	 */
	public static function get_microsub_endpoint() {
		return apply_filters( 'microsub_endpoint', get_rest_url( null, '/yarns-microsub/1.0/endpoint' ) );
	}



}
