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
		// If a post has multiple permalinks, check each of them.
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
		return self::poll( true );
	}

	/**
	 * A function used to force polling.
	 *
	 * @return array
	 */
	public static function force_poll() {
		return self::poll( true ); // Force poll regardless of time last polled.
	}




	/**
	 * Master polling function.
	 * Run through every subscribed feed and poll it.
	 *
	 * Feeds that are updated less frequently will be polled less often.
	 *
	 * Sets an upper limit on execution time to prevent Yarns from bogging down the server.
	 *
	 * @param boolean $force If set to true, will poll even if the site was recently polled.
	 *
	 * @return array|mixed
	 */
	public static function poll( $force = false, $poll_channel = null ) {
		Yarns_MicroSub_Plugin::debug_log( 'RUNNING POLL' );

		$poll_start_time = time();
		/*$poll_time_limit = 600; // execution time limit in seconds.*/
		$storage_period = get_option( 'yarns_storage_period' );

		$results = array();

		$channels = json_decode( get_option( 'yarns_channels' ), true );
		if ( $channels ) {
			foreach ( $channels as $channel_key => $channel ) {
				$channel_uid = $channel['uid'];
				// If poll_channel is set, only poll that one channel, otherwise poll all channels.
				if ( null === $poll_channel || $channel['uid'] === $poll_channel ) {
					if ( isset( $channel['items'] ) ) {
						foreach ( $channel['items'] as $feed_key => $feed ) {
							// reload channels because it changes every iteration.
							$channels = json_decode( get_option( 'yarns_channels' ), true );
							if ( isset( $feed['url'] ) ) {
								if ( ! array_key_exists( '_last_polled', $feed ) ) {
									// New subscriptions do not have _last_polled (and other polling frequency variables),
									// so initialize these variables if they do not exist.
									static::init_polling_frequencies( $channels, $channel_uid, $feed['url'] );
								} else {
									// Poll the site if _last_polled is longer ago than _polling_frequency.
									if ( true === $force || $feed['_poll_frequency'] * 3600 < time() - strtotime( $feed['_last_polled'] ) ) {
										$results[] = static::poll_site( $feed['url'], $channel_uid, $storage_period );
									}
								}
								// exit early if polling is taking a long time.
								/*
								if ( time() - $poll_start_time > $poll_time_limit ) {
									$results['polling start time']     = $poll_start_time;
									$results['polling end time']       = time();
									$results['polling execution time'] = time() - $poll_start_time;
									$results['polling time limit']     = $poll_time_limit;

									return $results;
								}
								*/
							}
						}
					}
				}
			}
		}
		$results['polling start time']     = $poll_start_time;
		$results['polling end time']       = time();
		$results['polling execution time'] = time() - $poll_start_time;

		Yarns_Microsub_Posts::delete_old_posts( $storage_period ); // Clear old posts.

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
	public static function poll_site( $url, $channel_uid, $storage_period = null ) {
		if ( null === $storage_period ) {
			$storage_period = get_option( 'yarns_storage_period' );
		}
		$site_results             = array();
		$site_results['feed url'] = $url;
		$feed                     = Yarns_Microsub_Parser::parse_feed( $url, 20 );

		// If this is a preview return the feed as is.
		if ( '_preview' === $channel_uid ) {
			return $feed;
		}

		// Otherwise (this is not a preview) check if each post exists and add accordingly.
		if ( isset( $feed['items'] ) ) {
			// Sort $feed['items'] oldest to newest.
			usort(
				$feed['items'],
				function ( $a, $b ) {
					if ( ! isset( $a['published'] ) ) {
						$a['published'] = yarns_convert_date( 'Y-m-d\TH:i:sP' );
					}
					if ( ! isset( $b['published'] ) ) {
						$b['published'] = yarns_convert_date( 'Y-m-d\TH:i:sP' );
					}

					return strtotime( $a['published'] ) - strtotime( $b['published'] );
				}
			);

			foreach ( $feed['items'] as $post ) {
				if ( isset( $post['url'] ) && isset( $post['type'] ) ) {
					if ( 'entry' === $post['type'] ) {
						// Only poll if the post is within the storage period.
						// Set $post['date'] to updated if it exists, otherwise use 'published'.
						if ( isset( $post['updated'] ) ) {
							$post['date'] = $post['updated'];
						} elseif ( isset( $post['published'] ) ) {
							$post['date'] = $post['published'];
						}

						if ( ! yarns_date_compare( $post, $storage_period ) ) {
							if ( static::poll_post( $post['url'], $post, $channel_uid ) ) {
								$site_results['items'][] = $post['url']; // this is just returned for debugging when manually polling.
							} else {
								$site_results['already_exists'] = $post['url'];
							}
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

		if ( isset( $feed['_parse_time'] ) ) {
			$parse_time = $feed['_parse_time'];
		} else {
			$parse_time = null;
		}

		// @todo: Get etag from $feed array and pass to update_polling_frequencies
		static::update_polling_frequencies( $channel_uid, $url, $n_posts_added, $parse_time );

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

			$valid_types = Yarns_Microsub_Channels::get_post_types( $channel_uid );
			if ( isset( $post['post-type'] ) ) {
				if ( ! in_array( strtolower( $post['post-type'] ), $valid_types, true ) ) {
					return false; // Do not save if it's a post type that is not excluded by the channel.
				}
			}

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
	 */
	public static function update_polling_frequencies( $channel_uid, $url, $n_posts_added, $parse_time ) {
		//@@todo: Change this to update_feed_meta.  This should (1) update polling frequencies and (2) update the feed name, summary, and _feed_type
		$channels    = json_decode( get_option( 'yarns_channels' ), true );
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

		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled'] = yarns_convert_date( 'Y-m-d H:i:s P' );

		if ( isset( $channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'] ) ) {
			$empty_poll_count = $channels[ $channel_key ]['items'][ $feed_key ]['_empty_poll_count'];
		} else {
			$empty_poll_count = 0;
		}

		$poll_frequencies = array( 1, 2, 4, 8, 12, 24 );
		if ( isset( $channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency'] ) ) {
			$poll_frequency = $channels[ $channel_key ]['items'][ $feed_key ]['_poll_frequency'];
		} else {
			$poll_frequency = $poll_frequencies[0];  // First array item is the default option.
		}

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

		// Log each poll attempt for debugging.
		if ( get_option( 'yarns_poll_log' ) ) {
			$poll_log = json_decode( get_option( 'yarns_poll_log' ), true );
		}
		$this_poll                      = array();
		$this_poll['date']              = yarns_convert_date( 'Y-m-d H:i:s P' );
		$this_poll['url']               = $url;
		$this_poll['channel_uid']       = $channel_uid;
		$this_poll['_empty_poll_count'] = $empty_poll_count;
		$this_poll['_poll_frequency']   = $poll_frequency;
		$this_poll['_n_posts_added']    = $n_posts_added;
		$this_poll['_parse_time']       = $parse_time;
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

		$channels[ $channel_key ]['items'][ $feed_key ]['_last_polled']      = yarns_convert_date( 'Y-m-d H:i:s P' );
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
