<?php /**
 * Microsub Posts Class
 *
 * @author Jack Jamieson
 *
 */


class Yarns_Microsub_Posts {

	public static function init (){
		
		// Register a post type for storing aggregated posts
		register_post_type( 'yarns_microsub_post',
	    // CPT Options
	        array(
	            'labels' => array(
	                'name' => __( 'Yarns Microsub Posts' ),
	                'singular_name' => __( 'Yarns Microsub Post' )
	            ),
	            'public' => true,
	            'has_archive' => false,
	            'rewrite' => array('slug' => 'yarns_post'),
	        )
	    );

	    // register custom taxonomy to group aggregated posts by channel
	    register_taxonomy(  
        	'yarns_microsub_post_channel',  //The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces). 
        	'yarns_microsub_post',       //post type name
	        array(  
	            'hierarchical' => false,  
	            'label' => 'Channel',  //Display name
	            'query_var' => true,
	            'rewrite' => array(
	                'slug' => 'channel' // This controls the base slug that will display before each term
	            )
	        )  
	    );  



	    
	}
	


	public static function add_post($permalink, $post, $channel){
		$my_post = array(
		  'post_type'	  => 'yarns_microsub_post',
		  'post_title'    => $channel."|".$permalink,
		  'post_status'   => 'publish'

		);

		if (isset($post['published'])){
			//return $post['published'];
			$my_post["post_date"] = date('Y-m-d H:i:s', strtotime($post['published']));
			
			//$my_post["post_date"] = strtotime($post['published']);
			//return json_encode($my_post);
		}
		if (isset($post['updated'])){
			$my_post["post_modified"] = date('Y-m-d H:i:s', strtotime($post['updated']));

		}

		// Create the post
		$post_id = wp_insert_post( $my_post );

		// Set the channel of the post 
		wp_set_post_terms( $post_id, $channel, 'yarns_microsub_post_channel' );

		// Save the post JSON as a custom meta field
		update_post_meta($post_id, 'yarns_microsub_json', json_encode($post));

		return $post;

		// Return the post id
		return $post_id;
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

	To mark an entry read as well as everything before it in the timeline:

	    action=timeline
	    method=mark_read
	    channel={uid}
	    last_read_entry={entry-id}
    */

    /*Remove Entry from a Channel

	POST

	Parameters:

	    action=timeline
	    method=remove
	    channel={uid}
	    entry={entry-id} or entry[]={entry-id}*/
}

?>