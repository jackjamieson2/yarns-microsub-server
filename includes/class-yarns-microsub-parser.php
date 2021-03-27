<?php

/**
 * Class Yarns_Microsub_Parser
 */
class Yarns_Microsub_Parser {



	/**
	 * Final clean up on post content before saving or previewing.
	 *
	 * @param array $item The post data to be cleaned.
	 * @param array $feed The feed data.
	 *
	 * @return array
	 */
	public static function clean_post( $item, $feed ) {

		$item['author']  = static::clean_author( $item, $feed );
		$item['_source'] = static::get_source( $item, $feed );

		// dedupe name with summary.
		if ( isset( $item['name'] ) ) {
			if ( isset( $item['summary'] ) ) {
				if ( false !== stripos( $item['summary'], $item['name'] ) ) {
					unset( $item['name'] );
				}
			}
		}

		// dedupe name with content['text'].
		if ( isset( $item['name'] ) ) {
			if ( isset( $item['content']['text'] ) ) {
				if ( false !== stripos( $item['content']['text'], $item['name'] ) ) {
					unset( $item['name'] );
				}
			}
		}

		// Attempt to set a featured image.
		if ( ! isset( $item['featured'] ) ) {
			if ( isset( $item['photo'] ) && is_array( $item['photo'] ) && 1 === count( $item['photo'] ) ) {
				$item['featured'] = $item['photo'];
				unset( $item['photo'] );
			}
		}

		// Add type='entry' for jsonfeeds which might be missing it. This seems to only happen from micro.blog
		// see https://github.com/jackjamieson2/yarns-microsub-server/issues/80
		if ( 'jsonfeed' === $feed['type'] && ! isset( $item['type'] ) ) {
			$item['type'] = 'entry';
		}

		if ( is_array( $item ) ) {
			$item = array_filter( $item );
		}

		return $item;

	}

	/** This is a temporary workaround to account for micro.blog jsonfeeds which do not contain author information.
	 *
	 * @param array $item
	 * @param array $feed
	 *
	 * @return array
	 */
	public static function get_source( $item, $feed ) {
		if ( 'jsonfeed' === $feed['type'] && ! isset( $feed['author'] ) ) {
			if ( isset( $feed['name'] ) ) {
				$feed['_source']['name'] = $feed['name'];
			}

			if ( isset( $feed['url'] ) ) {
				$feed['_source']['url'] = $feed['url'];
			}

			if ( isset( $feed['photo'] ) ) {
				$feed['_source']['photo'] = $feed['photo'];
			}

			// Finally, if we were able to add any properties, make $feed['author'] an h-card, then return _source
			if ( isset( $feed['_source'] ) ) {
				$feed['_source']['type'] = 'card';

				return $feed['_source'];
			}
		}
	}

	/**
	 * @param array $item_author
	 * @param array $feed_author
	 */
	public static function clean_author( $item, $feed ) {
		// If author is just a string, replace it with an array
		// see https://github.com/jackjamieson2/yarns-microsub-server/issues/75
		if ( isset( $item['author'] ) ) {
			if ( ! is_array( $item['author'] ) ) {
				$item['author'] = array(
					'type' => 'card',
					'name' => $item['author'],
				);
			}
		}

		if ( isset( $feed['author'] ) ) {
			if ( ! is_array( $feed['author'] ) ) {
				$feed['author'] = array(
					'type' => 'card',
					'name' => $feed['author'],
				);
			}
		}

		if ( isset( $feed['author'] ) && isset( $item['author'] ) ) {
			$item['author'] = array_merge( $feed['author'], $item['author'] );
		} elseif ( isset( $feed['author'] ) && ! isset( $item['author'] ) ) {
			$item['author'] = $feed['author'];
		}

		if ( ! isset( $item['author'] ) ) {
			return;
		}

		// Some feeds return multiple author photos, but only one can be shown.
		if ( isset( $item['author']['photo'] ) ) {
			if ( is_array( $item['author']['photo'] ) ) {
				$item['author']['photo'] = $item['author']['photo'][0];
			}
		}

		// if author['email'] is set, but author['name'] is not, then copy email to name
		if ( isset( $item['author']['email'] ) & ! isset( $item['author']['name'] ) ) {
			$item['author']['name'] = $item['author']['email'];
		}

		// author['url'] should be a string, so convert to string if it's an array

		if ( isset( $item['author']['url'] ) && is_array( $item['author']['url'] ) ) {
			$item['author']['url'] = $item['author']['url'][0];
		}

		//$item['author']['url'] = "test";

		return $item['author'];

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
		$search  = new Parse_This_Discovery();
		$results = $search->fetch( $url );

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
	 * @param string $url URL to be parsed.
	 * @param int $count Number of posts to be returned.
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
			'alternate'  => false,
			'return'     => 'feed',
			'follow'     => true,
			'limit'      => $count,
			'html'       => true, // If mf2 parsing does not work look for html parsing
			'references' => true, // Store nested citations as references per the JF2 spec
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
				$feed['items'][ $key ] = static::clean_post( $feeditem, $feed );
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



