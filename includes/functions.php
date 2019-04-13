<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 */


function dummysort_by_order($a,$b) {
	return (int)$a['order'] - (int)$b['order'];
}



function test() {

	$post['published'] = "2019-03-31T10:10:37-04:00";

	$my_post['post_date'] = date( 'Y-m-d H:i:s P', strtotime( $post['published'] ) );
	return wp_json_encode($my_post);

	/*
	$poll_log = json_decode( get_site_option( 'yarns_poll_log' ), true );
	return $poll_log;
	*/

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
 * Everything below blatantly stolen from function.php in Micropub plugin
 * @todo link to blob
 */


	function yarns_is_assoc_array( $array ) {
		return is_array( $array ) && array_values( $array ) !== $array;
	}




// blatantly stolen from https://github.com/idno/Known/blob/master/Idno/Pages/File/View.php#L25
if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		$headers = array();
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
				$headers[ str_replace( ' ', '-', strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ] = $value;
			} elseif ( 'CONTENT_TYPE' === $name ) {
				$headers['content-type'] = $value;
			} elseif ( 'CONTENT_LENGTH' === $name ) {
				$headers['content-length'] = $value;
			}
		}
		return $headers;
	}
}

if ( ! function_exists( 'ms_get' ) ) {
	function ms_get( $array, $key, $default = array() ) {
		if ( is_array( $array ) ) {
			return isset( $array[ $key ] ) ? $array[ $key ] : $default;
		}
		return $default;
	}
}


/**
 * Save debug logs
 *
 * @param string $message Message to be written to the log.
 */
function yarns_ms_debug_log( $message ) {
	if ( get_site_option( 'debug_log' ) ) {
		$debug_log = json_decode( get_site_option( 'debug_log' ), true );
	} else {
		$debug_log = [];
	}

	$debug_entry = date( 'Y-m-d H:i:s' ) . '  ' . $message;

	if ( is_array( $debug_log ) && ! empty( $debug_log ) ) {
		array_unshift( $debug_log, $debug_entry ); // Add item to start of array.
		$debug_log = array_slice( $debug_log, 0, 30 ); // Limit log length to 30 entries.
	} else {
		$debug_log[] = $debug_entry;
	}
	update_option( 'debug_log', wp_json_encode( $debug_log ) );
}