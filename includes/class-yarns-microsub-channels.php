<?php
/**
 * Microsub channels class
 *
 * @author Jack Jamieson
 *
 */

class Yarns_Microsub_Channels {
/*
 * @@todo: add unread counts to each channel.
 */

	/**
	 * Returns a list of the channels
	 *
	 * @param bool $details Whether or not to return details about the channels.
	 *
	 * @return array
	 */
	public static function get( $details = false ) {
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ), true );
		}
		// The channels list also includes lists of feeds and post-types filter options, so remove them if details === false.
		if ( false === $details ) {
			foreach ( $channels as $key => $channel ) {
				if ( array_key_exists( 'items', $channel ) ) {
					unset( $channels[ $key ]['items'] );
				}

				if ( array_key_exists( 'post-types', $channel ) ) {
					unset( $channels[ $key ]['post-types'] );
				}
			}
		}

		return [
			'channels' => $channels,
		];
	}

	/**
	 * Get a single channel object with details
	 *
	 * @param string $uid The channel ID.
	 *
	 * @return mixed
	 */
	public static function get_channel( $uid ) {
		$channels = self::get( true )['channels'];
		foreach ( $channels as $channel ) {
			if ( $uid === $channel['uid'] ) {
				return $channel;
			}
		}
	}

	/**
	 * Delete a channel
	 *
	 * @param string $uid The channel ID.
	 *
	 * @return string
	 */
	public static function delete( $uid ) {
		// Rewrite the channel list with the selected channel removed.
		$new_channel_list = [];
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ) );
			// check if the channel already exists.
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->uid === $uid ) {
						error_log( 'deleting:' . $item->uid );
					} else {
						// Keep this channel in the new list.
						$new_channel_list[] = $item;
					}
				}
			}
		}
		update_option( 'yarns_channels', wp_json_encode( $new_channel_list ) );
		return 'deleted';
	}


	//Add a channel

	/**
	 * @param string $new_channel_name Name of the new channel.
	 *
	 * @return array
	 */
	public static function add( $new_channel_name ) {
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ) );
			// check if the channel already exists.
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->name === $new_channel_name ) {
						// item already exists, so return existing item.
						return $item;
					}
				}
			}
		} else {
			$channels = [];
		}

		// Generate a random uid.
		$uid        = static::generate_uid();
		$post_types = static::all_post_types();

		$new_channel = [
			'uid'        => $uid,
			'name'       => $new_channel_name,
			'post-types' => $post_types,
		];

		$channels[] = $new_channel;
		update_option( 'yarns_channels', json_encode( $channels ) );

		return $new_channel;
	}


	/**
	 * Update an existing channel (i.e. change its name)
	 *
	 * @param string $uid The Channel ID.
	 * @param string $name The new name of the channel.
	 *
	 * @return mixed
	 */
	public static function update( $uid, $name ) {
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ), true );
			// check if the channel already exists.
			foreach ( $channels as $key => $item ) {
				if ( $item ) {
					if ( $item['uid'] === $uid ) {
						$channels[ $key ]['name'] = $name;
						update_option( 'yarns_channels', wp_json_encode( $channels ) );
						return $channels[ $key ];
					}
				}
			}
		} else {
			static::add( $name );
		}
	}

	/**
	 * Updates the filter options for a channel.
	 *
	 * @param string $channel The channel ID.
	 * @param $name
	 *
	 * @return mixed
	 */
	public static function save_filters() {
		if ( isset( $_POST['uid'] ) && isset( $_POST['options'] ) ) {
			$uid     = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$options = wp_unslash( $_POST['options'] );
			if ( get_site_option( 'yarns_channels' ) ) {
				$channels = json_decode( get_site_option( 'yarns_channels' ), true );
				// check if the channel already exists.
				foreach ( $channels as $key => $item ) {
					if ( $item ) {
						if ( $item['uid'] === $uid ) {
							$channels[ $key ]['post-types'] = $options;
							update_option( 'yarns_channels', wp_json_encode( $channels ) );
							echo 'Saved';

						}
					}
				}
			}
		}

		wp_die();
	}

	/**
	 * Returns a list of allowed post-types
	 *
	 * @param string $query_channel The ID of the channel.
	 *
	 * @return string|void
	 */
	public static function get_post_types( $query_channel ) {
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ), true );
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					// This is the channel to be returned.
					if ( isset( $channel['post-types'] ) ) {
						$valid_types = '';
						foreach ( $channel['post-types'] as $type ) {
							$valid_types .= $type . ',';
						}
						return $valid_types;
					}
				}
			}
		}
		return;
	}

	/*
	action=timeline
	Retrieve Entries in a Channel

	GET

	Retrieve the entries in a given channel.

	Parameters:

		action=timeline
		channel={uid}
		after={cursor}
		before={cursor}
	*/

	/**
	 * Retrieve Entries in a Channel
	 *
	 * @param string $channel The channel ID.
	 * @param string $after For pagination.
	 * @param string $before For pagination.
	 * @param int    $num_posts The number of posts to return.
	 *
	 * @return string
	 */
	public static function timeline( $channel, $after, $before, $num_posts = 20 ) {
		$valid_types = static::get_post_types( $channel );

		$args = array(
			'post_type'                   => 'yarns_microsub_post',
			'post_status'                 => 'publish',
			'yarns_microsub_post_channel' => $channel,
			'yarns_microsub_post_type'    => $valid_types,
			'posts_per_page'              => $num_posts,
		);

		$id_list = [];

		// Pagination.
		if ( $after ) {
			// Fetch additional posts older (lower id) than $after.
			$id_list = array_merge( $id_list, range( 1, (int) $after - 1 ) );
		}
		if ( $before ) {
			// Check for additional posts newer (higher id) than $before.
			$new_posts = static::find_newer_posts( $before, $args );
			if ( $new_posts ) {
				$id_list = array_merge( $id_list, $new_posts );
			}
		}
		// use rsort to sort the list of ids in descending order.
		if ( $id_list ) {
			if ( is_array( $id_list ) ) {
				rsort( $id_list );
			}
			$args['post__in'] = $id_list;
		}

		// notes for paging: https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts.
		$ids            = []; // store a list of post ids returned by the query.
		$timeline_items = [];
		$query          = new WP_Query( $args );

		while ( $query->have_posts() ) {
			$query->the_post();
			$id                = get_the_ID();
			$item              = Yarns_Microsub_Posts::get_single_post( $id );
			$timeline_items [] = $item;
			$ids[]             = $id;
		}


		wp_reset_postdata();


		// Filter out posts that should be omitted.
		if ( $timeline_items ) {
			$timeline['items']            = $timeline_items;
			$timeline['paging']['before'] = (string) max( $ids );
			// Only add 'after' if there are older posts.
			if ( self::older_posts_exist( min( $ids ), $channel ) ) {
				$timeline['paging']['after'] = (string) min( $ids );
			}
			return $timeline;
		}
		return 'error';
	}


	/**
	 * Check if the channel has any posts older than $id
	 *
	 * @param int    $id The ID of the post to compare with.
	 * @param string $channel The channel to check.
	 *
	 * @return bool
	 */
	private static function older_posts_exist( $id, $channel ) {
		// see https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts.
		$post_ids = range( 1, $id - 1 );

		$args = array(
			'post__in'                    => $post_ids,
			'post_type'                   => 'yarns_microsub_post',
			'post_status'                 => 'publish',
			'yarns_microsub_post_channel' => $channel,
			'posts_per_page'              => 1,
		);
		if ( get_posts( $args ) ) {
			return true;
		}
	}


	/**
	 * List the feeds being followed in a single channel.
	 *
	 * @param string $query_channel The ID of the channel.
	 *
	 * @return array|void
	 */
	public static function list_follows( $query_channel ) {
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ), true );
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					// This is the channel to be returned.
					if ( isset( $channel['items'] ) ) {
						return [ 'items' => $channel['items'] ];
					} else {
						return; // no subscriptions yet, so return nothing.
					}
				}
			}
		}
		return; // no matches, so return nothing.
	}



	/** Follow a new URL in a channel. Or unfollow existing URL
	 * @param string $query_channel The channel ID.
	 * @param string $url The URL to be followed/unfollowed.
	 * @param bool   $unfollow  Toggle follow vs. unfollow.
	 *
	 * @return array|void
	 */
	public static function follow( $query_channel, $url, $unfollow = false ) {
		$url        = stripslashes( $url );
		$new_follow = [
			'type' => 'feed',
			'url'  => $url,
		];

		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ), true );
			// Check if the channel has any subscriptions yet.
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					if ( ! array_key_exists( 'items', $channel ) ) {
						// no subscriptions in this channel yet.
						$channels[ $key ]['items'] = [];
					} else {
						// Check if the subscription exists in this channel.
						foreach ( $channel['items'] as $channel_key => $feed ) {
							if ( $feed['url'] === $url ) {
								// already following this feed.
								if ( true === $unfollow ) {
									// if $unfollow == true then remove the feed.
									unset( $channels[ $key ]['items'][ $channel_key ] );
									update_option( 'yarns_channels', wp_json_encode( $channels ) );
									return;
								} else {
									// if $unfollow == false then exit early because the subscription already exists.
									return;
								}
							}
						}
					}

					// Add the new follow to the selected channel.
					if ( false === $unfollow ) {
						$channels[ $key ]['items'][] = $new_follow;
						update_option( 'yarns_channels', wp_json_encode( $channels ) );
						// Now that the new feed is added, poll it right away.
						// Get the channel and feed keys.
						Yarns_Microsub_Aggregator::poll_site( $url, $query_channel );

						return $new_follow;
					}
				}
			}
		}

		return; // channel does not exist, so return nothing
	}

	/**
	 * Return the key for a specific channel.
	 *
	 * @param array  $channels      Array of channels.
	 * @param string $uid           The Channel ID.
	 *
	 * @return int|void
	 */
	public static function get_channel_key( $channels, $uid ) {
		foreach ( $channels as $channel_key => $channel ) {
			if ( isset( $channel['uid'] ) ) {
				if ( $uid === $channel['uid'] ) {
					return $channel_key;
				}
			}
		}
		return;
	}

	/**
	 * Return the key for a specific channel.
	 *
	 * @param array  $channels      Array of channels.
	 * @param int    $channel_key   Key for a specific channel.
	 * @param string $url           URL of a feed.
	 *
	 * @return int|void
	 */
	public static function get_feed_key( $channels, $channel_key, $url ) {
		foreach ( $channels[ $channel_key ]['items'] as $feed_key => $feed ) {
			if ( isset( $feed['url'] ) ) {
				if ( $url === $feed['url'] ) {
					return $feed_key;
				}
			}
		}
		return;
	}



	/*    Muting

	GET

	action=mute
	channel={uid}

	Retrieve the list of users that are muted in the given channel.*/

	/*POST

    action=mute
    channel={uid}
    url={url}

	Mute a user in a channel, or with the uid global mutes the user across every channel. 
	*/

	/*Unmute

	POST

	To unmute a user, use action=unmute and provide the URL of the account to unmute. Unmuting an account that was previously not muted has no effect and should not be considered an error. 
	 */


	// Returns a list of post ids that are newer
	public static function find_newer_posts( $before, $args ) {

		$args['posts_per_page'] = - 1;

		$query = new WP_Query( $args );

		while ( $query->have_posts() ) {
			$query->the_post();

			$id    = get_the_ID();
			$ids[] = $id;
		}
		wp_reset_query();


		// Only keep ids that are newer (higher) than $before
		if ( $ids ) {
			foreach ( $ids as $key => $id ) {
				if ( ! $id > $before ) {
					unset( $ids['$key'] );
				}
			}

			return $ids;
		}

		return;
	}


	private static function generate_uid() {
		$uid = uniqid();

		// Confirm the uid is unique (it always should be, but just in case)
		if ( get_site_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_site_option( 'yarns_channels' ) );
			//check if the channel already exists
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->uid == $uid ) {
						// the $uid already exists, so make a new one
						$uid = static::generate_uid();
					}
				}
			}
		}

		return $uid;
	}


	public static function all_post_types() {
		return array(
			'photo',
			'video',
			'article',
			'note',
			'checkin',
			'itinerary',
			'repost',
			'reply',
			'like',
			'bookmark',
			'other'
		);
	}
}