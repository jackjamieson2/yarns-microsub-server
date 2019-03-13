<?php
$uid = sanitize_text_field( wp_unslash( $_GET['channel'] ) );

$channel = Yarns_Microsub_Channels::get_channel( $uid );
$admin_page_link = Yarns_Microsub_Admin::admin_page_link();

if ( ! $channel ) {
	?>
	<h2>Error finding channel</h2>
	<p>Sorry, there is no channel with this uid.</p>
	<p>Return to <a href="<?php echo $admin_page_link; ?>">Yarns Options</a></p>
	<?php
} else {
	?>
	<div id="yarns-option-breadcrumbs">
		<a href="<?php echo $admin_page_link; ?>">Yarns Options</a>
		/ <span><?php echo $channel['name']; ?></span>
	</div><!--#yarns-option-breadcrumbs-->

	<div id="yarns-channel-options" data-uid="<?php echo $uid;?>">
		<h2 id="yarns-option-heading"><?php echo $channel['name']; ?></h2>
		<span id="yarns-channel-update" >Rename channel</span>
		<span id="yarns-channel-update-options">
			<input type="text" id="yarns-channel-update-input" name="yarns-channel-update-input" value="<?php echo $channel['name']; ?>" size="30" ></input>
			<a class="button" id ="yarns-channel-update-save" data-uid="<?php echo $uid; ?>">Save</a>
		</span><!--#yarns-channel-update-options-->


		<span id="yarns-channel-delete" data-uid="<?php echo $uid; ?>">Delete channel</span>
		<span id="yarns-options-uid"><?php echo $uid; ?></span>

		<!-- Channel filters -->
			<?php
			$all_types = Yarns_Microsub_Channels::all_post_types();
			$channel_types = Yarns_Microsub_Channels::channel_post_types( $channel );

			if ( isset( $channel['post-types'] ) ) {
				$channel_types = $channel['post-types'];
			} else {
				// If the channel types haven't been set (then set to all types).
				$channel_types = $all_types;
			}
			?>

			<div class="yarns-channel-filters">
			<h3>Channel options</h3>
			<p> Include the following types of posts from this feed: </p>
			<?php
			foreach ( $all_types as $type ) {
				if ( in_array( $type, $channel_types, true ) ) {
					$checked = 'checked';
				} else {
					$checked = '';
				}
				echo sprintf('<label><input type="checkbox" %s > %s</input></label>',$checked,$type);
			}
			?>


			<br><a class="button yarns-channel-filters-save" data-uid="' . $channel['uid'] . '">Update</a>
		</div>


		<!-- Follow new feed -->
		<div id="yarns-add-subscription"><h2>Follow a new site:</h2>
			<input type="text" id="yarns-URL-input" name="yarns-URL-input" value="" size="30"  placeholder = "Enter a URL to find its feeds."></input>
			<a class="button" id ="yarns-channel-find-feeds">Search</a>
			<div id="yarns-feed-picker-list"></div>
		</div><!--#yarns-add-subscription-->

		<!-- Box for displaying previews -->
		<div id="yarns-preview-outer-container">
			<div id="yarns-preview-header">Preview: <span id="yarns-preview-url"></span><a class="button" id="yarns-preview-close">Close preview</a></div>
			<div id="yarns-preview-container"></div>
		</div>

		<!-- List of feeds in this channel -->
		<div id="yarns-channel-feeds"><h2>Following:</h2>
			<ul id="yarns-following-list">
				<?php Yarns_Microsub_Admin::list_feeds($channel);?>

			</ul><!--#yarns-following-list-->
		</div><!--#yarns-channel-feeds-->



		</div><!--#yarns_channel_options-->
<?php } ?>