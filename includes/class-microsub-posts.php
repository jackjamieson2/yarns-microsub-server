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
	    
	}
	


	public static function add_post($permalink, $post){
		$my_post = array(
		  'post_type'	  => 'yarns_microsub_post',
		  'post_title'    => $permalink,
		  'post_content'  => $post,
		  'post_status'   => 'publish',
		);
		// Insert the post into the database
		wp_insert_post( $my_post );

	}

	
}

?>