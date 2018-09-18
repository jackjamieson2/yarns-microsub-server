<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 *
 */


function test() {

}

function encode_array( $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $item ) {
			if ( is_array( $item ) ) {
				//encode the child array as well
				$data[ $key ] = encode_array( $item );
			} else {
				// only encode [html] items
				if ( strtolower( $key ) == 'html' ) {
					// Some rss feeds have pre-encoded html. Decode those.
					if ( ! strpos( $item, '<' ) && strpos( $item, '&lt;' ) >= 0 ) {
						$item = html_entity_decode( $item );
					}
					// Add slashes to prevent double quotes from mucking things up
					$data[ $key ] = addslashes( $item );

					$data[ $key ] = $item;

					// $data[$key] = htmlspecialchars($item, ENT_COMPAT, "UTF-8");
				}
			}
		}
	}
	return $data;
}


function decode_array( $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $item ) {
			if ( is_array( $item ) ) {
				//encode the child array as well
				$data[ $key ] = decode_array( $item );
			} else {
				if ( strtolower( $key ) == 'html' ) {
					$data[ $key ] = stripcslashes( $item );
					//$item = "test";
					//$item = html_entity_decode($item,ENT_COMPAT);
					//$data[$key] = htmlspecialchars_decode($item, ENT_COMPAT);

				}
				//encode the item
				//$data[$key] = htmlspecialchars_decode($item);

				// * Some feeds (e.g. thestar.com) have html entities in the description, in which case they could be
				// decoded.  But others (e.g.  cbc.ca) do not.  Think about what to do here.
			}
		}
	}
	return $data;
}
