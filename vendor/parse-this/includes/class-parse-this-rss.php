<?php
/**
 * Helpers for Turning RSS/Atom into JF2
**/

class Parse_This_RSS {

	/*
	 * Parse RSS/Atom into JF2
	 *
	 * @param SimplePie $feed
	 * @return JF2 array
	 */
	public static function parse( $feed, $url ) {
		$items     = array();
		$rss_items = $feed->get_items();
		foreach ( $rss_items as $item ) {
			$items[] = self::get_item( $item );
		}
		return array_filter(
			array(
				'type'       => 'feed',
				'_feed_type' => 'rss',
				'summary'    => $feed->get_description(),
				'author'     => self::get_author( $feed->get_author() ),
				'name'       => $feed->get_title(),
				'url'        => $feed->get_permalink(),
				'photo'      => $feed->get_image_url(),
				'items'      => $items,
			)
		);
	}

	/*
	 * Takes a SimplePie_Author object and Turns it into a JF2 Author property
	 * @param SimplePie_Author $author
	 * @return JF2 array
	 */
	public static function get_author( $author ) {
		if ( ! $author ) {
			return array();
		}
		return array_filter(
			array(
				'name'  => $author->get_name(),
				'url'   => $author->get_link(),
				'email' => $author->get_email(),
			)
		);
	}

	/*
	 * Takes a SimplePie_Item object and Turns it into a JF2 entry
	 * @param SimplePie_Item $item
	 * @return JF2
	 */
	public static function get_item( $item ) {
		return array_filter(
			array(
				'type'      => 'entry',
				'name'      => htmlspecialchars_decode( $item->get_title(), ENT_QUOTES ),
				'author'    => self::get_author( $item->get_author() ),
				'summary'   => $item->get_description( true ),
				'content'   => array_filter(
					array(
						'html' => htmlspecialchars( $item->get_content( true ) ),
						'text' => wp_strip_all_tags( $item->get_content( true ) ),
					)
				),
				'published' => $item->get_date( DATE_W3C ),
				'updated'   => $item->get_updated_date( DATE_W3C ),
				'url'       => $item->get_permalink(),
				'location'  => self::get_location( $item ),
				'category'  => self::get_categories( $item->get_categories() ),
			)
		);
	}

	private static function get_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return array();
		}
		$return = array();
		foreach ( $categories as $category ) {
			$return[] = $category->get_label();
		}
		return $return;
	}

	private static function get_location_name( $item ) {
		$return = $item->get_item_tags( SIMPLEPIE_NAMESPACE_GEORSS, 'featureName' );
		if ( $return ) {
			return $return[0]['data'];
		}
	}


	public static function get_location( $item ) {
		return array_filter(
			array(
				'latitude'  => $item->get_latitude(),
				'longitude' => $item->get_longitude(),
				'name'      => self::get_location_name( $item ),
			)
		);
	}


}
