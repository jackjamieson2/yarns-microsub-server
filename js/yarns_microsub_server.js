( function ( $ ) {

		/**
		 * Functions to run on document.ready
		 */
		$( document ).ready(
			function () {
				/**
				 * Remove links from items that will use javascript instead.
				 */
				$( '.yarns-feed-unfollow' ).removeAttr( 'href' )
				$( '.yarns-feed-preview' ).removeAttr( 'href' )

				/**
				 * Make channel rows in channel list table sortable.
				 */
				$( '#yarns-channels #the-list' ).sortable(
					{
						/*handle: 'tr',*/
						update: function ( event, ui ) {
							clear_notices() // Clear any errors that are showing from a previous function.

							let channel_order = []
							$( '#yarns-channels #the-list tr .channel_name' ).each(
								function ( index ) {
									channel_order.push( $( this ).find( 'a' ).attr( 'data-uid' ) )
								}
							)

							console.log( channel_order )
							if ( Array.isArray( channel_order ) ) {
								$.ajax(
									{
										url: yarns_microsub_server_ajax.ajax_url,
										type: 'post',
										data: {
											action: 'order_channels',
											channel_order: channel_order,
										},
										success: function ( response ) {
											console.log( response )
										}
									}
								)
							}
						}
					}
				)

				/*$( '.sortable' ).sortable( { handle: '.handle' } )*/
			}
		)

		/**
		 * Click add new channel
		 */
		$( 'body' ).on(
			'click',
			'#yarns-channel-add',
			function () {
				let button = $( this )

				if ( $( '#yarns-new-channel-name' ).is( ':hidden' ) ) {
					// show the channel name input.
					$( '#yarns-new-channel-name' ).val( '' )
					$( '#yarns-new-channel-name' ).show()
					$( this ).text( 'Add' )

				} else {
					// create channel with entered name.
					start_loading( button )
					channel = $( '#yarns-new-channel-name' ).val()
					if ( channel != '' ) {
						$.ajax(
							{
								url: yarns_microsub_server_ajax.ajax_url,
								type: 'post',
								data: {
									action: 'add_channel',
									channel: channel,
								},
								success: function ( response ) {
									done_loading( button )
									response = JSON.parse( response )
									if ( response['status'] === 200 ) {
										window.location.reload()
									} else {
										display_error( button, response['data']['error_description'] )
									}
								}
							}
						)
					} else {
						// Error, the channel must have a name.
					}
				}
			}
		)

		/**
		 * Save Options button. Saves general options.
		 */
		$( 'body' ).on(
			'click',
			'#yarns-save-options',
			function () {
				clear_notices() // Clear any errors that are showing from a previous function.
				let errors = false

				let options = {}
				options['storage_period'] = $( '#yarns-storage-period' ).val()
				options['show_debug'] = $( '#yarns-toggle-debug' ).prop( 'checked' ) == true ? true : false

				// Validate options.
				if ( !options['storage_period'] > 0 ) {
					display_error( $( '#yarns-storage-period' ), 'Storage period must be number greater than 0.' )
					errors = true
				}

				// If there were no errors, save options using ajax.
				if ( errors == false ) {
					button = $( this )
					start_loading( button )
					$.ajax(
						{
							url: yarns_microsub_server_ajax.ajax_url,
							type: 'post',
							data: {
								action: 'save_options',
								options: options,
								test: 'test',
							},
							success: function ( response ) {
								done_loading( button )
								console.log( response )
								window.location.reload()
							}
						}
					)
				}
			}
		)

		/**
		 * Delete a channel
		 */
		$( 'body' ).on(
			'click',
			'#yarns-channel-delete',
			function () {
				if ( confirm( 'Do you want to delete this channel? You will lose all of its subscriptions' ) ) {
					console.log( 'deleting channel checkpoint 1' )
					button = $( this )
					start_loading( button )
					var uid = $( this ).data( 'uid' )
					var channel = $( '#yarns-option-heading' ).text()
					$.ajax(
						{
							url: yarns_microsub_server_ajax.ajax_url,
							type: 'post',
							data: {
								action: 'delete_channel',
								uid: uid,
							},
							success: function ( response ) {
								console.log( 'success' )
								$( '#yarns-channel-options' ).text( 'Deleted channel: ' + channel )

								$( '#yarns-channels' ).html( response )
								done_loading( button )
							}
						}
					)
				}
			}
		)

		/**
		 * Rename a channel (update)
		 */


// Show the channel settings
		$( 'body' ).on(
			'click',
			'#yarns-show-channel-settings',
			function () {
				$( this ).hide()
				$( '#yarns-channel-settings-box' ).show()
			}
		)

// Save the new channel name

		$( 'body' ).on(
			'click',
			'#yarns-channel-update-save',
			function () {
				button = $( this )

				var uid = $( this ).data( 'uid' )
				var old_channel = $( '#yarns-option-heading' ).text()
				var channel = $( '#yarns-channel-update-input' ).val().trim()

				if ( channel != '' && channel != old_channel ) {
					//update to use the new channel name.

					start_loading( button )

					$.ajax(
						{
							url: yarns_microsub_server_ajax.ajax_url,
							type: 'post',
							data: {
								action: 'update_channel',
								uid: uid,
								channel: channel,
							},
							success: function ( response ) {
								$( '#yarns-channels' ).html( response )

								$( '#yarns-option-heading' ).text( channel )
								$( '#yarns-option-breadcrumbs span' ).text( channel )
								$( '#yarns-channel-update' ).css( 'visibility', 'visible' )
								$( '#yarns-option-heading' ).css( 'visibility', 'visible' )
								$( '#yarns-channel-update-options' ).hide()
								done_loading( button )
							}
						}
					)
				} else {
					//revert to old name.
					$( '#yarns-channel-update-input' ).val( old_channel )

					$( '#yarns-channel-update' ).css( 'visibility', 'visible' )
					$( '#yarns-option-heading' ).css( 'visibility', 'visible' )

					$( '#yarns-channel-update-options' ).hide()
				}
			}
		)

		/**
		 * Save filters button
		 *
		 */
		$( 'body' ).on(
			'click',
			'.yarns-channel-filters-save',
			function () {
				console.log( 'Saving channel filters' )

				button = $( this )
				start_loading( button )

				let parent = $( this ).parent( $( '.yarns-channel' ) )
				let uid = $( this ).data( 'uid' )
				let channel = $( '#yarns-channel-update-name' ).val().trim()
				let options = []

				parent.find( 'label' ).each(
					function ( index ) {
						if ( $( this ).find( 'input' ).prop( 'checked' ) == true ) {
							options.push( $( this ).text() )
						}
					}
				)

				$.ajax(
					{
						url: yarns_microsub_server_ajax.ajax_url,
						type: 'post',
						data: {
							action: 'save_filters',
							uid: uid,
							options: options,
							channel: channel,
						},
						success: function ( response ) {
							let channel_feeds_url = $( '#yarns-breadcrumb-channel' ).attr( 'href' )
							done_loading( button )
							console.log( response )
							console.log( 'success' )
							window.location = channel_feeds_url
						}
					}
				)
			}
		)

		/**
		 * Manage subscriptions
		 */

		/**
		 * Search for feeds
		 */
		$( 'body' ).on(
			'click',
			'#yarns-channel-find-feeds',
			function () {
				clear_notices() // Clear any errors that are showing from a previous function.
				let errors = false
				button = $( this )
				query = $( '#yarns-URL-input' ).val()

				// Validate query.
				if ( query.length < 1 ) {
					display_error( button, 'Please enter a query before searching.' )
					errors = true
				}

				// If there were no errors, perform the search.
				if ( errors == false ) {
					console.log( 'Searching for ' + query )

					start_loading( button )
					$.ajax(
						{
							url: yarns_microsub_server_ajax.ajax_url,
							type: 'post',
							data: {
								action: 'find_feeds',
								query: query,
							},
							success: function ( response ) {
								response = JSON.parse( response )
								done_loading( button )
								console.log( response )
								if ( response['error'] ) {
									display_error( button, response['content'] )
								} else {
									$( '#yarns-feed-picker-list' ).html( response['content'] )

									$( '#yarns-feed-picker-list input' ).first().trigger( 'click' )
								}
							}
						}
					)
				}
			}
		)

		/**
		 * Display a preview
		 */

		/**
		 * Detect preview clicks from the 'add feed' interface
		 */

		$( 'body' ).on(
			'click',
			'#yarns-channel-preview-feed',
			function () {
				preview_url = $( 'input[name=yarns-feed-picker]:checked' ).val()
				preview( preview_url )
			}
		)

		/**
		 * Detect preview clicks from the list of already subscribed feeds
		 */
		$( 'body' ).on(
			'click',
			'.yarns-feed-preview',
			function () {
				preview_url = $( this ).data( 'url' )
				preview( preview_url )
			}
		)

		function preview ( preview_url ) {
			// Show the preview box while loading.
			start_loading( $( '#yarns-preview-container' ) )
			$( '#yarns-preview-url' ).text( preview_url )

			$( '#yarns-preview-outer-container' ).show()
			$( 'body' ).addClass( 'noscroll' )

			$.ajax(
				{
					url: yarns_microsub_server_ajax.ajax_url,
					type: 'post',
					data: {
						action: 'preview_feed',
						url: preview_url,
					},
					success: function ( response ) {
						done_loading( $( '#yarns-preview-container' ) )
						$( '#yarns-preview-container' ).html( response )
						// Change all links to open in new tab.
						$( '#yarns-preview-container' ).find( 'a' ).each(
							function ( index ) {
								$( this ).attr( 'target', '_blank' )
							}
						)
					}
				}
			)
		}

		/**
		 * Close the preview
		 */
		$( 'body' ).on(
			'click',
			'#yarns-preview-close',
			function () {
				$( '#yarns-preview-container' ).html( '' )
				$( '#yarns-preview-outer-container' ).hide()
				$( 'body' ).removeClass( 'noscroll' )
			}
		)

		/**
		 * Follow a feed
		 */
		$( 'body' ).on( 'click',
			'#yarns-channel-add-feed',
			function () {
				url = $( 'input[name=yarns-feed-picker]:checked' ).val()
				uid = $( '#yarns-options-uid' ).text()
				button = $( this )
				start_loading( button )

				$.ajax(
					{
						url: yarns_microsub_server_ajax.ajax_url,
						type: 'post',
						data: {
							action: 'follow_feed',
							uid: uid,
							url: url,
						},
						success: function ( response ) {
							done_loading( button )
							console.log( 'success' )
							window.location = response
							/*$html_content = response.find('#yarns_follow_html');
							$message = response.find('#yarns_follow_message');
							$('#yarns-following-list').html(response);*/
						}
					}
				)
			}
		)

		/**
		 * Unfollow a feed
		 */
		$( 'body' ).on(
			'click',
			'.yarns-feed-unfollow',
			function () {
				console.log( 'clicked unfollow' )
				url = $( this ).data( 'url' )
				uid = $( '#yarns-options-uid' ).text()
				button = $( this )
				start_loading( button )

				$.ajax(
					{
						url: yarns_microsub_server_ajax.ajax_url,
						type: 'post',
						data: {
							action: 'unfollow_feed',
							uid: uid,
							url: url,
						},
						success: function ( response ) {
							done_loading( button )
							console.log( 'success' )
							window.location = response
						}
					}
				)
			}
		)

		/**
		 * Update feed url when radio button is clicked
		 */

		$( 'body' ).on(
			'click',
			'input[name=yarns-feed-picker]',
			function () {
				console.log( $( this ).val() )
			}
		)

		/**
		 * DEBUGGING COMMANDS
		 */
		$( 'body' ).on(
			'click',
			'#yarns_delete_posts',
			function () {
				if ( confirm( 'This will delete content from all channels. ' ) ) {
					button = $( this )
					start_loading( button )
					$.ajax(
						{
							url: yarns_microsub_server_ajax.ajax_url,
							type: 'post',
							data: {
								action: 'delete_posts'
							},
							success: function ( response ) {
								done_loading( button )
								alert( 'Deleted all posts.' )
							}
						}
					)
				}
			}
		)

		$( 'body' ).on(
			'click',
			'#yarns_force_poll',
			function () {
				button = $( this )
				start_loading( button )
				$.ajax(
					{
						url: yarns_microsub_server_ajax.ajax_url,
						type: 'post',
						data: {
							action: 'force_poll'
						},
						success: function ( response ) {
							done_loading( button )
							alert( 'Done polling' )
						}
					}
				)
			}
		)

		function start_loading ( target ) {
			target.addClass( 'yarns-loading' )
		}

		function done_loading ( target ) {
			target.removeClass( 'yarns-loading' )
		}

		/**
		 *  Clears errors and notices from the page
		 */
		function clear_notices () {
			$( '.yarns-error' ).remove()
			$( '.yarns-notice' ).remove()
		}

		/**
		 * Displays an error message after a target element
		 *
		 * @param target  The element after which to display the message.
		 * @param message The message to be displayed
		 */
		function display_error ( target, message ) {
			target.after(
				$(
					'<span/>',
					{
						'class': 'yarns-error',
						text: message
					}
				)
			)
		}

		/**
		 * Displays a notice after a target element
		 *
		 * @param target  The element after which to display the message.
		 * @param message The message to be displayed
		 */
		function display_notice ( target, message ) {
			target.after(
				$(
					'<span/>', {
						'class': 'yarns-notice',
						text: message
					}
				)
			)
		}

	}
)
( jQuery )
