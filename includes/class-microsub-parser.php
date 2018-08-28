<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */
class Yarns_Microsub_Parser {


    public static function clean_post($data){
        // dedupe name with summary
        if ( isset( $data['name'] ) ) {
            if (isset($data['summary'])) {
                if (false !== stripos($data['summary'], $data['name'])) {
                    unset($data['name']);
                }
            }
        }
        // dedupe name with content['text']
        if ( isset( $data['name'] ) ) {
            if ( isset( $data['content']['text'] ) ) {
                if ( false !== stripos( $data['content']['text'], $data['name'] ) ) {
                    unset( $data['name'] );
                }
            }
        }


        // Attempt to set a featured image
        if ( ! isset( $data['featured'] ) ) {
            if ( isset( $data['photo'] ) && is_array( $data['photo'] ) && 1 === count( $data['photo'] ) ) {
                $data['featured'] = $data['photo'];
                unset( $data['photo'] );
            }
        }

        // Convert special characters to html entities in content['html']
        if (isset ($data['content']['html'])){
            //$data['content']['html'] = htmlspecialchars( $data['content']['html']);
        }

        $data = encode_array(array_filter($data));
        return $data;
    }

	/**
	 * Parses marked up HTML.
	 *
	 * @param string $content HTML marked up content.
	 */
	public static function mergeparse( $content, $url ) {
		// For debugging - get time of the script

		if ( empty( $content ) || empty( $url ) ) {
			return array();
		}


		//$mf2data  = Parse_MF2_yarns::mf2parse( $content, $url );
		//return $mf2data;

		//$parsethis = new Yarns_Microsub_Parse_This();
		//$parsethis->set_source( $content, $url );
		//$metadata = $parsethis->meta_to_microformats();
		$mf2data  = Parse_MF2_yarns::mf2parse( $content, $url );
		//$data     = array_merge( $metadata, $mf2data );
		//$data     = array_filter( $data );
        $data = $mf2data;
		if ( ! isset( $data['summary'] ) && isset( $data['content'] ) ) {
			$data['summary'] = substr( $data['content']['text'], 0, 300 );
			if ( 300 < strlen( $data['content']['text'] ) ) {
				$data['summary'] .= '...';
			}
		}
		if ( isset( $data['name'] ) ) {
			if ( isset( $data['summary'] ) ) {
				if ( false !== stripos( $data['summary'], $data['name'] ) ) {
					unset( $data['name'] );
				}
			}
		}
		// Attempt to set a featured image
		if ( ! isset( $data['featured'] ) ) {
			if ( isset( $data['photo'] ) && is_array( $data['photo'] ) && 1 === count( $data['photo'] ) ) {
				$data['featured'] = $data['photo'];
				unset( $data['photo'] );
			}
		}

		// Convert special characters to html entities in content['html']
        if (isset ($data['content']['html'])){
		    $data['content']['html'] = htmlspecialchars( $data['content']['html']);
        }


		//$time_end = microtime(true);
		//$execution_time = ($time_end - $time_start);
		//error_log("Execution time in seconds: " . $execution_time);
		return $data;


	}



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
		$url = static::validate_url($query);
		$results = [];
		$content = file_get_contents($url); //get the html returned from the following url
		$dom = new DOMDocument();
		libxml_use_internal_errors(TRUE); //disable libxml errors

