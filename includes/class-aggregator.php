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

	public static function test_aggregator($url){
	    $channel_uid = 'test-2';
        $feed = parser::parse_feed($url)['items'];
        foreach ($feed as $item){
            if (isset($item['url'])){
                $permalink= $item['url'];
                if (!static::exists($permalink,$channel_uid)){
                    return Yarns_Microsub_Posts::add_post($permalink, $item,$channel_uid);
                }
            }
        }

    }

	/* Poll for new posts */ 
	public static function poll(){
		error_log("Polling for new posts");
		
		// for debugging, return a list of URLs and post IDs
		$results = [];
		// For each channel
		$channels =  json_decode(get_site_option("yarns_channels"),True);
		if ($channels){
			foreach ($channels as $channel){
				$channel_uid = $channel['uid'];
				if (isset($channel['items'])){
					// For each feed in this channel
					foreach ($channel['items'] as $item){
						if (isset($item['url'])){
							$feed = parser::parse_feed($item['url'], $preview=true)['items'];

							// get new posts from this feed.
								// TO DO - revise parse_hfeed to be able to handle both rss and h-feed
							// Check if the posts already exist
							foreach ($feed as $item){
								if (isset($item['url'])){
									$permalink= $item['url'];
									if (!static::exists($permalink,$channel_uid)){
										$content = file_get_contents($permalink);
										$full_post = parser::mergeparse($content,$permalink);
										//return $full_post;										
										
										Yarns_Microsub_Posts::add_post($permalink, $full_post,$channel_uid);
										$results[][] = "added " . $permalink;
									}  else {
										$results[][] = "already exists " . $permalink;
									}

								}
							}
						}
					}

				}
			}
		}
		return $results;
	}
	

}

