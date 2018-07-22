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
	public static function timeline($channel, $after, $before){
			//Get all the posts of type yarns_microsub_post
		$query = new WP_Query(array(
		    'post_type' => 'yarns_microsub_post',
		    'post_status' => 'publish',
		    'yarns_microsub_post_channel' => $channel
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


	/*
	// return dummy results for testing
	$result1 = [ 
		"type"			=> 	"feed",
		"url" 			=>	$url,
		"name" 			=>	"name",
		"photo" 		=>	"https://percolator.today/images/cover.jpg",
		"description"	=> 	"description",
	];	 

	$result2 = [ 
		"type"			=> 	"feed",
		"url" 			=>	$url,
		"name" 			=>	"name",
		"photo" 		=>	"https://percolator.today/images/cover.jpg",
		"description"	=> 	"description",
		"author"		=> 	array(
			"name"		=> 	"Aaron Parecki",
			"url"		=>	"https://aaronparecki.com/",
			"photo"		=>	"https://aaronparecki.com/images/profile.jpg"
		)
		
	];	 
	*/


	public static function search($query){

		// Check if $query is a valid URL, if not try to generate one
		$url = validate_url($query);
		$results = [];
		$html = file_get_contents($url); //get the html returned from the following url
		$dom = new DOMDocument();
		libxml_use_internal_errors(TRUE); //disable libxml errors

		if(!empty($html)){ //if any html is actually returned
			$dom->loadHTML($html);
			$hfeed_exists = False;
			//Check each link for rss/atom feeds
			$website_links = $dom->getElementsByTagName("link");
			
			if($website_links->length > 0){
				foreach($website_links as $link){
					if ($link->getAttribute("rel")=="feed"){
						$feed['url'] = $link->getAttribute("href");
						$feed['_type'] = "h-feed";
						$feeds[] = $feed;
						$hfeed_exists = True;
					} else if ($link->getAttribute("rel")=="alternate"){
						$feed['url'] = $link->getAttribute("href");
						$feed['_type'] = $link->getAttribute('type');
						$feeds[] = $feed;
					}
				}
			}

			// If no h-feed was found as a <link>, then check if the $url itself is an h-feed
			if ($hfeed_exists == False){
				if (parser::find_hfeed_in_page($url)){

					// Found an h-feed on the page
					$feed['url'] = $url;
					$feed['_type'] = "h-feed";
					$feed['debug'] = parser::find_hfeed_in_page($url);
					$feeds[] = $feed;

				}
			}
		
		
			// Now that feeds have been discovered, do some clean up and then populate additional fields (author info, photo, description, etc.)
			
			
			foreach($feeds as $i=>$feed) {
				// Convert relative urls to absolute
				if (parse_url($feeds[$i]['url'],PHP_URL_HOST) == False){
					$feeds[$i]['url'] = $url . $feeds[$i]['url'];
					//$feeds[$i]['url'] = parse_url($url,PHP_URL_SCHEME) + parse_url($url,PHP_URL_HOST) + $feeds[$i]['url'];
				}

				/*
				// 
				//  Commenting this out to improve speed
				//

				// populate additional info
				if ($feeds[$i]['_type']=='h-feed'){
					$mf = Mf2\fetch($feeds[$i]['url']);
					foreach ($mf['items'] as $mf_item) {
						if ("{$mf_item['type'][0]}"=="h-feed"){
							if(array_key_exists('name', $mf_item['properties'])) {
								$feeds[$i]['name'] = "{$mf_item['properties']['name'][0]}";
							}
							if(array_key_exists('photo', $mf_item['properties'])) {
								$feeds[$i]['photo'] = "{$mf_item['properties']['photo'][0]}";
							}
							if(array_key_exists('summary', $mf_item['properties'])) {
								$feeds[$i]['description'] = "{$mf_item['properties']['summary'][0]}";
							}							
						}

						if ("{$mf_item['type'][0]}"=="h-card"){
							$feeds[$i]['author']= $mf_item['properties'];
						} 
					}
				}
				*/
				$feeds[$i]['type'] = 'feed';
			}
			
			return ['results' => $feeds]; 
		}
	}

	public static function preview($url){
		//return get_timeline();

		return parser::parse_hfeed($url, $preview=true);
		// Check if this is an h-feed or other

		//if h-feed:
			// Run h-feed parser
		// if rss:
			// Run simplepie parser
	}


	public static function list_follows($query_channel){

		if (get_site_option("yarns_channels")){


			//return json_decode(get_site_option("yarns_channels")); // for debugging
			$channels =  json_decode(get_site_option("yarns_channels"),True);

			
			foreach ($channels as $key=>$channel){
				if ($channel['uid'] == $query_channel){
				//This is the channel to be returned
					if (isset($channel['items'])){
						return ['items' => $channel['items']];
					} else { 
						// testing
						
						return $channel;
						return; // no subscriptions yet, so return nothign
					}					
				}
			}
		}
		return; // no matches, so return nothing
	}

	public static function follow($query_channel, $url){
		$new_follow = [
			"type"=>"feed",
			"url"=>$url
		];
		//$channels = [];
		if (get_site_option("yarns_channels")){
			$channels = json_decode(get_site_option("yarns_channels"),True);
			// Check if the channel has any subscriptions yet
			foreach ($channels as $key=>$channel){
				if ($channel['uid'] == $query_channel){
					if (!array_key_exists('items', $channel)){
						// no subscritpions in this channel yet
							$channels[$key]['items'] = [];
					} else {
						//Check if the subscription exists in this channel
						foreach ($channel['items'] as $feed){
							if ($feed['url'] == $url){
								// already following this feed, exit early
								return $new_follow;
							}
						}
					}
					
					// Add the new follow to the selected channel
					$channels[$key]['items'][] = $new_follow;
					update_option("yarns_channels",json_encode($channels));
					return $new_follow;
				}
			} 
		} 
		return; // channel does not exist, so return nothing
	}
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

function validate_url($possible_url){
	//If it is already a valid URL, return as-is
	if(is_url($possible_url)){
		return $possible_url;
	} 

	// If just a word was entered, append .com
 	if(preg_match('/^[a-z][a-z0-9]+$/', $possible_url)) {
          // if just a word was entered, append .com
          $possible_url = $possible_url . '.com';
    }

    // If the URL is missing a trailing '/' add it
    //$possible_url = wp_slash($possible_url)
    if(substr($possible_url, -1) != '/'){
        $possible_url .= '/';
    }
	// If missing a scheme, prepend with 'http://', otherwise return as-is
	 return parse_url($possible_url, PHP_URL_SCHEME) === null ? 'http://' . $possible_url : $possible_url;
}

function is_url($query){
	if(filter_var($query, FILTER_VALIDATE_URL)){
		return true; 
	} else {
		return false;
	}
}


function isRSS($feedtype){
	$rssTypes = array ('application/rss+xml','application/atom+xml','application/rdf+xml','application/xml','text/xml','text/xml','text/rss+xml','text/atom+xml');
    if (in_array($feedtype,$rssTypes)){
    	return True;
    }
}


function get_dummy_hentry(){

}
