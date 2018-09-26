<?php
/**
 * Parse This class.
 * Derived from the Press This Class with Enhancements.
 *
 * @package WordPress
 * @subpackage Press_This
 * @since x.x.x
 */

/**
 * Parse This class.
 *
 * @since x.x.x
 */
class Yarns_Microsub_Parse_This {
	private $images = array();

	private $embeds = array();

	private $links = array();

	private $meta = array();

	private $urls = array();

	private $video = array();

	private $audio = array();

	private $url = '';

	private $domain = '';

	private $content = '';

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 * @access public
	 */
	public function __construct( $url = null ) {
		if ( $this->is_url( $url ) ) {
			$this->url = $url;
			$return    = $this->fetch_source_html( $url );
			if ( is_wp_error( $return ) ) {
				return $return;
			}
		}
	}

	/**
	 * Get the source's images and save them locally, for posterity, unless we can't.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param int    $post_id Post ID.
	 * @return string New markup with old image URLs replaced with the local attachment ones if swapped.
	 */
	public function side_load_images( $post_id ) {
		$this->content = wp_unslash( $this->content );

		if ( preg_match_all( '/<img [^>]+>/', $content, $matches ) && current_user_can( 'upload_files' ) ) {
			foreach ( (array) $matches[0] as $image ) {
				// This is inserted from our JS so HTML attributes should always be in double quotes.
				if ( ! preg_match( '/src="([^"]+)"/', $image, $url_matches ) ) {
					continue;
				}

				$image_src = $url_matches[1];

				// Don't try to sideload a file without a file extension, leads to WP upload error.
				if ( ! preg_match( '/[^\?]+\.(?:jpe?g|jpe|gif|png)(?:\?|$)/i', $image_src ) ) {
					continue;
				}

				// Sideload image, which gives us a new image src.
				$new_src = media_sideload_image( $image_src, $post_id, null, 'src' );

				if ( ! is_wp_error( $new_src ) ) {
					// Replace the POSTED content <img> with correct uploaded ones.
					// Need to do it in two steps so we don't replace links to the original image if any.
					$new_image     = str_replace( $image_src, $new_src, $image );
					$this->content = str_replace( $image, $new_image, $content );
				}
			}
		}

		// Expected slashed
		return wp_slash( $this->content );
	}

	/**
	 * Sets the source's HTML externally.
	 *
	 * @since x.x.x
	 * @access public
	 *
	 * @param string $source_content HTML source content.
	 * @param string $url Source URL
	 */
	public function set_source( $source_content, $url ) {
		if ( is_string( $source_content ) ) {
			$this->content = $source_content;
		}
		if ( $this->is_url( $url ) ) {
			$this->url    = $url;
			$this->domain = wp_parse_url( $url, PHP_URL_HOST );
		}
		$this->source_data_parse();
	}

	/**
	 * Downloads the source's HTML via server-side call for the given URL.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $url URL to scan.
	 * @return WP_Error|boolean WP_Error if invalid and true if successful
	 */
	public function fetch_source_html( $url ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid-url', __( 'A valid URL was not provided.', 'indieweb-post-kinds' ) );
		}

