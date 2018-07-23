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
							$mf2_items = parser::parse_hfeed($item['url'], $preview=true)['items'];
							// get new posts from this feed.
								// TO DO - revise parse_hfeed to be able to handle both rss and h-feed
							// Check if the posts already exist
							foreach ($mf2_items as $item){
								if (isset($item['url'])){
									$permalink= $item['url'];
									//$permalink = "http://tantek.com/2018/190/b1/scrollbar-gutter-move-to-css-scrollbars";
									// Check if the post already exists

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
	
	
	/* Add a post to the yarns_reader_posts table in the database */
	function yarns_reader_add_feeditem($item){
		


		global $wpdb;

		if($published < 1){
			$published = time();
		}
		$published = date('Y-m-d H:i:s',$published);
		$updated = date('Y-m-d H:i:s',$updated);

		// If the summary is exactly the same as the content, then empty content since it is redundant
		if (strip_tags($summary) == strip_tags($content)){
			$summary = $content;
			$content = "";
		} elseif (strlen($summary) <1) {
			// if there is no summary, copy the content to the summary
			$summary = $content;
		} 

		// truncate the summary if it is too long or contains more than one image
		if (strlen(strip_tags($summary))>500 || count($photos)>1) { 
			// since we're truncating, copy summary to content if content is empty
			if (strlen($content)<1){
				$content = $summary;
			}
			//Strip imgs and any tags not listed as allowed below:  ("<a><p><br>.....")
			$summary = substr(strip_tags($summary,"<a><p><br><blockquote><b><code><del><em><h1><h2><h3><h4><h5><h6><li><ol><ul><pre><q><strong><sub><u>"),0,500) . "..."; 
		}
	
		//If the author url is not known, then just use the site url
		if (empty($authorurl)){$authorurl = $siteurl;}

		// Add the post (if it doesn't already exist)
		$table_name = $wpdb->prefix . "yarns_reader_posts";
		if (yarns_item_exists($permalink) != True) {
		//if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){
			$rows_affected = $wpdb->insert( $table_name,
				array(	
					'feedid'=>$feedid,
					'sitetitle'=>$sitetitle,
					'siteurl'=>$siteurl,
					'title' => $title,
					'summary'=> $summary,
					'content' => $content,
					'published'=> $published,
					'updated'=> $updated,
					'authorname' => $authorname,
					'authorurl' => $authorurl,
					'authoravurl' => $avurl,
					'permalink' => $permalink,
					'location'=> $location,
					'syndication'=> $syndication,
					'in_reply_to'=> $in_reply_to,
					'photo'=> $photo,
					'posttype' => $type,
					'viewed' =>false,
				 ) );

			if($rows_affected == false){
				$lastquery = $wpdb->last_query;
				$lasterror = $wpdb->last_error;
				yarns_reader_log("could not insert post into database!");
				yarns_reader_log($lastquery);
				yarns_reader_log($lasterror);


				die("could not insert post into database!" .$permalink.": ".$title);
			}else{
				//yarns_reader_log("added ".$permalink.": ".$title);
			}
		}else{
			//yarns_reader_log("post already exists: " .$permalink.": ".$title);
		}	 
	}

}









/*
** Aggregator function (run using cron job or by refresh button)
*/
add_action( 'wp_ajax_yarns_reader_aggregator', 'yarns_reader_aggregator' );
function yarns_reader_aggregator() {
	yarns_reader_log("aggregator was run");
	global $wpdb;
	$table_following = $wpdb->prefix . "yarns_reader_following";
	//Iterate through each item in the 'following' table.
	foreach( $wpdb->get_results("SELECT * FROM ".$table_following.";") as $key => $row) {
		$feedurl = $row->feedurl;
		$feedtype = $row->feedtype;
		$sitetitle = $row->sitetitle;
		$siteurl = $row->siteurl;
		$feedid = $row->id;
		yarns_reader_log("checking for new posts in ". $feedurl);

		/****   RSS FEEDS ****/
		if (isRss($feedtype)){
			//yarns_reader_log ($feedurl . " is an rss feed (or atom, etc)");

			$feed = yarns_reader_fetch_feed($feedurl,$feedtype);
		
			if(is_wp_error($feed)){
				//yarns_reader_log($feed->get_error_message());
				trigger_error($feed->get_error_message());
				yarns_reader_log("Feed read Error: ".$feed->get_error_message());
			} else {
				//yarns_reader_log("Feed read success.");
			}
			$feed->enable_cache(false);
			$feed->strip_htmltags(false);   
			$items = $feed->get_items();
			usort($items,'date_sort');
			foreach ($items as $item){
				$title = $item->get_title();
				$summary = html_entity_decode ($item->get_description());
				$content = html_entity_decode ($item->get_content());
				$published=$item->get_date('U');
				$updated=0;
				//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
				$title = clean_the_title($title,$content);
				// Several fallback options to set author name/site title
				$authorname = $sitetitle;  // This uses the site title entered by the user (not the site title specified in the site feed)
				$avurl='';
				$permalink=$item->get_permalink();
				$location='';
				$photo='';
				$type='rss';
				try{
					yarns_reader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle);
				}catch(Exception $e){
					yarns_reader_log("Exception occured: ".$e->getMessage());
				}
			}
		} /****  H-FEEDS ****/ 
		elseif ($feedtype == "h-feed"){
			$feed = yarns_reader_fetch_hfeed($feedurl,$feedtype);
			foreach ($feed as $item){
				$title = $item['name'];
				$summary=$item['summary'];
				$content=$item['content'];
				$published = $item['published'];
				$updated = $item['updated'];
				$authorname = $item['author'];
				$authorurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible, or just use siteurl
				$avurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible
				$permalink = $item['url'];
				$location=$item['location'];
				$photo=$item['photo'];
				$syndication = $item['syndication'];
				$in_reply_to = $item['in-reply-to'];
				
				$authorurl = $item['author_url'];
				$avurl = $item['avurl'];
				
				$siteurl=$item['siteurl'];
				$feedurl = $url;
				$type = $item['type'];
				

				try{
					yarns_reader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle,$syndication,$in_reply_to);
					//yarns_reader_add_feeditem($permalink, $title, $content, $authorname, $authorurl, $time, $avurl, $siteurl, $feedurl, $type);
				}catch(Exception $e){
					yarns_reader_log("Exception occured: ".$e->getMessage());
				}
			}
			//yarns_reader_log ($feedurl . " is an h-feed");
		}
		remove_filter( 'wp_feed_cache_transient_lifetime', 'yarns_reader_feed_time' );
	}
	// Store the time of the this update
	$update_time = date('Y-m-d H:i:s', time());
	yarns_reader_log("Aggregator finished at ". $update_time);
	update_option( 'yarns_reader_last_updated', $update_time);

	//Clean up the feed items and log databases
	//$query = "SELECT * FROM ".$wpdb->prefix."yarns_reader_posts WHERE DATEDIFF(NOW(), `published`) > 1";

	/* TESTING FEATURE - identify posts older than 30 days and delete them automatically */
	// Currently this just logs posts older than 30 days with the heading "To be cleared"
	// Thinking about whether to implement this auto-clearing (a) always enabled, (b) optional.
	// Probably best as optional but enabled by default. 

	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'yarns_reader_posts` 
		WHERE DATEDIFF(NOW(),`published`) >30');
		
	if ( !empty( $items ) ) { 
		foreach ( $items as $item ) {
			$item_list .= $item->published . " - " . $item->permalink ."\n";
		}
		yarns_reader_log("To be cleared: " . $item_list);
	} else {
		yarns_reader_log("To be cleared: NONE");
	}


	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Fetch an RSS FEED and return its content
