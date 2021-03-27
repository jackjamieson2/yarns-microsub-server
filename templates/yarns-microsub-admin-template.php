<?php
$show_debug = get_option( 'yarns_show_debug' );
?>

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
			if ( isset( $_GET['action'] ) ) {
				if ( 'unfollow' === $_GET['action'] ) {
					$response = Yarns_Microsub_Channels::follow( $_GET['channel'], $_GET['feed_url'], $unfollow = true );
					echo $response;
				}
			}
			include 'yarns-microsub-channel-template.php';

		} else {
			// Show general settings if no channel is selected.
			//echo static::yarns_general_options();
			include 'yarns-microsub-general-settings.php';
		}
		?>


	</div><!--#yarns-admin-area-->



</div>
