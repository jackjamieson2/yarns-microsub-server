<?php
/**
 * Aggregator class
 *
 * @author Jack Jamieson
 *
 */


class Yarns_Microsub_Aggregator {

	/* Check if a post exists */

	public static function exists($permalink,$channel){

		if (get_page_by_title( $channel."|".$permalink, OBJECT, "yarns_microsub_post" )){
			return true;
		}
	}

	public static function test_aggregator(){


        //$channels =  json_decode(get_site_option("yarns_channels"),True);
	    return self::poll();
    }



	/* Poll the entire channel list for new posts */
	public static function poll(){
		error_log("Polling for new posts");
		$results = [];
		// For each channel
		$channels =  json_decode(get_site_option("yarns_channels"),True);
		if ($channels){
			foreach ($channels as $channel){
			    //return $channel;
				$channel_uid = $channel['uid'];
				if (isset($channel['items'])){
					// For each feed in this channel
                    foreach ($channel['items'] as $channel_item){
                        if (isset($channel_item['url'])) {
                            $results[] = static::poll_site($channel_item['url'], $channel_uid);
                        }
                    }
                }
            }
        }
        error_log("finished polling. Results = \n" . json_encode($results));
		return $results;
	}

	// POlls a single site
	public static function poll_site($url, $channel_uid){
	    $site_results = [];
        $feed = Yarns_Microsub_Parser::parse_feed($url);
        //return $feed;
        foreach ($feed['items'] as $post) {
            if (isset($post['url'])) {
                $site_results[] = static::poll_post($post['url'], $post, $channel_uid, $feed);
            }
        }
	    return $site_results;
    }

	// Process single posts
    /* 1. Check if post exists
     * 2. If not, add it
     * 3. Is so, skip it
     */
    public static function poll_post($permalink, $post, $channel_uid, $feed){
        if (!static::exists($permalink, $channel_uid)) {
            // For RSS posts, use the parsed post from $feed
            if ($feed['_feed_type'] == 'rss') {
                return Yarns_Microsub_Posts::add_post($permalink, $post, $channel_uid);
            } else {
                /*// Try just loading the post from the feed rather than fetching the individual permalink
                * $content = file_get_contents($permalink);
                * $full_post = parser::mergeparse($content, $permalink);
                * Yarns_Microsub_Posts::add_post($permalink, $full_post, $channel_uid);
                */
                return Yarns_Microsub_Posts::add_post($permalink, $post, $channel_uid);
            }
        } else {
            return "already exists " . $permalink;
        }
    }

}

