

		<h2> Channels </h2>
		<ul id='yarns-channels'>
			<?php echo static::list_channels(); ?>
		</ul>
		<input id="yarns-new-channel-name" type="text" placeholder="New channel name">
		<button id="yarns-channel-add">+ Add channel</button>


		<?php $storage_period = get_site_option('yarns_storage_period'); ?>
		<p>Storage period: <?php echo $storage_period; ?> days</p>


