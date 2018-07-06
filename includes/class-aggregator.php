<?php
/**
 * Aggregator class
 *
 * @author Jack Jamieson
 *
 */


class Yarns_Microsub_Aggregator {
	public $ID; // Id for the post
	public $post_author; // author for the post - h-card
	public $published; // time published 
	public $updated; // time updated
	public $content; // content
	public $summary; // summary
	public $url; // This serves as a unique identifier 

	

	
	/*
	** Defines the interval for the cron job (60 minutes) 
	*/
	public static function yarns_reader_cron_definer($schedules){
		$schedules['sixtymins'] = array(
			'interval'=> 3600,
			'display'=>  __('Once Every 60 Minutes')
		);
		return $schedules;
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
** Identify and return feeds at a given url 
*/
add_action( 'wp_ajax_yarns_reader_findFeeds', 'yarns_reader_findFeeds' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'yarns_reader_new_subscription' ) );
function yarns_reader_findFeeds($siteurl){
	$siteurl = $_POST['siteurl'];
	yarns_reader_log("Searching for feeds and site title at ". $siteurl);

	$html = file_get_contents($siteurl); //get the html returned from the following url
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


/*
** Unsubscribe from a feed
*/
add_action( 'wp_ajax_yarns_reader_unsubscribe', 'yarns_reader_unsubscribe' );
function yarns_reader_unsubscribe ($feed_id){	
//$wpdb->update($wpdb->prefix . 'yarns_reader_posts', array('liked'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
//$wpdb->delete( 'table', array( 'ID' => 1 ) );
	$feed_id = $_POST['feed_id'];
	global $wpdb;
	$unsubscribe = $wpdb->delete( $wpdb->prefix . "yarns_reader_following", array( 'ID' => $feed_id ) );
	echo $unsubscribe;
	wp_die();
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

//Log changes to the database (adding sites, fetching posts, etc.)
function yarns_reader_log($message){
	global $wpdb;
	$table_name = $wpdb->prefix . 'yarns_reader_log';

	$wpdb->insert( 
		$table_name, 
		array( 
			'date' => current_time( 'mysql' ), 
			'log' => $message, 
		) 
	);

}

function yarns_item_exists($permalink){
	global $wpdb;
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'yarns_reader_posts` 
		ORDER BY  `id`  DESC;'  
	);
	if ( !empty( $items ) ) { 			
		foreach ( $items as $item ) {
			if ($permalink == $item->permalink) {
				yarns_reader_log("MATCH: " . $permalink. " is same as " . $item->permalink);
				return True;
			}
		}
	} 
	return False;
}

/* Remove titles for posts where the title is equal to the content (e.g. notes, asides, microblogs) */
//In many rss feeds and h-feeds, the only indication of whether a title is redunant is that it duplicates 
function clean_the_title($title,$content,$content_plain=''){
	if (compare_cleaned_html($title, $content) >0 || compare_cleaned_html($title, $content_plain)>0){
		$title = "";
	} 
	return $title;
}

function compare_cleaned_html($string1,$string2){
	//$string1 = html_entity_decode($string1); // First convert html entities to text (to ensure consistent comparision)
	$string1 = strip_tags(rtrim($string1,".")); // remove trailing "..."
	$string1 = htmlentities($string1, ENT_QUOTES); // Convert quotation marks to HTML entities
	$string1 = str_replace(array("\r", "\n"), '', $string1); // remove line breaks 
	$string1 = str_replace("&nbsp;", "", $string1); // replace $nbsp; with a space character
	$string1 = strip_tags(trim($string1)); // remove white space on either side
	
	//$string2 = html_entity_decode($string2); // First convert html entities to text (to ensure consistent comparision)
	$string2 = strip_tags(rtrim($string2,".")); // remove trailing "..."
	$string2 = htmlentities($string2, ENT_QUOTES); // Convert quotation marks to HTML entities
	$string2 = str_replace(array("\r", "\n"), '', $string2); // remove line breaks
	$string2 = str_replace("&nbsp;", "", $string2); // replace $nbsp; with a space character
	$string2 = strip_tags(trim($string2)); // remove white space on either side
	
	if ($string1 === $string2) {
		return 2; // 2 == full match
	} else if (strpos($string1,$string2)===0 ) {
		return 1; // 1 = same start	
	} 
	return 0; // 0 == no match
}

/* 
** Returns true is the feed is of type rss 
*/

function isRSS($feedtype){
	$rssTypes = array ('application/rss+xml','application/atom+xml','application/rdf+xml','application/xml','text/xml','text/xml','text/rss+xml','text/atom+xml');
    if (in_array($feedtype,$rssTypes)){
    	return True;
    }
}


/* 
** Returns a datetime formatted with user's preferences (for timezone, date format, & time format)
*/

function User_datetime($datetime){
	$output_log = "Converting datetime... \n";
	$user_datetime_format = get_option('date_format') . " " . get_option('time_format');
	$user_datetime = get_date_from_gmt($datetime, $user_datetime_format);
	return $user_datetime ;
}



/*
**
**   Runs upon deactivating the plugin
**
*/
function yarns_reader_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'yarns_reader_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'yarns_reader_generate_hook' );
	}
	yarns_reader_log("deactivated plugin");
}


/*
**
**   Hooks and filters
**
*/

/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'yarns_reader_install');
register_activation_hook(__FILE__,'yarns_reader_create_tables');
add_filter('cron_schedules','yarns_reader_cron_definer');
add_action( 'yarns_reader_generate_hook', 'yarns_reader_aggregator' );

/* Functions to run upon deactivation */ 
register_deactivation_hook( __FILE__, 'yarns_reader_deactivate' );



/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'yarns_reader_update_db_check' );

/* Hook to display admin notice */ 
/*
add_action( 'admin_notices', 'initial_setup_admin_notice' );
*/
	
?>