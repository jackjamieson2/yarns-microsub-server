<?php
/**
 * Microsub channels class 
 *
 * @author Jack Jamieson
 *
 */
class channels {



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

        $args = array(
            'post_type' => 'yarns_microsub_post',
            'post_status' => 'publish',
            'yarns_microsub_post_channel' => $channel,
            'posts_per_page' => 20
        );
		$query = new WP_Query($args);

		// Pagination (to be implemented)
		if ($after){

        }
        if ($before){

        }

        // notes for paging: https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts
        $ids = []; // store a list of post ids returned by the query
        $timeline_items = [];
		while ($query->have_posts()) {
		    $query->the_post();
		    $item = json_decode(get_the_content(),True);
		    if (isset($item['content']['html'])){
		    	$item['content']['html'] = html_entity_decode($item['content']['html']);
		    }
		    $id = get_the_ID();
		    $item = json_decode(get_post_meta($id, 'yarns_microsub_json', true),true);
            // Decode html special characters in content['html']
            if (isset ($item['content']['html'])){
                $item['content']['html'] = htmlspecialchars_decode( $item['content']['html']);
            }

            $timeline_items [] = $item;
            $ids[] = $id;
        }


        wp_reset_query();


        $timeline['items'] = $timeline_items;
        $timeline['before'] = max($ids);
        // Only add 'after' if there are older posts
        if (self::older_posts_exist(min($ids),$channel)){
            $timeline['after'] = min($ids);
        }
        return $timeline;


	}

	/* Check if the channel has any posts older than $id */
	private static function older_posts_exist($id, $channel){
	    // see https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts
	    $post_ids = range(1, $id -1);

        $args = array(
            'post__in' => $post_ids,
            'post_type' => 'yarns_microsub_post',
            'post_status' => 'publish',
            'yarns_microsub_post_channel' => $channel,
            'posts_per_page' => 1
        );
        if (get_posts($args)) {
            return true;
        }
    }


	/* Following

	action=follow

	GET

	    action=follow
	    channel={uid}*/
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



	/*POST

	Follow a new URL in a channel.

	    action=follow
	    channel={uid}
	    url={url}
	*/

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