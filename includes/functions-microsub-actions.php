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

	/*Search

	action=search 
	query = {URI to search}*/

				/*HTTP/1.1 200 Ok
			Content-type: application/json

			{
			  "results": [
			    {
			      "type": "feed",
			      "url": "https://aaronparecki.com/",
			      "name": "Aaron Parecki",
			      "photo": "https://aaronparecki.com/images/profile.jpg",
			      "description": "Aaron Parecki's home page"
			    },
			    {
			      "type": "feed",
			      "url": "https://percolator.today/",
			      "name": "Percolator",
			      "photo": "https://percolator.today/images/cover.jpg",
			      "description": "A Microcast by Aaron Parecki",
			      "author": {
			        "name": "Aaron Parecki",
			        "url": "https://aaronparecki.com/",
			        "photo": "https://aaronparecki.com/images/profile.jpg"
			      }
			    },
			    { ... }
			  ]
			}*/


	public static function search($query){
	
		// Check if $query is a valid URL, if not try to generate one
		$url = return_url($query);
		
	
	$html = file_get_contents($url); //get the html returned from the following url
	$dom = new DOMDocument();
	libxml_use_internal_errors(TRUE); //disable libxml errors
	if(!empty($html)){ //if any html is actually returned
		$dom->loadHTML($html);
		$thetitle = $dom->getElementsByTagName("title");
		$returnArray[] = array("type"=>"title", "data"=>$thetitle[0]->nodeValue);		
		
		$website_links = $dom->getElementsByTagName("link");
		// Check for feeds as <link> elements
		$found_hfeed = FALSE; 

		if($website_links->length > 0){
			foreach($website_links as $row){
				// Convert relative feed URL to absolute URL if needed
				$feedurl = phpUri::parse($siteurl)->join($row->getAttribute("href"));

				if (isRSS($row->getAttribute("type"))){ // Check for rss feeds first
					//Return the feed type and absolute feed url 
					$returnArray[] = array("type"=>$row->getAttribute("type"), "data"=>$feedurl);
				}
				elseif($row->getAttribute("type")=='text/html'){ // Check for h-feeds declared using a <link> tag
					$returnArray[] = array("type"=>"h-feed", "data"=>$feedurl);
					$found_hfeed = TRUE; // H-feed has been found, so we stop looking
				}
			}
		}

		// Also here check for h-feed in the actual html
			$mf = Mf2\parse($html,$siteurl);
			$output_log ="Output: <br>";

			foreach ($mf['items'] as $mf_item) {
				if ($found_hfeed == FALSE) {
					$output_log .= "A {$mf_item['type'][0]} called {$mf_item['properties']['name'][0]}<br>";
					if ("{$mf_item['type'][0]}"=="h-feed"||  // check 1
						"{$mf_item['type'][0]}"=="h-entry"){
						//Found an h-feed (probably)
						$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
						$found_hfeed = TRUE; 
					} else {
						$output_log .="Searching children... <br>";

						foreach($mf_item['children'] as $child){
							if ($found_hfeed == FALSE) {
								$output_log .= "A CHILD {$child['type'][0]} called {$child['properties']['name'][0]}<br>";
								if ("{$child['type'][0]}"=="h-feed"|| // check 1
									"{$child['type'][0]}"=="h-entry"){
									//Found an h-feed (probably)
									$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
									$found_hfeed = TRUE; 
								}
							}
						}
					}
				}

			}
		echo json_encode($returnArray);
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}


/*	Preview

	action=preview

	POST

	    action=preview
	    url={url}*/

	    /*The response includes the list of items in the feed if available, in the same format as returned by the #Timelines API call. */


   /* Following

	action=follow

	GET

	    action=follow
	    channel={uid}*/




	/*POST

	Follow a new URL in a channel.

	    action=follow
	    channel={uid}
	    url={url}
*/

	/*Unfollowing

	action=unfollow

	POST

	    action=unfollow
	    channel={uid}
	    url={url}*/



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



/*
* UTILITY FUNCTIONS
*
*/

function return_url($possible_url){
	//If it is already a valid URL, return as-is
	if(is_url($possible_url){
		return $possible_url;
	} 

	// If just a word was entered, append .com
 	if(preg_match('/^[a-z][a-z0-9]+$/', $possible_url)) {
          // if just a word was entered, append .com
          $possible_url = $possible_url . '.com';
    }

	// Check if http:// or https:// is missing
	if (!preg_match("((https?|ftp)\:\/\/)?",$possible_url){
		$possible_url = "http://" + $possible_url;	
	}

	return $possible_url;
}

function is_url($query){
	if(filter_var($query, FILTER_VALIDATE_URL){
		return true; 
	} else {
		return false;
	}
}

}
