<?php /**
 * Microsub Posts Class
 *
 * @author Jack Jamieson
 *
 */


class Yarns_Microsub_Posts {

	public static function init() {

		// Register a post type for storing aggregated posts
		register_post_type(
			'yarns_microsub_post',
			// CPT Options
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

		// register custom taxonomy to group aggregated posts by channel
		register_taxonomy(
			'yarns_microsub_post_channel',  //The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
			'yarns_microsub_post',       //post type name
			array(
				'hierarchical' => false,
				'label'        => 'Channel',  //Display name
				'query_var'    => true,
				'rewrite'      => array(
					'slug' => 'channel', // This controls the base slug that will display before each term
				),
			)
		);

		// register custom taxonomy to filter aggregated posts by type
		register_taxonomy(
			'yarns_microsub_post_type',  //The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
			'yarns_microsub_post',       //post type name
			array(
				'hierarchical' => false,
				'label'        => 'Type',  //Display name
				'query_var'    => true,
				'rewrite'      => array(
					'slug' => 'type', // This controls the base slug that will display before each term
				),
			)
		);


	}


	public static function add_post( $permalink, $post, $channel ) {
		$my_post = array(
			'post_type'   => 'yarns_microsub_post',
			'post_title'  => $channel . '|' . $permalink,
			'post_status' => 'publish',

		);

		if ( isset( $post['published'] ) ) {
			//return $post['published'];
			$my_post['post_date'] = date( 'Y-m-d H:i:s', strtotime( $post['published'] ) );

			//$my_post["post_date"] = strtotime($post['published']);
			//return json_encode($my_post);
		}
		if ( isset( $post['updated'] ) ) {
			$my_post['post_modified'] = date( 'Y-m-d H:i:s', strtotime( $post['updated'] ) );

		}

		// Create the post
		$post_id = wp_insert_post( $my_post );

		// Add '_id' to the post
		$post['_id'] = $post_id;
		// Mark the post as 'unread'
		$post['_is_read'] = false;

		// Add a permalink field for debugging
		$post['_permalink'] = $permalink;

		$post = encode_array( $post );
		//$post = json_encode($post);
		//return $post;

		// Set the channel of the post
		wp_set_post_terms( $post_id, $channel, 'yarns_microsub_post_channel' );

		// Set the type of the post (for filtering)
		if ( isset( $post['post-type'] ) ) {
			wp_set_post_terms( $post_id, $post['post-type'], 'yarns_microsub_post_type' );
		}

		// Save the post JSON as a custom meta field
		update_post_meta( $post_id, 'yarns_microsub_json', $post );

		return get_post_meta( $post_id, 'yarns_microsub_json' );

	}

	/*
	Mark Entries Read

	POST

	To mark one or more individual entries as read:

	Parameters:

		action=timeline
		method=mark_read
		channel={uid}
		entry={entry-id} or entry[]={entry-id}

	*/

	public static function toggle_read( $entry_id, $read_status ) {
		// If $entry_id is an array, recursively process each item
		if ( is_array( $entry_id ) ) {
			foreach ( $entry_id as $single_entry ) {
				static::toggle_read( $single_entry, $read_status );
			}
		} else {
			// Get the post json
			$post = self::get_single_post( $entry_id );
			//$post = json_decode(get_post_meta($entry_id, 'yarns_microsub_json'), true);

			// Set '_is_read' to true if (a) the post exists, and (b) is unread
			if ( $post ) {
				if ( $post['_is_read'] != $read_status ) {
					$post['_is_read'] = $read_status;
					update_post_meta( $entry_id, 'yarns_microsub_json', $post );
				}
			}
		}
		return;
	}

	/*
	 * 	To mark an entry read as well as everything before it in the timeline:

		action=timeline
		method=mark_read
		channel={uid}
		last_read_entry={entry-id}
	 */
	public static function toggle_last_read( $entry_id, $channel, $read_status ) {
		// Get the timeline
		$timeline = Yarns_Microsub_Channels::timeline( $channel, $before = $entry_id + 1, $after = null, $num_posts = - 1 );
		//return $timeline;
		foreach ( $timeline['items'] as $item ) {
			//return $item;
			if ( $item['_id'] ) {
				static::toggle_read( $item['_id'], $read_status );
			}
		}
		return $timeline;

		//return "not yet implemented";
	}



		/*Remove Entry from a Channel

		POST

		Parameters:

			action=timeline
			method=remove
			channel={uid}
			entry={entry-id} or entry[]={entry-id}*/


	/* Delete all posts -- for debugging */
	public static function delete_alL_posts( $channel ) {
		// This function is only available in local auth mode
		if ( ! MICROSUB_LOCAL_AUTH == 1 ) {
			return 'not authorized';}
		$args = array(
			'post_type'                   => 'yarns_microsub_post',
			'post_status'                 => 'publish',
			'yarns_microsub_post_channel' => $channel,
			'posts_per_page'              => -1,
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

	// Parses json and returns array for a single post
	public static function get_single_post( $id ) {
		/* For some reason, some items are retrieved as encoded. Not sure why */
		$post = get_post_meta( $id, 'yarns_microsub_json', true );

		if ( ! is_array( $post ) ) {
			$post = stripcslashes( $post );
			//$post = json_decode($post);
		}

		//$post = json_decode(get_post_meta($id,'yarns_microsub_json',true),true);

		//$post = decode_array(json_decode(get_post_meta($id, 'yarns_microsub_json', true),true));

		return $post;
	}




}



