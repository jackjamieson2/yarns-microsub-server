<?php

/**
 * Class Yarns_Microsub_Parser
 */
class Yarns_Microsub_Parser {

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
			$data = encode_array( array_filter( $data ) );
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
		$search = new Parse_This( $url );
		return $search->fetch_feeds();
	}


	/**
	 * Returns a preview of the feed
	 *
	 * @param string $url URL of the feed to be previewed.
	 *
	 * @return array|void
	 */
	public static function preview( $url ) {
		return static::parse_feed( $url, 5 );
	}

	/**
	 * Parses feed at $url.  Determines whether the feed is h-feed or rss and passes to appropriate
	 * function.
	 *
	 * @param string $url URL to be parsed.
	 * @param int    $count Number of posts to be returned.
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
			'feed'      => true,
			'follow'    => true,
			'limit'     => $count,
		);

		if ( true === $preview ) {
			// When follow == true, Parse-This does some extra fetching to get an author hcard.
			// Previews should be fast, so skip this step.
			$args['follow'] = false;
		}

		$parse = new Parse_This( $url );
		$parse->fetch();
		$parse->parse( $args );

		$feed = $parse->get();

		if ( isset( $feed['items'] ) ) {
			foreach ( $feed['items'] as $key => $feeditem ) {
				$feed['items'][ $key ] = static::clean_post( $feeditem );
			}
		}

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
