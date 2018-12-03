<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 */


function test() {

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
