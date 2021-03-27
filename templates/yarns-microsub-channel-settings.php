

<label for="yarns-channel-update-name">Name: </label><input type="text" id="yarns-channel-update-name" name="yarns-channel-update-name" value="<?php echo $channel['name']; ?>" size="30" ></input>


<a class="button delete" id ="yarns-channel-delete" data-uid="<?php echo $uid; ?>">Delete channel</a>


<!-- Channel filters -->
<?php
$all_types     = Yarns_Microsub_Channels::all_post_types();
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
	echo sprintf( '<label><input type="checkbox" %s >%s</input></label>', $checked, $type );
}
?>
</div>
<a class="button-primary yarns-channel-filters-save" data-uid="<?php echo $uid; ?>">Save</a>
<a class="button" href="<?php echo $channel_feeds_link; ?>">Cancel</a>
