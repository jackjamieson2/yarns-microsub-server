<?php

/**
 * Microsub Posts Class
 *
 * @author Jack Jamieson
 */
class Yarns_Microsub_Posts {

	/**
	 * Initialize Class.
	 * Registers post types and taxonomy.
	 */
	public static function init() {

		// Register a post type for storing aggregated posts.
		register_post_type(
			'yarns_microsub_post',
			// CPT Options.
			array(
				'labels'      => array(
					'name'          => __( 'Yarns Microsub Posts', 'yarns-microsub-server' ),
					'singular_name' => __( 'Yarns Microsub Post', 'yarns-microsub-server' ),
				),
				'public'      => false,
				'has_archive' => false,
				'rewrite'     => array( 'slug' => 'yarns_post' ),
			)
		);

		// register custom taxonomy to group aggregated posts by channel.
		register_taxonomy(
			'yarns_microsub_post_channel',  // The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
			'yarns_microsub_post',       // post type name.
			array(
				'public'       => false,
				'hierarchical' => false,
				'label'        => 'Channel',  // Display name.
				'query_var'    => true,
				'rewrite'      => array(
					'slug' => 'channel', // This controls the base slug that will display before each term.
				),
			)
		);

		// register custom taxonomy to filter aggregated posts by type.
		register_taxonomy(
			'yarns_microsub_post_type',  // The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces)..
			'yarns_microsub_post',       // post type name.
			array(
				'public'       => false,
				'hierarchical' => false,
				'label'        => 'Type',  // Display name.
				'query_var'    => true,
				'rewrite'      => array(
					'slug' => 'type', // This controls the base slug that will display before each term.
				),
			)
		);

		// register post statuses for 'read' and 'unread'
		register_post_status(
			'yarns_unread',
			array(
				'label'                     => _x( 'Unread', 'post', 'yarns-microsub-server' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				// translators: 1. Singular Unread 2. Plural Unread
				'label_count'               => _n_noop( 'Unread (%s)', 'Unread (%s)', 'yarns-microsub-server' ),
			)
		);

		register_post_status(
			'yarns_read',
			array(
				'label'                     => _x( 'Read', 'post', 'yarns-microsub-server' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				// translators: 1. Singular Read 2. Plural Read
				'label_count'               => _n_noop( 'Read (%s)', 'Read (%s)', 'yarns-microsub-server' ),
			)
		);

	}

	/**
	 * Add a new post parsed from a feed.
	 *
	 * @param string $permalink The permalink of the source post.
	 * @param array  $post The post data.
	 * @param string $channel The channel to save the post to.
	 */
	public static function add_post( $permalink, $post, $channel ) {
		// In rare cases, a post may have multiple permalinks, in which case $permalink will be an array.
		// If that happens, use the first array item in the post title.
		if ( is_array( $permalink ) ) {
			$permalink = $permalink[0];
		}

		$my_post = array(
			'post_type'   => 'yarns_microsub_post',
			'post_title'  => $channel . '|' . $permalink,
			'post_status' => 'yarns_unread',

		);

		if ( isset( $post['published'] ) ) {
			$my_post['post_date'] = yarns_convert_date( 'Y-m-d H:i:s P', $post['published'] );
		} else {
			// If there is no published date, then fall back to the current time.
			$post['published'] = yarns_convert_date( 'Y-m-d\TH:i:sP' );
		}
		if ( isset( $post['updated'] ) ) {
			$my_post['post_modified'] = yarns_convert_date( 'Y-m-d H:i:s P', $post['updated'] );
		}

		// Create the post.
		$post_id = wp_insert_post( $my_post );

		// Add '_id' to the post.
		$post['_id'] = $post_id;

		// Mark the post as 'unread'.
		$post['_is_read'] = false;

		$post = encode_array( $post );

		// Set the channel of the post.
		wp_set_post_terms( $post_id, $channel, 'yarns_microsub_post_channel' );

		// Set the type of the post (for filtering).
		if ( isset( $post['post-type'] ) ) {
			wp_set_post_terms( $post_id, $post['post-type'], 'yarns_microsub_post_type' );
		} else {
			// default to 'article'.
			wp_set_post_terms( $post_id, 'article', 'yarns_microsub_post_type' );
		}

		$update_post_args = array(
			'ID'          => $post_id,
			'post_status' => 'yarns_unread',
		);
		wp_update_post( $update_post_args );

		// Save the post JSON as a custom meta field.
		update_post_meta( $post_id, 'yarns_microsub_json', $post );
	}


	/**
	 * Toggle the read status of an entry.
	 *
	 * @param array|string $entry_id        Single ID or array of IDs of posts to toggle.
	 * @param string       $read_status     The read status to set.
	 *
	 * @return array
	 */
	public static function toggle_read( $entry_id, $read_status ) {
		$response = array();
		// If $entry_id is an array, recursively process each item.
		if ( is_array( $entry_id ) ) {
			foreach ( $entry_id as $single_entry ) {
				$response[] = static::toggle_read( $single_entry, $read_status );
			}
		} else {
			$post = self::get_single_post( $entry_id );
			// Set '_is_read' to the new status if (a) the post exists, and (b) $read_status has changed
			if ( $post ) {
				if ( isset( $post['_is_read'] ) ) {
					if ( $post['_is_read'] !== $read_status ) {
						$post['_is_read'] = $read_status;
						update_post_meta( $entry_id, 'yarns_microsub_json', $post ); // Update meta (JSON feed sent to client)

						$read_status_string = ( $read_status ) ? 'yarns_read' : 'yarns_unread';
						$update_post_args   = array(
							'ID'          => $entry_id,
							'post_status' => $read_status_string,
						);
						wp_update_post( $update_post_args );
					}
				}
			}
			$response = array(
				'result'  => 'ok',
				'updated' => $post,
			);

		}
		return $response;

	}


	/**
	 * Toggle read status of a single post and everything before it.
	 *
	 * @param int    $entry_id      The ID of the post to toggle.
	 * @param string $channel       The channel containing the post.
	 * @param string $read_status   The read status to set.
	 *
	 * @return string
	 */
	public static function toggle_last_read( $entry_id, $channel, $read_status ) {
		// Get the timeline of all feed items published before $entry_id.
		$read_before_post = self::get_single_post( $entry_id );
		if ( ! isset( $read_before_post['published'] ) ) {
			return;
		}
		$before_date = $read_before_post['published'];
		$timeline    = Yarns_Microsub_Channels::timeline( $channel, $before = null, $after = null, $is_read = null, $num_posts = - 1, $before_date );

		foreach ( $timeline['items'] as $item ) {
			if ( $item['_id'] ) {
				static::toggle_read( $item['_id'], $read_status );
			}
		}
		$timeline = Yarns_Microsub_Channels::timeline( $channel, $before = null, $after = null, $is_read = null );

		return $timeline;
	}


	/**
	 * Delete all posts -- for debugging only.
	 */
	public static function delete_all_posts() {
		$args = array(
			'post_type'      => 'yarns_microsub_post',
			'posts_per_page' => - 1,
			'post_status'    => array( 'yarns_read', 'yarns_unread', 'publish' ), // 'publish' is no longer used, but this will ensure posts are removed from previous versions of yarns.
		);

		$query = new WP_Query( $args );

		while ( $query->have_posts() ) {
			$query->the_post();
			wp_delete_post( get_the_ID(), true );
		}
	}

	/**
	 * Deletes old posts to keep the database clean.
	 *
	 * @param int $storage_period   The number of days to store aggregated posts before deletion.
	 */
	public static function delete_old_posts( $storage_period ) {
		$date_before = yarns_convert_date( 'Y-m-d h:m:s', '-' . $storage_period . 'days' );
		$args        = array(
			'post_type'      => 'yarns_microsub_post',
			'posts_per_page' => - 1,
			'post_status'    => array( 'yarns_read', 'yarns_unread' ),
			'date_query'     => array(
				'before' => $date_before,
			),
		);

		$query = new WP_Query( $args );

		$count = 0;
		while ( $query->have_posts() ) {
			$count ++;
			$query->the_post();
			wp_delete_post( get_the_ID(), true );
		}
	}


	/**
	 * Parses json and returns array for a single post
	 *
	 * @param int   $id The ID of the post to retrieve.
	 * @param mixed $channel (Optional) Channel of the post (for filtering).
	 *
	 * @return mixed
	 */
	public static function get_single_post( $id, $channel = null ) {
		if ( null !== $channel ) {
			$terms = wp_get_post_terms( $id, 'yarns_microsub_post_channel', array( 'fields' => 'names' ) );
			if ( ! empty( $terms[0] ) ) {
				if ( $terms[0] !== $channel ) {
					// The requested channel does not match the returned post, so return nothing.
					return;
				}
			}
		}
		$post = get_post_meta( $id, 'yarns_microsub_json', true );
		if ( ! is_array( $post ) ) {
			$post = stripcslashes( $post );
		}
		return $post;
	}


}



