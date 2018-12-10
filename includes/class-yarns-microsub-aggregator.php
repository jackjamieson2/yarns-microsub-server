<?php
/**
 * Class Yarns_Microsub_Aggregator
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
		// If a post has multiple permalinks, check each of them
		if ( is_array( $permalink ) ) {
			foreach ( $permalink as $single_url ) {
				if ( get_page_by_title( $channel . '|' . $single_url, OBJECT, 'yarns_microsub_post' ) ) {
					return true;
				}
			}
		}
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
		$poll_time_limit = 60; // execution time limit in seconds.
		/* todo: Figure out a good time limit and cron schedule.*/

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
								static::init_polling_frequencies( $channels, $channel_uid, $feed['url'] );
							} else {
								// Poll the site if _last_polled is longer ago than _polling_frequency.
								//if ( $feed['_poll_frequency'] * 3600 < time() - strtotime( $feed['_last_polled'] ) ) {
									// If this feed has stored etag and/or last-modified response headers, send them.

									if ( isset( $feed['_response_headers'] ) ) {
										$conditions = $feed['_response_headers'];

									} else {
										$conditions = null;
									}
									$results[] = static::poll_site( $feed['url'], $channel_uid, $conditions );
								//}
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
	 *
	 * @return array
	 */
	public static function poll_site( $url, $channel_uid, $conditions = null) {

		$site_results             = [];
		$site_results['feed url'] = $url;

		$feed                     = Yarns_Microsub_Parser::parse_feed( $url, 20, false, $conditions);


		if ( isset( $feed['_parse_time'] ) ) {
			$site_results['_parse_time'] = $feed['_parse_time'];
		}
		if ( isset( $feed['_response_headers'] ) ) {
			$site_results['_response_headers'] = $feed['_response_headers'];
		}
		if ( $conditions ) {
			$site_results['_request_conditions'] = $conditions;
		}




		// If this is a preview return the feed as is.
		if ( '_preview' === $channel_uid ) {
			return $feed;
		}

		// Otherwise (this is not a preview) check if each post exists and add accordingly.
		if ( isset( $feed['items'] ) ) {
			foreach ( $feed['items'] as $post ) {
				if ( isset( $post['url'] ) && isset( $post['type'] ) ) {
					if ( 'entry' === $post['type'] ) {
						if ( static::poll_post( $post['url'], $post, $channel_uid ) ) {
							$site_results['items'][] = $post['url']; // this is just returned for debugging when manually polling.
						}
					}
				}
			}
		}
		if ( isset( $site_results['items'] ) ) {
			$n_posts_added = count( $site_results['items'] );
		} else {
			$n_posts_added = 0;
		}

		// @todo: Get etag from $feed array and pass to update_polling_frequencies
		$response_headers = [];
		if (!empty($feed['_response_headers'])){
			$response_headers = $feed['_response_headers'];
		}

		static::update_polling_frequencies( $channel_uid, $url, $n_posts_added, $response_headers );

		return $site_results;
	}

	/**
	 * Check if a parsed post has already been saved, if not add it to Yarns' database of saved posts.
	 *
	 * @param string $permalink The permalink of the post to be added.
	 * @param string $post The post content.
	 * @param string $channel_uid The channel to which the post will be added.
	 *
	 * @return bool
	 */
	public static function poll_post( $permalink, $post, $channel_uid ) {
		if ( ! static::exists( $permalink, $channel_uid ) ) {
			Yarns_Microsub_Posts::add_post( $permalink, $post, $channel_uid );
			return true;
		}
		return false;
	}


	/**
	 * Update polling frequencies for an individual feed.
	 *
	 * @param string $channel_uid       Channel UID.
	 * @param string $url               URL of the site.
	 * @param int    $n_posts_added     Count of posts that were added in the last poll.
	 *
	 * @todo: add param to store etag of the most recent fetch.
	 */
	public static function update_polling_frequencies( $channel_uid, $url, $n_posts_added, $response_headers ) {
		$channels = json_decode( get_site_option( 'yarns_channels' ), true );
		$channel_key = Yarns_Microsub_Channels::get_channel_key( $channels, $channel_uid );
		$feed_key    = Yarns_Microsub_Channels::get_feed_key( $channels, $channel_key, $url );

		// Check if any new posts were added from this poll.
		if ( $n_posts_added > 0 ) {
			// New posts were added.
			$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = 0;
		} else {
			// No new posts were found.
			$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] ++;
		}

		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled'] = date( 'Y-m-d H:i:s' );

		$empty_poll_count = $channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'];
		$poll_frequency   = $channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency'];
		$poll_frequencies = array( 1, 2, 4, 8, 12, 24 );

		$key = array_search( $poll_frequency, $poll_frequencies, true );
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
		} else {
			// If the poll frequency isn't valid, then initialize it.
			$poll_frequency = $poll_frequencies[0];
		}
		$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = $empty_poll_count;
		$channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency']   = $poll_frequency;


		//if (!empty($response_headers)){
			$channels[ $channel_key ]['items'][ $feed_key ]['_response_headers'] = $response_headers;
		//}



		// Log each poll attempt for debugging.
		if ( get_site_option( 'yarns_poll_log' ) ) {
			$poll_log = json_decode( get_site_option( 'yarns_poll_log' ), true );
		}
		$this_poll                      = [];
		$this_poll['date']              = date( 'Y-m-d H:i:s' );
		$this_poll['url']               = $url;
		$this_poll['channel_uid']       = $channel_uid;
		$this_poll['_empty_poll_count'] = $empty_poll_count;
		$this_poll['_poll_frequency']   = $poll_frequency;
		$this_poll['_n_posts_added']    = $n_posts_added;
		$poll_log[]                     = $this_poll;
		update_option( 'yarns_poll_log', wp_json_encode( $poll_log ) );


		update_option( 'yarns_channels', wp_json_encode( $channels ) );
	}

	/**
	 * Initializes polling frequency variables for a single feed, then polls the site immediately
	 *
	 * @param array  $channels          The full array of channels and properties.
	 * @param string $channel_uid       Channel UID.
	 * @param string $url               URL of the site.
	 */
	public static function init_polling_frequencies( $channels, $channel_uid, $url ) {
		$channel_key = Yarns_Microsub_Channels::get_channel_key( $channels, $channel_uid );
		$feed_key    = Yarns_Microsub_Channels::get_feed_key( $channels, $channel_key, $url );

		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled']      = date( 'Y-m-d H:i:s' );
		$channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency']   = 1; // measured in hours.
		$channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] = 0;
		update_option( 'yarns_channels', wp_json_encode( $channels ) );
		if ( isset( $channels[ $channel_key ]['items'][ $feed_key ]['url'] ) ) {
			$url         = $channels[ $channel_key ]['items'][ $feed_key ]['url'];
			$channel_uid = $channels[ $channel_key ]['uid'];
			static::poll_site( $url, $channel_uid, $channels, $channel_key, $feed_key );
		}
	}
}
