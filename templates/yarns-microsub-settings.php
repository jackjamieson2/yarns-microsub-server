<div class='wrap'>
	<div id='yarns-admin-area'>

		<div id="yarns-logo">
			<img src="<?php echo esc_url( plugins_url( '../images/yarns_logo.png', __FILE__ ) ); ?>"
			     alt="Yarns">
			<span id="yarns-subheading">Microsub Server</span>
		</div>
		<?php

		if ( isset( $_GET['channel'] ) ) {
			// Show settings for specific channel if selected.

			include 'yarns-microsub-channel-options.php';

		} else {
			// Show general settings if no channel is selected.
			//echo static::yarns_general_options();
			include 'yarns-microsub-general-options.php';
		}
		?>


	</div><!--#yarns-admin-area-->
	<div id='yarns-debug-log-area'>
		<?php echo static::yarns_debug_log(); ?>
	</div>
	<div id='yarns-debug-commands'>
		<?php echo static::yarns_debug_commands(); ?>
	</div>
</div>