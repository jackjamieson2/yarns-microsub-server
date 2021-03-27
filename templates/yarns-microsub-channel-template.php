<?php
$uid     = sanitize_text_field( wp_unslash( $_GET['channel'] ) );
$channel = Yarns_Microsub_Channels::get_channel( $uid );

$mode = sanitize_text_field( wp_unslash( $_GET['mode'] ) );

$admin_page_link       = Yarns_Microsub_Admin::admin_page_link();
$channel_feeds_link    = Yarns_Microsub_Admin::admin_channel_feeds_link( $uid );
$channel_settings_link = Yarns_Microsub_Admin::admin_channel_settings_link( $uid );


if ( ! $channel ) {
	?>
	<h2>Error finding channel</h2>
	<p>Sorry, there is no channel with this uid.</p>
	<p>Return to <a href="<?php echo $admin_page_link; ?>">Yarns Microsub Server</a></p>
	<?php
} else {
	?>
	<div id="yarns-option-breadcrumbs">
		<?php
		if ( 'channel-settings' === $mode ) {

			?>
			<a id="yarns-breadcrumb-home"  href="<?php echo $admin_page_link; ?>">Yarns Microsub Server</a>
			/ <a id="yarns-breadcrumb-channel" href="<?php echo $channel_feeds_link; ?>"><?php echo $channel['name']; ?></a> / Channel Settings
			<?php
		} else {
			// breadcrumbs for general landing page
			?>
			<a id="yarns-breadcrumb-home" href="<?php echo $admin_page_link; ?>">Yarns Microsub Server</a>
			/ <span><?php echo $channel['name']; ?></span>
			<?php
		}
		?>

	</div><!--#yarns-option-breadcrumbs-->

	<div id="yarns-channel-options" data-uid="<?php echo $uid; ?>">
		<h1 id="yarns-option-heading"><?php echo $channel['name']; ?></h1>
		<span id="yarns-options-uid"><?php echo $uid; ?></span>
		<?php
		if ( 'channel-feeds' === $mode ) {
			?>
			 <a href="<?php echo $channel_settings_link; ?>" class="button" >Channel settings</a> 
			<?php
		}
		?>


		<?php


		if ( 'channel-settings' === $mode ) {
			include 'yarns-microsub-channel-settings.php';
		} else {
			include 'yarns-microsub-channel-feeds.php';
		}

		?>
		<!--<div id="yarns-channel-settings-box">
		</div>-->




		</div><!--#yarns_channel_options-->
<?php } ?>
