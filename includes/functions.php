<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 */


function test() {

	return Yarns_Microsub_Parser::load_parse_this();
	// Return poll log for review.
	return json_decode( get_option( 'yarns_poll_log' ), true );
}

/**
 * Recursively encode an array.
 * Used to save json array to a single meta property.
 *
 * @param array $data The array to be encoded.
 *
 * @return array
 */
function encode_array( $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $item ) {
			if ( is_array( $item ) ) {
				// encode the child array as well.
				$data[ $key ] = encode_array( $item );
			} else {
				// only encode [html] items.
				if ( strtolower( $key ) === 'html' ) {
					// Some rss feeds have pre-encoded html. Decode those.
					if ( ! strpos( $item, '<' ) && strpos( $item, '&lt;' ) >= 0 ) {
						$item = html_entity_decode( $item );
					}
					// Add slashes to prevent double quotes from mucking things up.
					$data[ $key ] = addslashes( $item );
					$data[ $key ] = $item;
				}
			}
		}
	}
	return $data;
}

/**
 * Checks if a post is newer than the storage period. Returns true if so.
 *
 * @param array $post               The post data.
 * @param int   $storage_period     The time to store posts (in days).
 *
 * @return bool
 */
function yarns_date_compare( $post, $storage_period ) {
	if ( isset( $post['date'] ) && strtotime( $post['date'] ) ) {
		if ( strtotime( $post['date'] ) < strtotime( '-' . $storage_period . ' days' ) ) {
			return true;
		}
	}
}

/**
 * Converts one time format to another.
 *
 * @param string $format Format to Output To. It defaults to ISO8601
 * @param string $time Time String. Defaults to now.
 * @return false|string Properly formatted string or false if unable.
 */
function yarns_convert_date( $format = DATE_W3C, $time = 'now' ) {
	$datetime = new DateTime( $time, wp_timezone() );
	if ( ! $datetime ) {
		return false;
	}
	return $datetime->format( $format );
}


function yarns_get( $array, $key, $default = array(), $index = false ) {
	$return = $default;
	if ( is_array( $array ) && isset( $array[ $key ] ) ) {
		$return = $array[ $key ];
	}
	if ( $index && wp_is_numeric_array( $return ) && ! empty( $return ) ) {
		$return = $return[0];
	}
	return $return;
}
