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
					'name'          => __( 'Yarns Microsub Posts' ),
					'singular_name' => __( 'Yarns Microsub Post' ),
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
				'public'      => false,
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
				'public'      => false,
				'hierarchical' => false,
				'label'        => 'Type',  // Display name.
				'query_var'    => true,
				'rewrite'      => array(
					'slug' => 'type', // This controls the base slug that will display before each term.
				),
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
			'post_status' => 'publish',

		);

		if ( isset( $post['published'] ) ) {
			$my_post['post_date'] = date( 'Y-m-d H:i:s', strtotime( $post['published'] ) );
		}
		if ( isset( $post['updated'] ) ) {
			$my_post['post_modified'] = date( 'Y-m-d H:i:s', strtotime( $post['updated'] ) );

		}

		// Create the post.
		$post_id = wp_insert_post( $my_post );

		// Add '_id' to the post.
		$post['_id'] = $post_id;

		// Mark the post as 'unread'.
		$post['_is_read'] = false;

		// Add a permalink field for debugging.
		$post['_permalink'] = $permalink;

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

		// Save the post JSON as a custom meta field.
		update_post_meta( $post_id, 'yarns_microsub_json', $post );
	}


	/**
	 * Toggle the read status of an entry.
	 *
	 * @param array|string $entry_id        Single ID or array of IDs of posts to toggle.
	 * @param string       $read_status     The read status to set.
	 */
	public static function toggle_read( $entry_id, $read_status ) {
		// If $entry_id is an array, recursively process each item.
		if ( is_array( $entry_id ) ) {
			foreach ( $entry_id as $single_entry ) {
				static::toggle_read( $single_entry, $read_status );
			}
		} else {
			$post = self::get_single_post( $entry_id );
			// Set '_is_read' to true if (a) the post exists, and (b) is unread.
			if ( $post ) {
				if ( $post['_is_read'] !== $read_status ) {
					$post['_is_read'] = $read_status;
					update_post_meta( $entry_id, 'yarns_microsub_json', $post );
				}
			}
		}
		return;
	}


	/**
	 * Toggle read status of a single post and everythign before it.
	 *
	 * @param int    $entry_id      The ID of the post to toggle.
	 * @param string $channel       The channel containing the post.
	 * @param string $read_status   The read status to set.
	 *
	 * @return string
	 */
	public static function toggle_last_read( $entry_id, $channel, $read_status ) {
		// Get the timeline.
		$timeline = Yarns_Microsub_Channels::timeline( $channel, $before = $entry_id + 1, $after = null, $num_posts = - 1 );
		foreach ( $timeline['items'] as $item ) {
			if ( $item['_id'] ) {
				static::toggle_read( $item['_id'], $read_status );
			}
		}
		return $timeline;
	}


	/**
	 * Delete all posts -- for debugging only.
	 *
	 * @param string $channel (Optional) Limit deletion to a specific channel.
	 *
	 * @return string
	 */
	public static function delete_all_posts( $channel ) {
		// This function is only available in local auth mode.
		if ( ! MICROSUB_LOCAL_AUTH === 1 ) {
			return 'not authorized';
		}
		$args = array(
			'post_type'                   => 'yarns_microsub_post',
			'post_status'                 => 'publish',
			'yarns_microsub_post_channel' => $channel,
			'posts_per_page'              => - 1,
		);

		if ( $channel ) {
			$args['yarns_microsub_post_channel'] = $channel;
		}

		$query = new WP_Query( $args );

		while ( $query->have_posts() ) {
			$query->the_post();
			wp_delete_post( get_the_ID(), true );
		}

		return 'deleted posts';
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



