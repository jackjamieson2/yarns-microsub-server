<?php
$storage_period   = get_option( 'yarns_storage_period' );
$yarns_show_debug = get_option( 'yarns_show_debug' );
$debug_checked    = $show_debug ? 'checked' : '';
$yarns_channels   = json_decode( get_option( 'yarns_channels' ), true );
?>


<h2> Yarns options </h2>
<label for="yarns-storage-period">Store posts published within the past  <input type="number" min="1" id="yarns-storage-period" name="yarns-storage-period" value="<?php echo $storage_period; ?>" size="3" ></input> days.</label>
<br>(Feed items older than this will be removed, and posts older than this will not be saved to your feeds).
<br><br>
<label for="yarns-toggle-debug">Show debug options: <input id="yarns-toggle-debug" type="checkbox" <?php echo $debug_checked; ?> ></input></label>
<br><br><a class="button" id="yarns-save-options">Save options</a>


<h2> Channels </h2>

<div id='yarns-channels'>
	<?php
	if ( ! empty( $yarns_channels ) ) {
		echo 'Drag each item into the order you prefer. Click the channel name to manage feeds and access other options.';
		echo static::list_channels();
	} else {
		echo 'No channels found. Create a channel, and then you can start following feeds!';

	}
	?>



</div>

<h3>Add a new channel</h3>
<input id="yarns-new-channel-name" type="text" placeholder="New channel name">
<a class="button" id="yarns-channel-add">+ Add channel</a>








<?php
// Only show debug options if option yarns_show_debug == true
if ( $show_debug ) {
	$debug_html  = '<div id="yarns-debug-log-area">';
	$debug_html .= static::debug_log();
	$debug_html .= '</div>';

	$debug_html .= '<div id="yarns-debug-commands">';
	$debug_html .= static::debug_commands();
	$debug_html .= '</div>';
	echo $debug_html;
}
?>