*/
function yarns_reader_fetch_feed($url,$feedtype) {
	require_once (ABSPATH . WPINC . '/class-feed.php');
	$feed = new SimplePie();
	//yarns_reader_log("Url is fetchable");
	$feed->set_feed_url($url);
	$feed->set_cache_class('WP_Feed_Cache');
	$feed->set_file_class('WP_SimplePie_File');
	$feed->set_cache_duration(30);
	$feed->enable_cache(false);
	$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy	
	$feed->init();
	$feed->handle_content_type();
	
	if ( $feed->error() )
		$errstring = implode("\n",$feed->error());
		//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
		if(stristr($errstring,"XML error")){
			yarns_reader_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			yarns_reader_log('simplepie-error: '.$errstring);
		}else{
			//yarns_reader_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
		}
	return $feed;
}

/*
** Fetch an H-FEED and return its content
*/
function yarns_reader_fetch_hfeed($url,$feedtype) {
	yarns_reader_log('fetching h-feed at '. $url);
	//Parse microformats at the feed-url
	$mf = Mf2\fetch($url);
	//Identify the h-feed within the parsed MF2
	$hfeed = "";
	$hfeed_path = "children"; // (default) in most cases, items within the h-feed will be 'children' 
	//Check if one of the top-level items is an h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-feed"){
				$hfeed = $mf_item;				
			} else {
				//If h-feed has not been found, check for a child-level h-feed
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-feed"){
							$hfeed = $child;	
						}
					}
				}
			}
		}
	}
	//If no h-feed was found, check for h-entries. If h-entry is found, then consider its parent the h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-entry"){
				$hfeed = $mf;		
				$hfeed_path	="items";
			} else {
				//If h-entries have not been found, check for a child-level h-entry
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-entry"){
							$hfeed = $mf_item;		
						}
					}
				}
			}
		}
	}

	//At this point, only proceed if h-feed has been found
	if ($hfeed == "") {
		//do nothing
		yarns_reader_log("no h-feed found");
	} else {
		//yarns_reader_log("Parsing h-feed at: ".$hpath);
		$site_url = $url;
		
		//Get permalinks for each item
		$hfeed_items = array();
		foreach ($hfeed[$hfeed_path] as $item) {
			if ("{$item['type'][0]}" == 'h-entry' ||
				"{$item['type'][0]}" == 'h-event' )
			{
				if ("{$item['properties']['url'][0]}"){
					$hfeed_item_urls[] = "{$item['properties']['url'][0]}";
				}
				
			}
		}

		//yarns_reader_log(json_encode($hfeed_items));
		//Fetch each post individually
		foreach ($hfeed_item_urls as $hfeed_item_url){
			//yarns_reader_log('following permalink at '. $hfeed_item_url);
			// Only proceed if the permalink has not previous been fetched
			if (yarns_item_exists($hfeed_item_url) !=True){
				$mf = Mf2\fetch($hfeed_item_url);
				//Fetch the full post from its permalink ONLY if it has not already been added
				foreach ($mf['items'] as $item) {
	  				if ("{$item['type'][0]}" == 'h-entry' ||
						"{$item['type'][0]}" == 'h-event' )
						// Only parse supported types (h-entry, h-event)
					{ 
						//yarns_reader_log("HFEED LOG: ". $hfeed_item_url . " | " ."{$item['properties']['url'][0]}" ); 
						
						//Only store an item_name if it is not equal to the content value
						if ("{$item['properties']['name'][0]}" !="{$item['properties']['content'][0]['value']}" ) {
							$item_name = "{$item['properties']['name'][0]}";
						} else {
							$item_name = '';
						}

						$item_type = "{$item['type'][0]}";
						$item_summary = "{$item['properties']['summary'][0]}";
						$item_published = strtotime("{$item['properties']['published'][0]}");
						$item_updated = strtotime("{$item['properties']['updated'][0]}");


						if ("{$item['properties']['location'][0]['properties']['name'][0]}"){
							// get full location h-card
							$location['name'] = "{$item['properties']['location'][0]['properties']['name'][0]}";
							$location['url']  = "{$item['properties']['location'][0]['properties']['url'][0]}";
							$location['latitude']= "{$item['properties']['location'][0]['properties']['latitude'][0]}";
							$location['longitude']  = "{$item['properties']['location'][0]['properties']['longitude'][0]}";
							$item_location = json_encode($location);

						} else if ("{$item['properties']['location'][0]['value']}"){
							// just get the location value
							$location['name'] = "{$item['properties']['location'][0]['value']}";
							$item_location = json_encode($location); 
						}

						//Note that location can be an h-card, but this script just gets the string value
						$item_url = $hfeed_item_url;
						$item_uid = "{$item['properties']['uid'][0]}";
						if ("{$item['properties']['syndication']}"){
							$syndication =  array();
							foreach ($item['properties']['syndication'] as $syndication_item) {
								$syndication[] = $syndication_item;		
							}
							$item_syndication = json_encode($syndication);
						}
						//$item_syndication = json_encode("{$item['properties']['syndication']}");

						if ("{$item['properties']['photo']}"){
							$photos =  array();
							foreach ($item['properties']['photo'] as $photo_item) {
								$photos[] = $photo_item;		
							}
							$item_photo = json_encode($photos);
						} else {
							//Clear $item_photo
							$item_photo ='';
						}

						//Check for 'featured' property if there was no 'photo'
						if ($item_photo == ''){
							if ("{$item['properties']['featured']}"){
								$photos =  array();
								foreach ($item['properties']['featured'] as $photo_item) {
									$photos[] = $photo_item;		
								}
								$item_photo = json_encode($photos);
							}
						}

						$item_inreplyto = "{$item['properties']['in-reply-to'][0]['value']}";
						
						if ("{$item['properties']['author'][0]['type'][0]}" === "h-card"){
							// get full author h-card
							$item_author = "{$item['properties']['author'][0]['properties']['name'][0]}";
							$item_avurl = "{$item['properties']['author'][0]['properties']['photo'][0]}";
							$item_author_url = "{$item['properties']['author'][0]['properties']['url'][0]}";
							
						} else {
							// just get the author name
							$item_author = "{$item['properties']['author'][0]}";
							$item_avurl = '';
							$item_author_url = '';
						}
						$item_content = "{$item['properties']['content'][0]['html']}";

						//handle h-entry
						if ("{$item['type'][0]}" == "h-entry"){
				
						}

						//handle h-event
						if ("{$item['type'][0]}" == "h-event"){
							
						}

						//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
						$item_name = clean_the_title($item_name,$item_content,$item_content_plain);

						$hfeed_items [] = array (
							"name"=>$item_name,
							"type"=>$item_type,
							"summary"=>$item_summary,
							"content"=>$item_content,
							"location"=>$item_location,
							"photo"=>$item_photo,
							"published" =>$item_published,
							"updated" =>$item_updated,
							"url"=>$item_url,
							"uid"=>$item_url,
							"author"=>$item_author,
							"syndication"=>$item_syndication,
							"in-reply-to"=>$item_inreplyto,
							"author"=>$item_author,
							"featured"=>$item_featured,
							"siteurl"=>$site_url,
							"author_url"=>$item_author_url,
							"avurl"=>$item_avurl
						);
					}
				}	
			} else {
				yarns_reader_log("Post has already been fetched: " . $hfeed_item_url);
			}
		}
		return $hfeed_items;
	}
}



/*
**
**   Utility functions
**
*/


function findPhotos($html){
	//yarns_reader_log("Finding photos...");
	$dom = new DOMDocument;
	$dom->loadHTML($html);
	foreach ($dom->getElementsByTagName('img') as $node) {
		$returnArray[] = $node->getAttribute('src');
	}
	return $returnArray;
} 





?>