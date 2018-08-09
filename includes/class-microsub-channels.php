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
			$channels =  json_decode(get_site_option("yarns_channels"), true);
		}
		// The channels list also includes a list of feeds for each channel, so remove them before returning
        foreach ($channels as $key=>$channel) {
                if (array_key_exists('items',$channel)){
                    unset($channels[$key]['items']);
            }
        }

        // for testing, hardcode an 'unread' value for each channel
        foreach ($channels as $key=>$channel) {
		    //$channels[$key]['unread'] = 10;
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

		return $new_channel;
  
		//get_site_option
		//update_option

	}



	/*
	UPDATE A CHANNEL
	action=channels
    channel={uid}
    name={channel name}

	*/
    public static function update($channel, $name){
        if (get_site_option("yarns_channels")){
            $channels = json_decode(get_site_option("yarns_channels"),True);
            //check if the channel already exists
            foreach ($channels as $key=>$item){
                if($item){
                    if ($item['uid'] == $channel){
                        $channels[$key]['name'] = $name;
                        update_option("yarns_channels",json_encode($channels));
                        return $channels[$key];

                        // Update this item
                        //item already exists, so return existing item
                       // return $item;
                    }
                }
            }
        } else {
            static::add($name);
        }

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
	public static function timeline($channel, $after, $before, $num_posts=20){
			//Get all the posts of type yarns_microsub_post


        $args = array(
            'post_type' => 'yarns_microsub_post',
            'post_status' => 'publish',
            'yarns_microsub_post_channel' => $channel,
            'posts_per_page' => $num_posts
        );

        $id_list = [];
		// Pagination
		if ($after){
		    // Fetch additional posts older (lower id) than $after
            $id_list = array_merge($id_list,range(1, (int)$after -1) );
        }
        if ($before){
		    // Check for additional posts newer (higher id) than $before
            $id_list = array_merge($id_list,static::find_newer_posts($before, $args) );
            //$id_list[] = static::find_newer_posts($before, $args);
        }
        // use rsort to sort the list of ids in descending order
        if ($id_list){
            if (is_array($id_list)){
                rsort($id_list);
            }
            $args['post__in'] = $id_list;
        }


        //return $args;

        // notes for paging: https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts
        $ids = []; // store a list of post ids returned by the query
        $timeline_items = [];
        $query = new WP_Query($args);

        while ($query->have_posts()) {
		    $query->the_post();

		    $id = get_the_ID();
            $item = Yarns_Microsub_Posts::get_single_post($id);

            $timeline_items [] = $item;
            $ids[] = $id;
        }


        wp_reset_query();

		if ($timeline_items) {
            $timeline['items'] = $timeline_items;
            $timeline['paging']['before'] = (string)max($ids);
            // Only add 'after' if there are older posts
            if (self::older_posts_exist(min($ids),$channel)){
                $timeline['paging']['after'] = (string)min($ids);
            }
            return $timeline;
		}
		return "error";
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

	public static function follow($query_channel, $url, $unfollow=false ){
	    $url = stripslashes($url);
	    error_log("converted to {$url}");
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
						// no subscriptions in this channel yet
							$channels[$key]['items'] = [];
					} else {
						//Check if the subscription exists in this channel
						foreach ($channel['items'] as $channel_key=>$feed){
							if ($feed['url'] == $url){
							    error_log("already following feed");
                                error_log("unfollow = {$unfollow}");
								// already following this feed

                                if ($unfollow == true){
                                    // if $unfollow == true then remove the feed
                                    //return "key = {$key} | channel_key = {$channel_key}";
                                    unset($channels[$key]['items'][$channel_key]);
                                    update_option("yarns_channels",json_encode($channels));
                                    return;

                                } else {
                                    // if $unfollow == false then exit early because the subscription already exists
                                    return;
                                }

							}
						}
					}
					
					// Add the new follow to the selected channel
					if ($unfollow==false){
                        $channels[$key]['items'][] = $new_follow;
                        update_option("yarns_channels",json_encode($channels));
                        return $new_follow;
                    }

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
    public static function unfollow($query_channel, $url){

    }




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



	// Returns a list of post ids that are newer
    public static function find_newer_posts($before, $args){

        $args['posts_per_page'] = -1;

        $query = new WP_Query($args);

        while ($query->have_posts()) {
            $query->the_post();

            $id = get_the_ID();
            $ids[] = $id;
        }
        wp_reset_query();


        // Only keep ids that are newer (higher) than $before
        foreach ($ids as $key=>$id){
            if (!$id>$before){
                unset($ids['$key']);
            }
        }

        return $ids;




    }


}