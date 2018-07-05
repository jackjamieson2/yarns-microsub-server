<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */

function get_channels($user_id){
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

function get_timeline($user_id){
}

/**
* Retrieve the list of feeds being followed in the given channel. 
*/
function get_follows(){

}
