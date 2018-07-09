<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */
Class channels {

	//Returns a list of the channels
	public static function get(){ 
		if (get_site_option("yarns_channels")){
			$channels =  json_decode(get_site_option("yarns_channels"));
		}  else {
			// For testing purposes, returns a hard-coded list of channels
			/*
			$channels = [];

			$array = [ 
				"uid"=> "notifications",
				"name"=> "Notifications", 
				"unread"=> 0 ];

	    	$channels[] = $array;

			$array2 = [
	      		"uid"=> "indieweb",
				"name"=> "IndieWeb2",
				"unread"=> 0
	    	];

	    	$channels[] = $array2;
	    	*/
		}
		return [
      		'channels' => $channels
    	];
	}

//Delete a channel 
	public static function delete($uid){
		// Rewrite the channel list with the selected channel removed
			// probably better ways to do this 
		$new_channel_list = [];
		if (get_site_option("yarns_channels")){
			$channels = json_decode(get_site_option("yarns_channels"));
			error_log("Starting list: ".  json_encode($channels));
			//check if the channel already exists
			foreach ($channels as $item){
				if($item){
					error_log("item->uid: " . $item->uid);
					error_log("$uid " . $uid);
					if ($item->uid === $uid){
						error_log("deleting:" . $item->uid);
					} else {
						// Keep this channel in the new list
						$new_channel_list[] = $item;
					}
				}
			}
		}
		update_option("yarns_channels",json_encode($new_channel_list));
		error_log("Ending list: ".  json_encode($new_channel_list));
		error_log("Saved list: " . get_site_option("yarns_channels"));
		return "deleted";
	}

	//Add a channel 
	public static function add($new_channel_name){ 
		//delete_option("yarns_channels");
		//return json_decode(get_site_option("yarns_channels"));
		if (get_site_option("yarns_channels")){
			$channels = json_decode(get_site_option("yarns_channels"));
			//check if the channel already exists
			foreach ($channels as $item){
				if($item){
					if ($item->name == $new_channel_name){
						//item already exists, so return existing item
						return $item;
					}
				}
			}
		} else {
			$channels = [];
		}
		// Create the channel
		$new_channel = [
			"uid" => sanitize_title($new_channel_name),
			"name" => $new_channel_name,
		];

		$channels[] = $new_channel;
		update_option("yarns_channels",json_encode($channels));

		return json_decode(json_encode($new_channel));
  
		//get_site_option
		//update_option

	}


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
