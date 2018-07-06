<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */

function get_channels(){
	// For testing purposes, returns a hard-coded list of channels
		$channels = [];

		$array = [ 
			"uid"=> "notifications",
			"name"=> "Notifications", 
			"unread"=> 0 ];

    	$channels[] = $array;

		$array2 = [
      		"uid"=> "indieweb",
			"name"=> "IndieWeb",
			"unread"=> 0
    	];

    	$channels[] = $array2;

		return [
      		'channels' => $channels
    	];
}


/** 
*
Action == timeline
GET

Retrieve the entries in a given channel.

Parameters:

    action=timeline
    channel={uid}
    after={cursor}
    before={cursor}
*/

function get_timeline(){

	//Get all the posts of type yarns_microsub_post
	$query = new WP_Query(array(
	    'post_type' => 'yarns_microsub_post',
	    'post_status' => 'publish'
	));

	$timeline = [];
	while ($query->have_posts()) {
	    $query->the_post();
	    $timeline[] = json_decode(get_the_content());
	}

	wp_reset_query();

	return [
      		'items' => $timeline
    	];
	 

}

/**
* Retrieve the list of feeds being followed in the given channel. 
*/
function get_follows(){

}
