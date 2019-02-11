<?php

/**
 * Class Yarns_Microsub_Parser
 */
class Yarns_Microsub_Parser {

	/**
	 * Loads Parse-This if it hasn't already been loaded.
	 */
	public static function load_parse_this() {

		$response = [];

		$path  = plugin_dir_path( __DIR__ ) . 'vendor/parse-this/includes/';

		// Load classes if they are not already loaded.
		$files = array(
			array( 'class-mf2-post.php', 'MF2_Post' ),
			array( 'class-parse-this.php', 'Parse_This' ),
			array( 'class-parse-this-api.php', 'Parse_This_API' ),
			array( 'class-parse-this-html.php', 'Parse_This_HTML' ),
			array( 'class-parse-this-jsonfeed.php', 'Parse_This_JSONFeed' ),
			array( 'class-parse-this-mf2.php', 'Parse_This_MF2' ),
			array( 'class-parse-this-rss.php', 'Parse_This_RSS' ),
		);
		foreach ( $files as $file ) {
			if ( ! class_exists( $file[1] ) ) {
				require_once $path . $file[0];
				$response[] = $path . $file[0];
			} else {
				$response[] = 'already exists: ' . $file[1];
			}
		}

		// Load functions.php.
		if ( ! function_exists("post_type_discovery"))
		require_once $path . 'functions.php';



		return $response;


	}


	/**
	 * Final clean up on post content before saving.
	 *
	 * @param array $data The post data to be cleaned.
	 *
	 * @return mixed
	 */
	public static function clean_post( $data ) {
		// dedupe name with summary.
		if ( isset( $data['name'] ) ) {
			if ( isset( $data['summary'] ) ) {
				if ( false !== stripos( $data['summary'], $data['name'] ) ) {
					unset( $data['name'] );
				}
			}
		}

		// dedupe name with content['text'].
		if ( isset( $data['name'] ) ) {
			if ( isset( $data['content']['text'] ) ) {
				if ( false !== stripos( $data['content']['text'], $data['name'] ) ) {
					unset( $data['name'] );
				}
			}
		}

		// Attempt to set a featured image.
		if ( ! isset( $data['featured'] ) ) {
			if ( isset( $data['photo'] ) && is_array( $data['photo'] ) && 1 === count( $data['photo'] ) ) {
				$data['featured'] = $data['photo'];
				unset( $data['photo'] );
			}
		}

		// Some feeds return multiple author photos, but only one can be shown.
		if ( isset( $data['author']['photo'] ) ) {
			if ( is_array( $data['author']['photo'] ) ) {
				$data['author']['photo'] = $data['author']['photo'][0];
			}
		}

		$ref_types = [ 'like-of', 'repost-of', 'bookmark-of', 'in-reply-to', 'listen-of' ];
		// When these types contain an array (name, url, type) it causes together to crash - see https://github.com/cleverdevil/together/issues/80
		// so reduce them to the url.
		foreach ( $ref_types as $ref_type ) {
			if ( isset( $data[ $ref_type ] ) ) {
				if ( is_array( $data[ $ref_type ] ) ) {
					if ( isset( $data[ $ref_type ]['url'] ) ) {
						$data[ $ref_type ] = $data[ $ref_type ]['url'];
					} else {
						$data [ $ref_type ] = wp_json_encode( $data[ $ref_type ] );
					}
				}
			}
		}
		if ( is_array( $data ) ) {
			//$data = encode_array( array_filter( $data ) );
			$data = array_filter( $data );
		}
		return $data;

	}


	/**
	 * Searches a URL for feeds
	 *
	 * @param string $query The query string to be searched.
	 *
	 * @return array|void
	 */
	public static function search( $query ) {
		// @@todo Check if the content itself is an rss feed and if so just return that.
		// Check if $query is a valid URL, if not try to generate one
		$url = static::validate_url( $query );
		// Search using Parse-This.
		static::load_parse_this(); // Load Parse-This if it hasn't already been loaded.
		$search  = new Parse_This( $url );
		$results = $search->fetch_feeds();
		Yarns_MicroSub_Plugin::debug_log( 'Searched ' . $url . ': ' . wp_json_encode( $results ) );
		return $results;
	}


	/**
	 * Returns a preview of the feed
	 *
	 * @param string $url URL of the feed to be previewed.
	 *
	 * @return mixed
	 */
	public static function preview( $url ) {
		$preview = static::parse_feed( $url, 5, true );
		Yarns_MicroSub_Plugin::debug_log( 'Parsed ' . $url . ': ' . wp_json_encode( $preview ) );
		return $preview;
	}

	/**
	 * Parses feed at $url.  Determines whether the feed is h-feed or rss and passes to appropriate
	 * function.
	 *
	 * @param string  $url URL to be parsed.
	 * @param int     $count Number of posts to be returned.
	 * @param boolean $preview Whether or not this is just a preview.
	 *
	 * @todo: add parameter for date of last polled update.
	 *
	 * @return array|void
	 */
	public static function parse_feed( $url, $count = 20, $preview = false ) {
		$parse_start_time = time();
		if ( ! $url ) {
			return;
		}
		$args = array(
			'alternate' => false,
			'return'      => 'feed',
			'follow'    => true,
			'limit'     => $count,
		);

		if ( true === $preview ) {
			// When follow == true, Parse-This does some extra fetching to get an author hcard.
			// Previews should be fast, so skip this step.
			$args['follow'] = false;
		}


		static::load_parse_this(); // Load Parse-This if it hasn't already been loaded.
		$parse = new Parse_This( $url );
		$parse->fetch();
		$parse->parse( $args );

		$feed = $parse->get();




		if ( isset( $feed['items'] ) ) {
			foreach ( $feed['items'] as $key => $feeditem ) {
				$feed['items'][ $key ] = static::clean_post( $feeditem );
			}
		}

		$parse_end_time      = time();
		$parse_duration      = $parse_end_time - $parse_start_time;
		$feed['_parse_time'] = $parse_duration;
		$feed['_post_limit'] = $count;


		return $feed;
	}


	/*
	* UTILITY FUNCTIONS
	*
	*/

	/**
	 * Corrects invalid URLs if possible
	 *
	 * @param string $possible_url URL to be validated.
	 *
	 * @return string
	 */
	public static function validate_url( $possible_url ) {
		// If it is already a valid URL, return as-is.
		if ( static::is_url( $possible_url ) ) {
			return $possible_url;
		}

		// If just a word was entered, append .com.
		if ( preg_match( '/^[a-z][a-z0-9]+$/', $possible_url ) ) {
			// if just a word was entered, append .com.
			$possible_url = $possible_url . '.com';
		}

		// If missing a scheme, prepend with 'http://', otherwise return as-is.
		return wp_parse_url( $possible_url, PHP_URL_SCHEME ) === null ? 'http://' . $possible_url : $possible_url;
	}

	/**
	 * Returns true if $query is a valid URL
	 *
	 * @param string $query The URL to test.
	 *
	 * @return bool
	 */
	public static function is_url( $query ) {
		if ( filter_var( $query, FILTER_VALIDATE_URL ) ) {
			return true;
		} else {
			return false;
		}
	}

}
