<?php
/**
 * Microsub endpoint class
 *
 * @author Jack Jamieson
 *
 */
class Yarns_Microsub_Endpoint {

	// Array of Scopes
	protected static $scopes;

	// associative array, populated by authorize().
	protected static $microsub_auth_response;
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		add_filter( 'query_vars', array( 'Yarns_Microsub_Endpoint', 'query_var' ) );

		// Configure the REST API route
		add_action( 'rest_api_init', array( 'Yarns_Microsub_Endpoint', 'register_routes' ) );

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
			'microsub', '/endpoint', array(
				'callback' => array( 'Yarns_Microsub_Endpoint', 'serve_request' )
			)
		);
	}

		/**
	 * adds some query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_var( $vars ) {
		$vars[] = 'replytocom';
		return $vars;
	}

	/**
	 * Hooks into the REST API output to output a webmention form.
	 *
	 * This is only done for the webmention endpoint.
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 *
	 * @return true
	 */



	public static function serve_request( $request ) {
		/*
		$debug = "DEBUGGING INFO: \n";
		$debug .= "Microsub testing – All params: \n ". json_encode($request->get_params());
		$debug .= "\n\n" . json_encode($request);
		*/
		
		$user_id = get_current_user_id();
		// The WordPress IndieAuth plugin uses filters for this
		static::$scopes = apply_filters( 'indieauth_scopes', static::$scopes );
		static::$microsub_auth_response = apply_filters( 'indieauth_response',  static::$microsub_auth_response );
		
		if ( ! $user_id ) {
			static::handle_authorize_error( 401, 'Unauthorized' );
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
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		printf( '<link rel="microsub" href="%s" />' . PHP_EOL, get_microsub_endpoint() );
		
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		header( sprintf( 'Link: <%s>; rel="microsub"', get_microsub_endpoint() ), false );
		
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

}
