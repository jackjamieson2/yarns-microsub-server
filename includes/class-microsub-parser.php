<?php
/**
 * Microsub actions 
 *
 * @author Jack Jamieson
 *
 */
Class parser {

	public static function parse_hfeed($url){
		error_log("Parsing h-feed");
		
		
		
		$mf = static::locate_hfeed($url);
		//return $mf;

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
		//Get permalinks for each item
		$hfeed_items = array();
		foreach ($mf[$mf_key] as $item) {
			if ("{$item['type'][0]}" == 'h-entry' ||
				"{$item['type'][0]}" == 'h-event' )
			{
				if ("{$item['properties']['url'][0]}"){
					$hfeed_item_urls[] = "{$item['properties']['url'][0]}";
				}
				
			}
		}
		
		error_log("Parsing individual h-feed items");
		$hfeed_items = array();
		foreach ($hfeed_item_urls as $page){
			$hfeed_items[] = static::parse_hfeed_item($page);
		}
		$result = ['items'=> $hfeed_items];
		error_log("Result: \n" . json_encode($result));

		return [
      		'items' => $hfeed_items
    	];

		//return $hfeed_items;
	}

		/*
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

			

	}
	*/


	public static function parse_hfeed_item($url){
		$mf = Mf2\fetch($url);
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

				if(array_key_exists('url', $item['properties'])) {
					$return_item['url'] = $item['properties']['url'];
				}

				if(array_key_exists('author', $item['properties'])) {
					$return_item['author'] = $item['properties']['author'];
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
		return "fail";

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
** Fetch an H-FEED and return its content
*/
function yarns_reader_fetch_hfeed($url,$feedtype) {
	yarns_reader_log('fetching h-feed at '. $url);
	//Parse microformats at the feed-url
	$mf = Mf2\fetch($url);
	//Identify the h-feed within the parsed MF2
	$hfeed = "";
	$hfeed_path = "children"; // (default) in most cases, items within the h-feed will be 'children' 
	

	
}

}