		if(!empty($content)){ //if any html is actually returned
			$dom->loadHTML($content);
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
                if (static::locate_hfeed($content, $url)){
                    $feed['url'] = $url;
                    $feed['_type'] = "h-feed";
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

	/*	Preview

	action=preview

	POST

	    action=preview
	    url={url}*/

	    /*The response includes the list of items in the feed if available, in the same format as returned by the #Timelines API call. */


	public static function preview($url){
	    return static::parse_feed($url, 5);
	    return Yarns_Microsub_Aggregator::poll_site($url,'_preview');
	}

	public static function parse_feed($url, $count = 20){

        $content = file_get_contents($url);
        // only proceed if content could be found
        if (!$content){return;}

        // Try to parse h-feed
        $feed = static::parse_hfeed($content, $url, $count);
        if ($feed){
            return $feed;
        }

        // If there is no h-feed, Try to parse rss
        $feed = static::parse_rss($content,$url, $count);
        if ($feed){
            return $feed;
        }

        // Failed, so return nothing
	    return;
    }

    public static function parse_rss($content,$url){
        include_once( ABSPATH . WPINC . '/feed.php' );
        // Get a SimplePie feed object from the specified feed source.
        $feed = fetch_feed( $url );


        if (is_wp_error( $feed ) ){
            return;
        } else {

            $rss_items = [];


            // Parse the feed
            $feed->enable_cache(false);
            $feed->strip_htmltags(false);
            $items = $feed->get_items();



            foreach ($items as $item){

                $post['type'] = 'entry';
                $post['name'] = htmlspecialchars_decode($item->get_title(), ENT_QUOTES);


                $post['author']['name'] = $feed->get_title();

                $post['author']['photo'] = $feed->get_image_url();
                $post['author']['url'] = $feed->get_link();

                // If the individual post has an author with different info from the feed, use that info
                $post_author = $item->get_author();
                if ($post_author->get_name()){
                    if ($post_author->get_name() != $post['author']['name']){
                        $post['author']['name'] = $post_author->get_name() . " | " . $post['author']['name'];
                    }
                }



                if ($post_author->get_link()){
                    if ($post_author->get_link() != $post['author']['url']){
                        $post['author']['url'] = $post_author->get_link();
                    }
                }


                $post['summary'] = strip_tags($item->get_description());


                $post['content']['html'] = htmlspecialchars($item->get_content());
                $post['content']['text'] = strip_tags($item->get_content());



                //$post['content'] = html_entity_decode ($item->get_content());
                $post['published']=gmdate("Y-m-d H:i:sO", $item->get_date('U'));



                $post['url'] = $item->get_permalink();

                $rss_items[] = encode_array($post); // encode_array encodes html characters for display
            }





            return [
                'items' => $rss_items,
                '_feed_type' =>'rss'
            ];

        }

    }

	/*

	$url -> the url from which to retrieve a feed
	$count -> the number of posts to retrieve

	*/
	public static function parse_hfeed($content, $url, $count=5){

		$mf = static::locate_hfeed($content, $url);
		//If no h-feed was found, return
        if(!$mf){return;}

		//return $mf;

		// Find the key to use
		if (!$mf){
			return ([
          		'error' => 'not_found',
          		'error_description' => 'No h-feed was found'
        	]);
		} else {
			// Most h-feeds use item, but some use children (e.g. tantek.com)
			if(array_key_exists('items', $mf)) {
				$mf_key = 'items';
			} else  if(array_key_exists('children', $mf)) {
				$mf_key = 'children';
			} else {
				// If the feed has neither items or chidlren, something has gone wrong
				return "No feed items";
			}
			 
			//return $mf_key;
		}
		//error_log("hfeed item key = " . $mf_key );

        // Get feed author
        // (For posts with no author, use the feed author instead)
        $feed_author = static::get_feed_author($content, $url);

		//Get permalinks and contnet for each item
		$hfeed_items = array();

        foreach ($mf[$mf_key] as $key=>$item) {
            //error_log ("checkpoint 1.".$key);
            if ($key >= $count){break;} // Only get up to the specific count of items
            if ("{$item['type'][0]}" == 'h-entry' ||
                "{$item['type'][0]}" == 'h-event' )
            {
                $the_item = Parse_MF2_yarns::parse_hentry($item,$mf);
                if (is_array($the_item)){
                    /* Merge feed author and post author if:
                     *  (1) feed_author was found AND
                     *  (2) there is no post author OR (3) post author has same url as feed author
                     */
                    if ($feed_author){
                        if (!isset($the_item['author'])){

                            //if(!array_key_exists('author', $the_item)) {
                            $the_item['author'] = $feed_author;
                        } else if (array_key_exists('url',$the_item['author']) && array_key_exists('url', $feed_author)){
                            if ($the_item['author']['url'] == $feed_author['url']){
                                $the_item['author'] = array_merge($feed_author, $the_item['author']);
                            }
                        }

                    }

                    $the_item = static::clean_post($the_item);
                    $hfeed_items[] = $the_item;
                }
            }
        }

        //$result = ['items'=> $hfeed_items];
		return [
      		'items' => $hfeed_items,
            '_feed_type' =>'h-feed',
    	];

	}

	private static function get_feed_author($content, $url){
        $mf = Mf2\parse($content, $url);

        if ( ! is_array( $mf ) ) {
            return array();
        }

        $count = count( $mf['items'] );
        if ( 0 === $count ) {
            return array();
        }
        foreach ($mf['items'] as $item) {
            // Check if the item is an h-card
            if (Parse_MF2_yarns::is_hcard($item)){
                return Parse_MF2_yarns::parse_hcard( $item, $mf, $url );
            }
            // Check if the item is an h-feed, in which case look for an author property
            if (in_array('h-feed', $item['type'], true)) {
                if (isset($item['properties'])){
                    if (isset($item['properties']['author'])){
                        foreach($item['properties']['author'] as $author){
                            if (Parse_MF2_yarns::is_hcard($author)){
                                return Parse_MF2_yarns::parse_hcard( $author, $mf, $url );
                            } else {
                                return $author;
                            }
                        }
                    }
                }
                //return Parse_MF2_yarns::parse_hcard( $item, $mf, $url );
            }
        }
    }



	/* For now deprecated in favour of mergeparse() */
	public static function parse_hfeed_item($content,$url){
		//$mf = Mf2\fetch($url);
		$mf = Mf2\parse($content,$url);
		foreach ($mf['items'] as $item){
			if ("{$item['type'][0]}" == 'h-entry' ||
				"{$item['type'][0]}" == 'h-event' )
			{
				$return_item = array();
				//$return_item = $item['properties'];
				$return_item['type'] = $item['type'];

				if(array_key_exists('name', $item['properties'])) {
					$return_item['name'] = $item['properties']['name'];
				}

				if(array_key_exists('published', $item['properties'])) {
					$return_item['published'] = $item['properties']['published'];
				}

				if(array_key_exists('updated', $item['properties'])) {
					$return_item['updated'] = $item['properties']['updated'];
				}

				if(array_key_exists('url', $item['properties'])) {
					$return_item['url'] = $item['properties']['url'];
				}



				if(array_key_exists('content', $item['properties'])) {
					$return_item['content'] = $item['properties']['content'];
				}

				if(array_key_exists('summary', $item['properties'])) {
					$return_item['summary'] = $item['properties']['summary'];
				}

				if(array_key_exists('photo', $item['properties'])) {
					$return_item['photo'] = $item['properties']['photo'];
				}

				return $return_item;
			}
		}
	}


	// Find the root feed
	public static function locate_hfeed($content, $url){
		$mf = Mf2\parse($content, $url);

		if (!$mf){
		    // If no microformats could be parsed, there is no h-feed
		    return;
        }

		foreach ($mf['items'] as $mf_item) {
			if(in_array('h-feed', $mf_item['type'])) {
				return $mf_item;
			} 
		}

		foreach ($mf['items'] as $mf_item) {
			if(array_key_exists('children', $mf_item)) {
				foreach($mf_item['children'] as $child){
				//If h-feed has not been found, check for a child-level h-feed
					if ("{$child['type'][0]}"=="h-feed"){
						//return 2;
						return $child;	
					}
				}
			}
		}
		//If no h-feed was found, check for h-entries. If h-entry is found, then consider its parent the h-feed
		foreach ($mf['items'] as $mf_item) {
			if ("{$mf_item['type'][0]}"=="h-entry"){
				//return 3;
				return $mf;		
				//$hfeed_path	="items";
			} else {
				if(array_key_exists('children', $mf_item)) {
					//If h-entries have not been found, check for a child-level h-entry
					foreach($mf_item['children'] as $child){
						if ("{$child['type'][0]}"=="h-entry"){
							//return 4;
							return $mf_item;		
						}
					}
				}	
			}
		}
		return;
	}

	public static function find_hfeed_in_page($url){
		$mf2 = Mf2\fetch($url);
		
		// If there was more than one h-entry on the page, treat the whole page as a feed
		if(count($mf2['items']) > 1) {
			if(count(array_filter($mf2['items'], function($item){
		    	return in_array('h-entry', $item['type']);
		  		})) > 1) {
		       	#Recognized $url as an h-feed because there are more than one object on the page".
		       	// Return the whole page as an hfeed
		       	return $mf2;
		  	}
		}

		// If the first item is an h-feed, parse as a feed
		$first = $mf2['items'][0];
		if(in_array('h-feed', $first['type'])) {
		  #Parse::debug("mf2:3: Recognized $url as an h-feed because the first item is an h-feed");
			return $first;
		}

		// Fallback case, but hopefully we have found something before this point
	    foreach($mf2['items'] as $item) {
	      	// Otherwise check for a recognized h-* object
	      	if(in_array('h-entry', $item['type']) || in_array('h-cite', $item['type']) || in_array('h-feed', $item['type'])) {
		        #Parse::debug("mf2:6: $url is falling back to the first h-entry on the page");
			  	return $item;
			  	//break;
	      	} else {
	      	foreach ($item['children'] as $child){
				if(in_array('h-entry', $child['type']) || in_array('h-cite', $child['type']) || in_array('h-feed', $child['type'])) {
					#Parse::debug("mf2:6: $url is falling back to the first h-entry on the page");
					return $child;
					//$hfeed_exists = True;
					break;
				}
	      	}
	      }
	    }
	}


/*
* UTILITY FUNCTIONS
*
*/

	public static function validate_url($possible_url){
		//If it is already a valid URL, return as-is
		if(static::is_url($possible_url)){
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

	public static function is_url($query){
		if(filter_var($query, FILTER_VALIDATE_URL)){
			return true; 
		} else {
			return false;
		}
	}


	public static function isRSS($feedtype){
		$rssTypes = array ('application/rss+xml','application/atom+xml','application/rdf+xml','application/xml','text/xml','text/xml','text/rss+xml','text/atom+xml');
	    if (in_array($feedtype,$rssTypes)){
	    	return True;
	    }
	}






}