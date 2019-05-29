
<?php $storage_period = get_site_option('yarns_storage_period'); ?>

<h2> Channels </h2>
<ul id='yarns-channels'>
	<?php echo static::list_channels(); ?>
</ul>

<h3>Add a new channel</h3>
<input id="yarns-new-channel-name" type="text" placeholder="New channel name">
<a class="button" id="yarns-channel-add">+ Add channel</a>


<h2> Yarns options </h2>
<label for="yarns-storage-period">Number of days to store feed items before deleting <input type="number" min="1" id="yarns-storage-period" name="yarns-storage-period" value="<?php echo $storage_period; ?>" size="3" ></input></label>

<br><br><a class="button" id="yarns-save-options">Save options</a>



