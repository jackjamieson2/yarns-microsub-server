
<?php
$storage_period = get_site_option('yarns_storage_period');
$yarns_show_debug = get_site_option('yarns_show_debug');
$debug_checked = $show_debug ? 'checked' : '';
?>


<h2> Channels </h2>
<ul id='yarns-channels'>
	<?php echo static::list_channels(); ?>
</ul>

<h3>Add a new channel</h3>
<input id="yarns-new-channel-name" type="text" placeholder="New channel name">
<a class="button" id="yarns-channel-add">+ Add channel</a>


<h2> Yarns options </h2>
<label for="yarns-storage-period">Number of days to store feed items before deleting: <input type="number" min="1" id="yarns-storage-period" name="yarns-storage-period" value="<?php echo $storage_period; ?>" size="3" ></input></label>
<br><br>
<label for="yarns-toggle-debug">Show debug options: <input id="yarns-toggle-debug" type="checkbox" <?php echo $debug_checked; ?> ></input></label>



<br><br><a class="button" id="yarns-save-options">Save options</a>



<?php
// Only show debug options if option yarns_show_debug == true
if ( $show_debug ) {
	$debug_html = '<div id="yarns-debug-log-area">';
	$debug_html .= static::debug_log();
	$debug_html .= '</div>';

	$debug_html .= '<div id="yarns-debug-commands">';
	$debug_html .= $show_debug;
	$debug_html .= static::debug_commands();
	$debug_html .= '</div>';
	echo $debug_html;
}
?>
