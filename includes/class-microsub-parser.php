<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */
class parser {



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
		/* TESTING*/
		//$mf2data  = Parse_MF2::mf2parse( $content, $url );
		//return $mf2data;

		/* END TESTING*/


		//$mf2data  = Parse_MF2::mf2parse( $content, $url );
		//return $mf2data;

		//$parsethis = new Parse_This();
		//$parsethis->set_source( $content, $url );
		//$metadata = $parsethis->meta_to_microformats();
		$mf2data  = Parse_MF2::mf2parse( $content, $url );
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

	/*	Preview

	action=preview

	POST

	    action=preview
	    url={url}*/

	    /*The response includes the list of items in the feed if available, in the same format as returned by the #Timelines API call. */


	public static function preview($url){

		return parser::parse_hfeed($url, $preview=true);
		// Check if this is an h-feed or other

		//if h-feed:
			// Run h-feed parser
		// if rss:
			// Run simplepie parser
	}




	/*

	$url -> the url from which to retrieve a feed
	$count -> the number of posts to retrieve
	$return_type -> The type of results to return_type
		"urls" => a list of urls
		"preview" => return basic metadata
		"full" = run mergeparse on each item
	$preview -> whether to treat the feed as a live preview or a full feed
		preview = true -> prioritize speed and just fetch everything from the main url
		preview = true -> prioritize completion and fetch each post from its own permalink
	*/
	public static function parse_hfeed($url, $preview=false, $count=5){

		error_log("Parsing h-feed");
		error_log("Preview == " . $preview);

		$time_start = microtime(true); 

	
		
		$mf = static::locate_hfeed($url);
		error_log("located h-feed");

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

		

		//Get permalinks for each item
		$hfeed_items = array();



		if ($preview ==true){
			//error_log ("checkpoint 1");
			foreach ($mf[$mf_key] as $key=>$item) {
				//error_log ("checkpoint 1.".$key);
				if ($key >= $count){break;} // Only get up to the specific count of items
				if ("{$item['type'][0]}" == 'h-entry' ||
					"{$item['type'][0]}" == 'h-event' ) 
				{
					//$the_item = Parse_MF2::mf2parse($item,$mf);
					$the_item = Parse_MF2::parse_hentry($item,$mf);
					$hfeed_items[] = $the_item;
				}

			}
			//error_log ("checkpoint 2");


		} else {
			// if $preview == false, get the full posts from original permalinks
			foreach ($mf[$mf_key] as $key=>$item) {
				//if ($key >= $count){break;} // Only get up to the specific count of items
				if ("{$item['type'][0]}" == 'h-entry' ||
					"{$item['type'][0]}" == 'h-event' )
				{
					
					if ("{$item['properties']['url'][0]}"){
						$hfeed_item_urls[] = "{$item['properties']['url'][0]}" . "?" . $key;
					}
				}

			}
			//error_log("Got permalinks");
		
			//error_log("Parsing individual h-feed items");
			$hfeed_items = array();
			foreach ($hfeed_item_urls as $page){

				$content = file_get_contents($page);
				$hfeed_items[] = static::mergeparse($content,$page);
			}
			$result = ['items'=> $hfeed_items];
			//error_log("Result: \n" . json_encode($result));



		}

		$time_end = microtime(true);
		$execution_time = ($time_end - $time_start);
		//error_log("hfeed parsing execution time in seconds: " . $execution_time);

		return [
      		'items' => $hfeed_items,
      		'start' => $time_start,
      		'end' => $time_end,
      		'execution time' => $execution_time
    	];

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

/*

{
    "type": "entry",
    "published": "2017-04-28T11:58:35-07:00",
    "url": "https://aaronparecki.com/2017/04/28/9/p3k-http",
    "author": {
        "type": "card",
        "name": "Aaron Parecki",
        "url": "https://aaronparecki.com/",
        "photo": "https://aaronparecki.com/images/profile.jpg"
    },
    "category": [
        "http",
        "p3k",
        "library",
        "code",
        "indieweb"
    ],
    "photo": [
        "https://aaronparecki.com/2017/04/28/9/photo.png"
    ],
    "content": {
        "text": "Finally packaged up my HTTP functions into a library! https://github.com/aaronpk/p3k-http Previously I had been copy+pasting these around to quite a few projects. Happy to have consolidated these finally!",
        "html": "Finally packaged up my HTTP functions into a library! <a href=\"https://github.com/aaronpk/p3k-http\">https://github.com/aaronpk/p3k-http</a> Previously I had been copy+pasting these around to quite a few projects. Happy to have consolidated these finally!"
    },
    "_id": "abc987",
    "_is_read": true
}
*/


	// Find the root feed
	public static function locate_hfeed($url){
		$mf = Mf2\fetch($url);

		foreach ($mf['items'] as $mf_item) {
			if(in_array('h-feed', $mf_item['type'])) {

			//if ("{$mf_item['type'][0]}"=="h-feed"){
				//return 1;
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
		return false;

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