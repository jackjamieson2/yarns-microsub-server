<?php
$show_debug = get_site_option( 'yarns_show_debug' );
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
			if ( isset ( $_GET['action'] ) ) {
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

</div>