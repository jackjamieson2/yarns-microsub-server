<?php

/**
 * Microsub channels class
 *
 * @author Jack Jamieson
 */
class Yarns_Microsub_Channels {

	/**
	 * Returns a list of the channels
	 *
	 * @param bool $details Whether or not to return details about the channels.
	 *
	 * @return array
	 *
	 * @@todo: add unread counts to each channel.
	 */
	public static function get( $details = false ) {

		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ), true );
		}

		if ( ! empty( $channels ) ) {
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

			foreach ( $channels as $key => $channel ) {
				if ( false === $details ) {
					// The channels list also includes lists of feeds and post-types filter options, so remove them if details === false.
					$channels[ $key ] = static::strip_channel_details( $channel );
				}

				$channels[ $key ]['unread'] = static::get_unread_count( $channel['uid'] );
			}
		} else {
			return false;
		}

		$results = array(
			'channels' => $channels,
		);

		return $results;
	}

	/**
	 * Given a channel object, returns the object without details (such as a list of items, valid post-types), etc.
	 * (These details are used in Yarns backend, but should not be sent to clients.
	 *
	 * @param array $channel The channel from which to strip details.
	 *
	 * @return array
	 */
	private static function strip_channel_details( $channel ) {
		if ( array_key_exists( 'items', $channel ) ) {
			unset( $channel['items'] );
		}
		if ( array_key_exists( 'post-types', $channel ) ) {
			unset( $channel['post-types'] );
		}

		return $channel;
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
		$new_channel_list = array();
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ) );
			// check if the channel already exists.
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->uid === $uid ) {
						Yarns_MicroSub_Plugin::debug_log( 'deleting:' . $item->uid );
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


	/**
	 * Add a new channel
	 *
	 * @param string $new_channel_name Name of the new channel.
	 *
	 * @return array
	 */
	public static function add( $new_channel_name ) {
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ) );
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
			$channels = array();
		}

		// Generate a random uid.
		$uid        = static::generate_uid();
		$post_types = static::all_post_types();

		$new_channel = array(
			'uid'        => $uid,
			'name'       => $new_channel_name,
			'post-types' => $post_types,
		);

		$channels[] = $new_channel;
		update_option( 'yarns_channels', wp_json_encode( $channels ) );

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
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ), true );
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
	 * Returns a list of channel uids
	 *
	 * @param array $channels Array of channels.
	 *
	 * @return array            Array of Channel UIDs.
	 */
	private static function get_channel_uids( $channels ) {
		$channel_uids = array();
		foreach ( $channels as $channel ) {
			$channel_uids[] = $channel['uid'];
		}

		return $channel_uids;
	}

	/**
	 * Sorts function. Sorts by key 'order'.
	 *
	 * @param mixed $a
	 * @param mixed $b
	 *
	 * @return mixed
	 */
	private static function sort_by_order( $a, $b ) {
		return (int) $a['order'] - (int) $b['order'];
	}

	/**
	 * Change the order in which channels are presented.
	 *
	 * @param array $input Array of channel uids in the new order.
	 *
	 * @return boolean          Return true on success.
	 */
	public static function order( $input ) {

		$current_channels = json_decode( get_option( 'yarns_channels' ), true );

		$current_channel_uids = static::get_channel_uids( $current_channels );
		// Validate that all items in $channels refer to channels that exist.
		foreach ( $input as $key => $channel ) {
			if ( ! in_array( $channel, $current_channel_uids, true ) ) {
				unset( $input[ $key ] );
			}
		}
		if ( count( $input ) < 1 ) {
			// There are no valid channels in the list, so return false.
			return false;
		}

		// Map the current channel order and the input channel order .
		$input_order = array();
		$input_key   = 0;
		foreach ( $current_channels as $key => $channel ) {
			if ( in_array( $channel['uid'], $input, true ) ) {
				$input_order[ $key ] = $input[ $input_key ];
				$input_key ++;
			}
		}

		// Add 'order' field to each channel.
		foreach ( $current_channels as $key => $channel ) {
			if ( in_array( $channel['uid'], $input_order, true ) ) {
				$new_key                           = array_search( $channel['uid'], $input_order, true );
				$current_channels[ $key ]['order'] = $new_key;
			} else {
				$current_channels[ $key ]['order'] = $key;
			}
		}

		// Sort channel list by 'order'
		usort( $current_channels, array( 'Yarns_Microsub_Channels', 'sort_by_order' ) );

		update_option( 'yarns_channels', wp_json_encode( $current_channels ) );

		return true;
	}

	/**
	 * Updates the filter options for a channel.
	 *
	 * @return mixed
	 */
	public static function save_filters() {
		$response = '';

		if ( isset( $_POST['uid'] ) && isset( $_POST['options'] ) ) {
			$uid            = sanitize_text_field( wp_unslash( $_POST['uid'] ) );
			$options        = $_POST['options'];
			$all_post_types = static::all_post_types();

			// validate submitted options.
			if ( is_array( $options ) ) {
				foreach ( $options as $key => $option ) {
					if ( ! in_array( $option, $all_post_types, true ) ) {
						unset( $options[ $key ] );
					}
				}
			} else {
				// If options is not an array, something went wrong. In this case, reset to all post types.
				$options = $all_post_types;
			}

			// Update the channel name.
			if ( isset( $_POST['channel'] ) ) {
				$name = sanitize_text_field( wp_unslash( $_POST['channel'] ) );
				static::update( $uid, $name );
				$response .= 'Updated Name.  ';
			}

			// update channel filters
			if ( get_option( 'yarns_channels' ) ) {
				$channels = json_decode( get_option( 'yarns_channels' ), true );
				// check if the channel already exists.
				foreach ( $channels as $key => $item ) {
					if ( $item ) {
						if ( $item['uid'] === $uid ) {
							$channels[ $key ]['post-types'] = $options;
							update_option( 'yarns_channels', wp_json_encode( $channels ) );
							$response .= 'Updated filters.  ';
						}
					}
				}
			}
		}
		// Poll the channel.
		Yarns_Microsub_Aggregator::poll( true, $uid );
		echo esc_html( $response );
		wp_die();

	}

	/**
	 * Returns a list of allowed post-types
	 *
	 * @param string $query_channel The ID of the channel.
	 *
	 * @return mixed
	 */
	public static function get_post_types( $query_channel ) {
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ), true );
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					// This is the channel to be returned.
					if ( isset( $channel['post-types'] ) ) {
						$valid_types = array();
						foreach ( $channel['post-types'] as $type ) {
							$valid_types[] = $type;
						}

						return $valid_types;
					}
				}
			}
		}

		return;
	}


	/**
	 * Returns count of unread posts per channel
	 *
	 * @param string $channel The channel to check.
	 *
	 * @return integer
	 */
	public static function get_unread_count( $channel ) {
		$args  = array(
			'channel'   => $channel,
			'is_read'   => 'false',
			'num_posts' => - 1,
		);
		$query = static::get_timeline_query( $args );

		return $query->post_count;
	}


	/**
	 * Returns a WP_Query object, given arguments.
	 *
	 * @param array $args   Array of arguments to define the query.
	 *
	 * @return WP_Query
	 */
	private static function get_timeline_query( $args ) {
		$valid_types = static::get_post_types( $args['channel'] );

		$query_args = array(
			'post_type'      => 'yarns_microsub_post',
			'post_status'    => 'yarns_unread',
			'posts_per_page' => $args['num_posts'],
			'orderby'        => 'post_date',
			'order'          => 'DESC',
			'tax_query'      => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'yarns_microsub_post_channel',
					'field'    => 'name',
					'terms'    => $args['channel'],
				),
				array(
					'taxonomy' => 'yarns_microsub_post_type',
					'field'    => 'name',
					'terms'    => $valid_types,
				),
			),
		);

		if ( isset( $args['is_read'] ) && 'false' === $args['is_read'] ) {
			$query_args['post_status'] = 'yarns_unread';
		} else {
			$query_args['post_status'] = array( 'yarns_unread', 'yarns_read' );
		}

		// If we are fetching a limited number of posts, then handle pagination.
		if ( -1 !== $args['num_posts'] ) {
			$id_list = array();

			if ( $args['after'] ) {
				// Fetch additional posts older (lower id) than $args['after'].
				$id_list = array_merge( $id_list, range( 1, (int) $args['after'] - 1 ) );
			}
			if ( $args['before'] ) {
				// Check for additional posts newer (higher id) than $args['before'].
				$new_posts = static::find_newer_posts( $args['before'], $args );
				if ( $new_posts ) {
					$id_list = array_merge( $id_list, $new_posts );
				}
			}
			// use rsort to sort the list of ids in descending order.
			if ( $id_list ) {
				if ( is_array( $id_list ) ) {
					rsort( $id_list );
				}
				$query_args['post__in'] = $id_list;
			}
		}
		// notes for paging: https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts.
		$query = new WP_Query( $query_args );

		return $query;
	}

	/**
	 * Retrieve Entries in a Channel (Timeline endpoint action)
	 *
	 * @param string  $channel      The channel ID.
	 * @param string  $after        For pagination.
	 * @param string  $before       For pagination.
	 * @param boolean $is_read      Will omit posts marked as read if set to false.
	 * @param int     $num_posts    The number of posts to return.
	 *
	 * @return string
	 */
	public static function timeline( $channel, $after, $before, $is_read, $num_posts = 40, $before_date = null ) {

		$args = array(
			'channel'   => $channel,
			'after'     => $after,
			'before'    => $before,
			'is_read'   => $is_read,
			'num_posts' => $num_posts,
		);

		if ( isset( $before_date ) ) {
			$args['date_query'] = array( 'before' => $before_date );
		}

		$query = static::get_timeline_query( $args );

		$timeline_items = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$id                = get_the_ID();
			$item              = Yarns_Microsub_Posts::get_single_post( $id );
			$timeline_items [] = $item;
			$ids[]             = $id;
		}

		wp_reset_postdata();

		if ( $timeline_items ) {
			if ( is_array( $timeline_items ) ) {
				$timeline['items'] = array_filter( $timeline_items ); // remove null items if any exist.

				// Sort by published date in descending order.
				usort(
					$timeline['items'],
					function ( $a, $b ) {
						return strtotime( $b['published'] ) - strtotime( $a['published'] );
					}
				);

				// Add 'before' variable.
				$timeline['paging']['before'] = (string) max( $ids );
				// Only add 'after' if there are older posts.
				if ( self::older_posts_exist( min( $ids ), $channel ) ) {
					$timeline['paging']['after'] = (string) min( $ids );
				}

				return $timeline;
			}
		} else {
			$results = 'empty results';

			return $results;
		}

	}



	/**
	 * Check if the channel has any posts older than $id
	 *
	 * @param int    $id        The ID of the post to compare with.
	 * @param string $channel   The channel to check.
	 *
	 * @return bool
	 */
	private static function older_posts_exist( $id, $channel ) {
		// see https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts.
		$post_ids = range( 1, $id - 1 );

		$args = array(
			'post__in'                    => $post_ids,
			'post_type'                   => 'yarns_microsub_post',
			'yarns_microsub_post_channel' => $channel,
			'post_status'                 => array( 'yarns_unread', 'yarns_read' ),
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
	 * @return mixed
	 */
	public static function list_follows( $query_channel ) {
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ), true );
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					// This is the channel to be returned.
					if ( isset( $channel['items'] ) ) {
						return array( 'items' => $channel['items'] );
					} else {
						return; // no subscriptions yet, so return nothing.
					}
				}
			}
		}
	}


	/** Follow a new URL in a channel. Or unfollow existing URL
	 *
	 * @param string $query_channel The channel ID.
	 * @param string $url The URL to be followed/unfollowed.
	 * @param bool $unfollow Toggle follow vs. unfollow.
	 *
	 * @return array|void
	 */
	public static function follow( $query_channel, $url, $unfollow = false ) {
		$url        = stripslashes( $url );
		$new_follow = array(
			'type' => 'feed',
			'url'  => $url,
		);
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ), true );
			// Check if the channel has any subscriptions yet.
			foreach ( $channels as $key => $channel ) {
				if ( $channel['uid'] === $query_channel ) {
					if ( ! array_key_exists( 'items', $channel ) ) {
						// no subscriptions in this channel yet.
						$channels[ $key ]['items'] = array();
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

		return new WP_Microsub_Error( 'invalid_request', 'tried to add feed to a channel that does not exist', 400 );

	}

	/**
	 * Return the key for a specific channel.
	 *
	 * @param array $channels Array of channels.
	 * @param string $uid The Channel ID.
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
	 * @param array $channels Array of channels.
	 * @param int $channel_key Key for a specific channel.
	 * @param string $url URL of a feed.
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



	/*
	@@todo Add ability to mute feeds in a channel

	GET
	action=mute
	channel={uid}
	Retrieve the list of users that are muted in the given channel.

	POST
	action=mute
	channel={uid}
	url={url}
	Mute a user in a channel, or with the uid global mutes the user across every channel.


	Unmute
	POST
	To unmute a user, use action=unmute and provide the URL of the account to unmute. Unmuting an account that was previously not muted has no effect and should not be considered an error.
	 */


	/**
	 * Returns a list of post ids that are newer than $before
	 *
	 * @param int $before ID of the post to use for comparison.
	 * @param array $args Arguments array.
	 *
	 * @return mixed
	 */
	public static function find_newer_posts( $before, $args ) {
		$args['posts_per_page'] = - 1;

		$query = new WP_Query( $args );

		while ( $query->have_posts() ) {
			$query->the_post();

			$id    = get_the_ID();
			$ids[] = $id;
		}
		wp_reset_postdata();

		// Only keep ids that are newer (higher) than $before.
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


	/**
	 * Generate a random UID, ensuring it is unique.
	 *
	 * @return string
	 */
	private static function generate_uid() {
		$uid = uniqid();

		// Confirm the uid is unique (it always should be, but just in case).
		if ( get_option( 'yarns_channels' ) ) {
			$channels = json_decode( get_option( 'yarns_channels' ) );
			// check if the channel already exists.
			foreach ( $channels as $item ) {
				if ( $item ) {
					if ( $item->uid === $uid ) {
						// the $uid already exists, so make a new one.
						$uid = static::generate_uid();
					}
				}
			}
		}

		return $uid;
	}

	/**
	 * Returns an array of all post types recognized by Yarns.
	 *
	 * @return array
	 */
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
			'other',
		);
	}

	/**
	 * Returns a list of post types displayed in a specific channel.
	 *
	 * @param array $channel The channel whose post types to be returned.
	 *
	 * @return array
	 */
	public static function channel_post_types( $channel ) {
		if ( isset( $channel['post-types'] ) ) {
			return $channel['post-types'];
		} else {
			// If the channel types haven't been set then return all types.
			return static::all_post_types();
		}
	}
}