		$args          = array(
			'timeout'             => 30,
			'limit_response_size' => 1048576,
			'redirection'         => 5,
			// Use an explicit user-agent for Press This
			'user-agent'          => 'Parse This (WP/' . get_bloginfo( 'version' ) . '); ' . get_bloginfo( 'url' ),
		);
		$remote_url    = wp_safe_remote_head( $url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $remote_url ) ) {
			return $remote_url;
		}
		switch ( $response_code ) {
			case 200:
				break;
			default:
				return new WP_Error( 'source_error', wp_remote_retrieve_response_message( $response ), array( 'status' => $response_code ) );
		}

		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return new WP_Error( 'content-type', 'Content Type is Media' );
		}

		$remote_url    = wp_safe_remote_get( $url, $args );
		$this->domain  = wp_parse_url( $url, PHP_URL_HOST );
		$this->content = wp_remote_retrieve_body( $remote_url );
		$this->source_data_parse();
		return true;
	}

	/**
	 * Is string a URL.
	 *
	 * @param array $url
	 * @return bool
	 */
	public function is_url( $url ) {
		return preg_match( '/^([!#$&-;=?-\[\]_a-z~]|%[0-9a-fA-F]{2})+$/', $url );
	}

	/**
	 * Utility method to limit an array to 100 values.
	 * Originally set to 50 but some sites are very detailed in their meta.
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param array $value Array to limit.
	 * @return array Original array if fewer than 100 values, limited array, empty array otherwise.
	 */
	private function _limit_array( $value ) {
		if ( is_array( $value ) ) {
			if ( count( $value ) > 100 ) {
				return array_slice( $value, 0, 100 );
			}

			return $value;
		}

		return array();
	}

	/**
	 * Utility method to limit the length of a given string to 5,000 characters.
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param string $value String to limit.
	 * @return bool|int|string If boolean or integer, that value. If a string, the original value
	 *                         if fewer than 5,000 characters, a truncated version, otherwise an
	 *                         empty string.
	 */
	private function _limit_string( $value ) {
		$return = '';

		if ( is_numeric( $value ) || is_bool( $value ) ) {
			$return = $value;
		} elseif ( is_string( $value ) ) {
			if ( mb_strlen( $value ) > 5000 ) {
				$return = mb_substr( $value, 0, 5000 );
			} else {
				$return = $value;
			}

			// $return = html_entity_decode( $return, ENT_QUOTES, 'UTF-8' );
			$return = sanitize_text_field( trim( $return ) );
		}

		return $return;
	}

	/**
	 * Utility method to limit a given URL to 2,048 characters.
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param string $url URL to check for length and validity.
	 * @return string Escaped URL if of valid length (< 2048) and makeup. Empty string otherwise.
	 */
	private function _limit_url( $url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}

		// HTTP 1.1 allows 8000 chars but the "de-facto" standard supported in all current browsers is 2048.
		if ( strlen( $url ) > 2048 ) {
			return ''; // Return empty rather than a truncated/invalid URL
		}

		// Does not look like a URL.
		if ( ! $this->is_url( $url ) ) {
			return '';
		}

		// If the URL is root-relative, prepend the protocol and domain name
		if ( $url && $this->domain && preg_match( '%^/[^/]+%', $url ) ) {
			$url = $this->domain . $url;
		}

		// Not absolute or protocol-relative URL.
		if ( ! preg_match( '%^(?:https?:)?//[^/]+%', $url ) ) {
			return '';
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Utility method to limit image source URLs.
	 *
	 * Excluded URLs include share-this type buttons, loaders, spinners, spacers, WordPress interface images,
	 * tiny buttons or thumbs, mathtag.com or quantserve.com images, or the WordPress.com stats gif.
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param string $src Image source URL.
	 * @return string If not matched an excluded URL type, the original URL, empty string otherwise.
	 */
	private function _limit_img( $src ) {
		$src = $this->_limit_url( $src );

		if ( preg_match( '!/ad[sx]?/!i', $src ) ) {
			// Ads
			return '';
		} elseif ( preg_match( '!(/share-?this[^.]+?\.[a-z0-9]{3,4})(\?.*)?$!i', $src ) ) {
			// Share-this type button
			return '';
		} elseif ( preg_match( '!/(spinner|loading|spacer|blank|rss)\.(gif|jpg|png)!i', $src ) ) {
			// Loaders, spinners, spacers
			return '';
		} elseif ( preg_match( '!/([^./]+[-_])?(spinner|loading|spacer|blank)s?([-_][^./]+)?\.[a-z0-9]{3,4}!i', $src ) ) {
			// Fancy loaders, spinners, spacers
			return '';
		} elseif ( preg_match( '!([^./]+[-_])?thumb[^.]*\.(gif|jpg|png)$!i', $src ) ) {
			// Thumbnails, too small, usually irrelevant to context
			return '';
		} elseif ( false !== stripos( $src, '/wp-includes/' ) ) {
			// Classic WordPress interface images
			return '';
		} elseif ( false !== stripos( $src, '/wp-content/themes' ) ) {
			// Anything within a WordPress theme directory
			return '';
		} elseif ( false !== stripos( $src, '/wp-content/plugins' ) ) {
			// Anything within a WordPress plugin directory
			return '';
		} elseif ( preg_match( '![^\d]\d{1,2}x\d+\.(gif|jpg|png)$!i', $src ) ) {
			// Most often tiny buttons/thumbs (< 100px wide)
			return '';
		} elseif ( preg_match( '!/pixel\.(mathtag|quantserve)\.com!i', $src ) ) {
			// See mathtag.com and https://www.quantcast.com/how-we-do-it/iab-standard-measurement/how-we-collect-data/
			return '';
		} elseif ( preg_match( '!/[gb]\.gif(\?.+)?$!i', $src ) ) {
			// WordPress.com stats gif
			return '';
		}
		// Optionally add additional limits
		return apply_filters( 'parse_this_img_filters', $src );
	}

	/**
	 * Limit embed source URLs to specific providers.
	 *
	 * Not all core oEmbed providers are supported. Supported providers include YouTube, Vimeo,
	 * Vine, Daily Motion, SoundCloud, and Twitter.
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param string $src Embed source URL.
	 * @return string If not from a supported provider, an empty string. Otherwise, a reformatted embed URL.
	 */
	private function _limit_embed( $src ) {
		$src = $this->_limit_url( $src );

		if ( empty( $src ) ) {
			return '';
		}

		if ( preg_match( '!//(m|www)\.youtube\.com/(embed|v)/([^?]+)\?.+$!i', $src, $src_matches ) ) {
			// Embedded Youtube videos (www or mobile)
			$src = 'https://www.youtube.com/watch?v=' . $src_matches[3];
		} elseif ( preg_match( '!//player\.vimeo\.com/video/([\d]+)([?/].*)?$!i', $src, $src_matches ) ) {
			// Embedded Vimeo iframe videos
			$src = 'https://vimeo.com/' . (int) $src_matches[1];
		} elseif ( preg_match( '!//vimeo\.com/moogaloop\.swf\?clip_id=([\d]+)$!i', $src, $src_matches ) ) {
			// Embedded Vimeo Flash videos
			$src = 'https://vimeo.com/' . (int) $src_matches[1];
		} elseif ( preg_match( '!//vine\.co/v/([^/]+)/embed!i', $src, $src_matches ) ) {
			// Embedded Vine videos
			$src = 'https://vine.co/v/' . $src_matches[1];
		} elseif ( preg_match( '!//(www\.)?dailymotion\.com/embed/video/([^/?]+)([/?].+)?!i', $src, $src_matches ) ) {
			// Embedded Daily Motion videos
			$src = 'https://www.dailymotion.com/video/' . $src_matches[2];
		} else {
			$oembed = _wp_oembed_get_object();

			if ( ! $oembed->get_provider(
				$src,
				array(
					'discover' => false,
				)
			) ) {
				$src = '';
			}
		}

		return $src;
	}

	/**
	 * Set a meta value or convert to array if multiple values
	 *
	 * @param string  $meta_name   Meta key name.
	 * @param mixed   $meta_value  Meta value.
	 * @param boolean $single Single Value
	 */
	private function _set_meta_entry( $meta_name, $meta_value, $single = true ) {
		// If the key does not exist set it.
		if ( ! isset( $this->meta[ $meta_name ] ) ) {
			$this->meta[ $meta_name ] = $meta_value;
			return;
		}
		if ( $single ) {
			return;
		}
		// If it exists but is not an array turn it into one.
		if ( ! is_array( $this->meta[ $meta_name ] ) ) {
			$this->meta[ $meta_name ] = array( $this->meta[ $meta_name ] );
		}
		// Do not allow duplicates.
		if ( ! in_array( $meta_value, $this->meta[ $meta_name ], true ) ) {
			if ( ! is_array( $meta_value ) ) {
				$this->meta[ $meta_name ][] = $meta_value;
			} else {
				$this->meta[ $meta_name ] = array_unique( array_merge( $this->meta[ $meta_name ], $meta_value ) );
			}
		}
	}


	/**
	 * Process recognized meta data entries from the source and store it in a simplified manner
	 *
	 * @ignore
	 * @since 4.2.0
	 *
	 * @param string $meta_name  Meta key name.
	 * @param mixed  $meta_value Meta value.
	 */
	private function _process_meta_entry( $meta_name, $meta_value ) {
		switch ( $meta_name ) {
			// og:url is the canonical URL so it should be set appropriately
			case 'og:url':
				$this->meta['url'] = $meta_value;
				break;
			// The type of OGP object
			case 'og:type':
				$this->meta['type'] = $meta_value;
				break;
			// The Title
			case 'og:title':
			case 'twitter:title':
				$this->_set_meta_entry( 'title', $meta_value );
				break;
			case 'og:description':
			case 'twitter:description':
				$this->_set_meta_entry( 'description', $meta_value );
				break;
			case 'article:published_time':
			case 'article:published':
			case 'og:article:published':
			case 'og:article:published_time':
			case 'date':
				$datetime = new DateTime( $meta_value );
				if ( $datetime ) {
					$meta_value = $datetime->format( DATE_W3C );
				}
				$this->_set_meta_entry( 'published', $meta_value );
				break;
			case 'article:modified_time':
			case 'article:modified':
			case 'og:article:modified':
			case 'og:article:modified_time':
				$datetime = new DateTime( $meta_value );
				if ( $datetime ) {
					$meta_value = $datetime->format( DATE_W3C );
				}
				$this->_set_meta_entry( 'modified', $meta_value );
				break;
			case 'article:expiration_time':
				$this->_set_meta_entry( 'expiration', $meta_value );
				break;
			case 'og:author':
			case 'book:author':
			case 'article:author':
			case 'good_reads:author':
			case 'author':
				$this->_set_meta_entry( 'author', $meta_value, false );
				break;

			// The locale it is marked up in
			case 'og:locale':
				$this->_set_meta_entry( 'locale', $meta_value );
				break;
			// Alternate Locales
			case 'og:locale:alternate':
				$this->_set_meta_entry( 'locale:alternate', $meta_value, false );
				break;
			case 'og:site_name':
				$this->_set_meta_entry( 'site_name', $meta_value );
				break;
			case 'music:duration':
			case 'video:duration':
				$this->_set_meta_entry( 'duration', $meta_value );
				break;
			case 'music:album:disc':
				$this->_set_meta_entry( 'disc', $meta_value );
				break;
			case 'playfoursquare:location:latitude':
			case 'place:location:latitude':
			case 'og:latitude':
				$this->_set_meta_entry( 'latitude', $meta_value );
				break;
			case 'playfoursquare:location:longitude':
			case 'place:location:longitude':
			case 'og:longitude':
				$this->_set_meta_entry( 'longitude', $meta_value );
				break;
			case 'business:contact_data:street_address':
			case 'og:street_address':
				$this->_set_meta_entry( 'street_address', $meta_value );
				break;
			case 'business:contact_data:locality':
			case 'og:locality':
				$this->_set_meta_entry( 'locality', $meta_value );
				break;
			case 'business:contact_data:postal_code':
			case 'og:postal_code':
				$this->_set_meta_entry( 'postal_code', $meta_value );
				break;
			case 'business:contact_data:country-name':
			case 'og:country-name':
				$this->_set_meta_entry( 'country-name', $meta_value );
				break;
			case 'business:contact_data:region':
			case 'og:region':
				$this->_set_meta_entry( 'region', $meta_value );
				break;
			case 'music:album:track':
				$this->_set_meta_entry( 'track', $meta_value );
				break;
			case 'video:series':
				$this->_set_meta_entry( 'series', $meta_value );
				break;
			case 'book:release_date':
			case 'music:release_date':
				$this->_set_meta_entry( 'release_date', $meta_value );
				break;
			case 'og:video':
			case 'og:video:secure_url':
			case 'og:audio':
				$meta_value = $this->_limit_embed( $meta_value );
				if ( ! isset( $this->embeds ) ) {
					$this->embeds = array();
				}

				if ( ! empty( $meta_value ) && ! in_array( $meta_value, $this->embeds, true ) ) {
					$this->embeds[] = $meta_value;
				}

				return;

			case 'article:section':
				$this->_set_meta_entry( 'section', $meta_value, false );
				break;
			case 'book:isbn':
			case 'good_reads:isbn':
				$this->_set_meta_entry( 'isbn', $meta_value );
				break;
			case 'og:image':
			case 'og:image:secure_url':
			case 'twitter:image0:src':
			case 'twitter:image0':
			case 'twitter:image:src':
			case 'twitter:image':
				// The image will join the list of images but also be declared the featured image
				$this->_set_meta_entry( 'featured', $meta_value );
				$meta_value = $this->_limit_img( $meta_value );
				if ( ! isset( $this->images ) ) {
					$this->images = array();
				}

				if ( ! empty( $meta_value ) && ! in_array( $meta_value, $this->images, true ) ) {
					$this->images[] = $meta_value;
				}
				break;
			case 'keywords':
			case 'news_keywords':
			case 'video:tag':
			case 'article:tag':
			case 'book:tag':
				if ( 1 < substr_count( $meta_value, ',' ) ) {
					$this->_set_meta_entry( 'tag', explode( ',', $meta_value ), false );
				} else {
					$this->_set_meta_entry( 'tag', $meta_value, false );
				}
				break;
		}
	}

	/**
	 * Parses _meta, _images, and _links data from the content.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function source_data_parse() {
		$pre = apply_filters( 'pre_parse_this_data_parse', null, $this );
		if ( $pre ) {
			return $pre;
		}

		if ( empty( $this->content ) ) {
			return false;
		}

		// Strip the content to only the elements being looked at
		$allowed_elements = array(
			'img'    => array(
				'src'    => true,
				'width'  => true,
				'height' => true,
			),
			'iframe' => array(
				'src' => true,
			),
			'link'   => array(
				'rel'      => true,
				'itemprop' => true,
				'href'     => true,
			),
			'title'  => array(),
			'meta'   => array(
				'property' => true,
				'name'     => true,
				'content'  => true,
			),
		);
		$source_content   = wp_kses( $this->content, $allowed_elements );

		// Fetch and gather <meta> data first, so discovered media is offered 1st to user.
		if ( empty( $this->meta ) ) {
			$this->meta = array();
		}
		if ( preg_match_all( '/<meta [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );
			// For Debugging Purposes Generate an Entire Unfiltered Array
			$unfiltered = array();
			foreach ( $items as $value ) {
				if ( preg_match( '/(property|name)=[\'"]([^"]+)[\'"][^>]+content=[\'"]([^"]+)[\'"]/', $value, $new_matches ) ) {
					$meta_name  = $this->_limit_string( $new_matches[2] );
					$meta_value = $this->_limit_string( $new_matches[3] );

					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 200 ) {
						continue;
					}
					$unfiltered[ $meta_name ] = $meta_value;
					$this->_process_meta_entry( $meta_name, $meta_value );
				}
				if ( preg_match( '/content=[\'"]([^"]+)[\'"][^>]+(property|name)=[\'"]([^"]+)[\'"]/', $value, $new_matches ) ) {
					$meta_name  = $this->_limit_string( $new_matches[3] );
					$meta_value = $this->_limit_string( $new_matches[1] );

					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 200 ) {
						continue;
					}
					$unfiltered[ $meta_name ] = $meta_value;
					$this->_process_meta_entry( $meta_name, $meta_value );
				}
			}
			// For debugging pass through unfiltered parameters
			if ( WP_DEBUG ) {
				$this->_set_meta_entry( 'unfiltered', $unfiltered );
			}
		}

		// Fetch and gather <img> data.
		if ( empty( $this->images ) ) {
			$this->images = array();
		}

		if ( preg_match_all( '/<img [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( ( preg_match( '/width=(\'|")(\d+)\\1/i', $value, $new_matches ) && $new_matches[2] < 256 ) ||
					( preg_match( '/height=(\'|")(\d+)\\1/i', $value, $new_matches ) && $new_matches[2] < 128 ) ) {

					continue;
				}

				if ( preg_match( '/src=(\'|")([^\'"]+)\\1/i', $value, $new_matches ) ) {
					$src = $this->_limit_img( $new_matches[2] );
					if ( ! empty( $src ) && ! in_array( $src, $this->images, true ) ) {
						$this->images[] = $src;
					}
				}
			}
		}

		// Fetch and gather <video> data.
		if ( empty( $this->video ) ) {
			$this->video = array();
		}

		if ( preg_match_all( '/<video [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( preg_match( '/src=(\'|")([^\'"]+)\\1/i', $value, $new_matches ) ) {
					$src = $new_matches[2];
					if ( ! empty( $src ) && ! in_array( $src, $this->video, true ) ) {
						$this->video[] = $src;
					}
				}
			}
		}

		// Fetch and gather <audio> data.
		if ( empty( $this->audio ) ) {
			$this->audio = array();
		}

		if ( preg_match_all( '/<audio [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( preg_match( '/src=(\'|")([^\'"]+)\\1/i', $value, $new_matches ) ) {
					$src = $new_matches[2];
					if ( ! empty( $src ) && ! in_array( $src, $this->audio, true ) ) {
						$this->audio[] = $src;
					}
				}
			}
		}

		if ( preg_match_all( '/<figure [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( preg_match( '/data-audio-url=(\'|")([^\'"]+)\\1/i', $value, $new_matches ) ) {
					$src = $new_matches[2];
					if ( ! empty( $src ) && ! in_array( $src, $this->audio, true ) ) {
						$this->audio[] = $src;
					}
				}
			}
		}

		// Fetch and gather <iframe> data.
		if ( empty( $this->embeds ) ) {
			$this->embeds = array();
		}

		if ( preg_match_all( '/<iframe [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( preg_match( '/src=(\'|")([^\'"]+)\\1/', $value, $new_matches ) ) {
					$src = $this->_limit_embed( $new_matches[2] );

					if ( ! empty( $src ) && ! in_array( $src, $this->embeds, true ) ) {
						$this->embeds[] = $src;
					}
				}
			}
		}

		// Fetch and gather <link> data.
		if ( empty( $this->links ) ) {
			$this->links = array();
		}

		if ( preg_match_all( '/<link [^>]+>/', $source_content, $matches ) ) {
			$items = $this->_limit_array( $matches[0] );

			foreach ( $items as $value ) {
				if ( preg_match( '/rel=["\'](canonical|shortlink|icon)["\']/i', $value, $matches_rel ) && preg_match( '/href=[\'"]([^\'" ]+)[\'"]/i', $value, $matches_url ) ) {
					$rel = $matches_rel[1];
					$url = $this->_limit_url( $matches_url[1] );

					if ( ! empty( $url ) && empty( $this->links[ $rel ] ) ) {
						$this->links[ $rel ] = $url;
					}
				}
			}
		}

		// If Title is Not Set Use Title Tag
		if ( ! isset( $this->meta['title'] ) ) {
			preg_match( '/<title>(.+)<\/title>/i', $this->content, $match );
			//$this->meta['title'] = trim( $match[1] );
		}
		// If Site Name is not set use domain name less www
		if ( ! isset( $this->meta['site_name'] ) ) {
			$this->meta['site_name'] = preg_replace( '/^www\./', '', $this->domain );
		}

		$allowed_elements = array(
			'a' => array(
				'href' => true,
			),
		);
		// Only hunt for links that are actual links
		$source_content = wp_kses( $this->content, $allowed_elements );
		$this->urls     = wp_extract_urls( $source_content );

		$video_extensions = array(
			'mp4',
			'mkv',
			'webm',
			'ogv',
			'avi',
			'm4v',
			'mpg',
		);
		$audio_extensions = array(
			'mp3',
			'ogg',
			'm4a',
			'm4b',
			'flac',
			'aac',
		);

		foreach ( $this->urls as $url ) {
			$extension = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
			if ( in_array( $extension, $audio_extensions, true ) ) {
				$this->audio[] = $url;
			}
			if ( in_array( $extension, $video_extensions, true ) ) {
				$this->video[] = $url;
			}
		}

	}

		/**
		 * Verifies whether the provided URL is in the content..
		 *
		 * @since x.x.x
		 * @access public
		 *
		 * @param string $url URL.
		 */
	public function verify_url_in_content( $url ) {
		return in_array( $url, $this->urls, true );
	}

	public function get_meta( $key = null ) {
		if ( ! $key ) {
			return $this->meta;
		} else {
			if ( isset( $this->meta[ $key ] ) ) {
				return $this->meta[ $key ];
			}
		}
		return null;
	}

	/**
	 * Maps the return from Parse This to Microformat Properties
	 * Partly this is to have some sort of simple structure for the data to be used
	 *
	 * @param string $content HTML marked up content
	 */
	public function meta_to_microformats() {
		$data            = array();
		$data['name']    = $this->get_meta( 'title' );
		$data['summary'] = $this->get_meta( 'description' );
		$data['author']  = $this->get_meta( 'author' );
		if ( isset( $data['author'] ) && is_array( $data['author'] ) ) {
			$author = array(
				'url'  => array(),
				'name' => array(),
			);
			foreach ( $data['author'] as $a ) {
				if ( self::is_url( $a ) ) {
					$author['url'][] = $a;
				} else {
					$author['name'][] = $a;
				}
			}
			$data['author'] = array_filter( $author );
		}
		$data['featured'] = $this->get_meta( 'featured' );
		//$data['publication'] = $this->get_meta( 'site_name' );
		$data['published'] = $this->get_meta( 'published' ) ?: $this->get_meta( 'release_date' );
		$data['updated']   = $this->get_meta( 'modified' );
		$tags              = $this->get_meta( 'tag' );
		if ( is_array( $tags ) ) {
			$data['category'] = array_values( $tags );
		}
		// Extended Parameters
		$data['duration']  = $this->get_meta( 'duration' );
		$data['longitude'] = $this->get_meta( 'longitude' );
		$data['latitude']  = $this->get_meta( 'latitude' );
		$type              = $this->get_meta( 'type' );
		$data['photo']     = $this->images;
		$data['media']     = $this->embeds;
		$data['video']     = $this->video;
		$data['audio']     = $this->audio;
		$data['url']       = $this->get_meta( 'url' );
		if ( ! $data['url'] ) {
			$data['url'] = $this->url;
		}
		if ( WP_DEBUG ) {
			//$data['raw']         = $this->get_meta();
			//$data['raw']['urls'] = $this->urls;
		}
		return array_filter( $data );
	}

	public function get_all() {
		return array(
			'url'     => array_filter( $this->url ),
			'images'  => array_filter( $this->images ),
			'embeds'  => array_filter( $this->embeds ),
			'meta'    => array_filter( $this->meta ),
			'links'   => array_filter( $this->links ),
			'urls'    => array_filter( $this->urls ),
			'video'   => array_filter( $this->video ),
			'audio'   => array_filter( $this->audio ),
			'content' => $this->content,
		);
	}

}
