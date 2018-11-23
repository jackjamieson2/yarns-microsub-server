<?php

/**
 * Aggregator class
 *
 * @author Jack Jamieson
 *
 */
class Yarns_Microsub_Aggregator {


	/**
	 * Check if a post already exists
	 *
	 * @param string $permalink permalink of the post to check.
	 * @param string $channel Channel in which to look for the post.
	 *
	 * @return bool
	 */
	public static function exists( $permalink, $channel ) {
		if ( get_page_by_title( $channel . '|' . $permalink, OBJECT, 'yarns_microsub_post' ) ) {
			return true;
		}
	}

	/**
	 * A function used to call polling manually (for testing)
	 *
	 * @return array|mixed
	 */
	public static function test_aggregator() {
		return self::poll();
	}


	/**
	 * Master polling function.
	 * Run through every subscribed feed and poll it.
	 *
	 * Feeds that are updated less frequently will be polled less often.
	 *
	 * Sets an upper limit on execution time to prevent Yarns from bogging down the server.
	 *
	 * @return array|mixed
	 */
	public static function poll() {
		$poll_start_time = time();
		$poll_time_limit = 25; // execution time limit in seconds.

		$results = [];

		$channels = json_decode( get_site_option( 'yarns_channels' ), true );
		if ( $channels ) {
			foreach ( $channels as $channel_key => $channel ) {
				$channel_uid = $channel['uid'];
				if ( isset( $channel['items'] ) ) {
					foreach ( $channel['items'] as $feed_key => $feed ) {
						// reload channels because it changes every iteration.
						$channels = json_decode( get_site_option( 'yarns_channels' ), true );
						if ( isset( $feed['url'] ) ) {
							if ( ! array_key_exists( '_last_polled', $feed ) ) {
								// New subscriptions do not have _last_polled (and other polling frequency variables),
								// so initialize these variables if they do not exist.
								static::init_polling_frequencies( $channels, $channel_key, $feed_key );
							} else {
								// Poll the site if _last_polled is longer ago than _polling_frequency.
								if ( $feed['_poll_frequency'] * 3600 < time() - strtotime( $feed['_last_polled'] ) ) {
									$results[] = static::poll_site( $feed['url'], $channel_uid, $channels, $channel_key, $feed_key );
								}
							}

							// exit early if polling is taking a long time.
							if ( time() - $poll_start_time > $poll_time_limit ) {
								$results['polling start time']     = $poll_start_time;
								$results['polling end time']       = time();
								$results['polling execution time'] = time() - $poll_start_time;
								$results['polling time limit']     = $poll_time_limit;
								return $results;
							}
						}
					}
				}
			}
		}
		$results['polling start time']     = $poll_start_time;
		$results['polling end time']       = time();
		$results['polling execution time'] = time() - $poll_start_time;
		$results['polling time limit']     = $poll_time_limit;
		
		return $results;
	}
	
	/**
	 * Poll a single site for new posts
	 *
	 * @param string $url URL of the site.
	 * @param string $channel_uid Channel UID.
	 * @param array  $channels Full channel array.
	 * @param string $channel_key Key of the channel the feed belongs to.
	 * @param string $feed_key Key of the feed.
	 *
	 * @return array
	 */
	public static function poll_site( $url, $channel_uid, $channels, $channel_key, $feed_key ) {
		$site_results = [];
		$feed         = Yarns_Microsub_Parser::parse_feed( $url, 20 );

		// If this is a preview return the feed as is.
		if ( '_preview' === $channel_uid ) {
			return $feed;
		}
		
		// Otherwise (this is not a preview) check if each post exists and add accordingly.
		if ( isset( $feed['items'] ) ) {
			foreach ( $feed['items'] as $post ) {
				if ( isset( $post['url'] ) ) {
					if ( static::poll_post( $post['url'], $post, $channel_uid, $feed ) ) {
						$site_results[] = $post['url']; // this is just returned for debugging when manually polling.
					}
				}
			}
		}
		
		
		$n_posts_added = count( $site_results );
		// Check if any new posts were added from this poll.
		if ( $n_posts_added > 0 ) {
			// New posts were added.
			$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = 0;
		} else {
			// No new posts were found.
			$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] ++;
		}
		
		static::update_polling_frequencies( $channels, $channel_key, $feed_key, $n_posts_added );
		
		return $site_results;
	}
	
	/**
	 * Check if a parsed post has already been saved, if not add it to Yarns' database of saved posts.
	 *
	 * @param $permalink
	 * @param $post
	 * @param $channel_uid
	 * @param $feed
	 *
	 * @return bool
	 */
	public static function poll_post( $permalink, $post, $channel_uid, $feed ) {
		if ( ! static::exists( $permalink, $channel_uid ) ) {
			Yarns_Microsub_Posts::add_post( $permalink, $post, $channel_uid );
			return true;
		}
		return false;
	}
	
	
	/**
	 * Update polling frequencies for an individual feed.
	 *
	 * @param $channels
	 * @param $channel_key
	 * @param $feed_key
	 * @param $n_posts_added
	 */
	public static function update_polling_frequencies( $channels, $channel_key, $feed_key, $n_posts_added ) {
		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled'] = date( 'Y-m-d H:i:s' );

		$empty_poll_count = $channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'];
		$poll_frequency   = $channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency'];
		$poll_frequencies = array( 1, 2, 4, 8, 12, 24 );

		$key = array_search( 1, $poll_frequencies );
		if ( false !== $key ) {
			if ( $empty_poll_count > 2 ) {
				if ( array_key_exists( $key + 1, $poll_frequencies ) ) {
					$poll_frequency   = $poll_frequencies[ $key + 1 ];
					$empty_poll_count = 0;
				}
			} elseif ( $n_posts_added > 1 ) {
				if ( array_key_exists( $key - 1, $poll_frequencies ) ) {
					$poll_frequency   = $poll_frequencies[ $key - 1 ];
					$empty_poll_count = 0;
				}
			}
		}

		$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = $empty_poll_count;
		$channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency']   = $poll_frequency;
		update_option( 'yarns_channels', wp_json_encode( $channels ) );
	}
	
	/**
	 * Initializes polling frequency variables for a single feed, then polls the site immediately
	 *
	 * @param $channels
	 * @param $channel_key
	 * @param $feed_key
	 */
	public static function init_polling_frequencies( $channels, $channel_key, $feed_key ) {
		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled']      = date( 'Y-m-d H:i:s' );
		$channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency']   = 1; // measured in hours
		$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = 0;
		update_option( 'yarns_channels', wp_json_encode( $channels ) );
		if ( isset( $channels[ $channel_key ]['items'][ $feed_key ]['url'] ) ) {
			$url         = $channels[ $channel_key ]['items'][ $feed_key ]['url'];
			$channel_uid = $channels[ $channel_key ]['uid'];
			static::poll_site( $url, $channel_uid, $channels, $channel_key, $feed_key );
		}
	}
}

