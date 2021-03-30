
<!-- Follow new feed -->
<div id="yarns-add-subscription"><h2>Follow a new site:</h2>
	<input type="text" id="yarns-URL-input" name="yarns-URL-input" value="" size="30"  placeholder = "Enter a URL to find its feeds."></input>
	<a class="button" id ="yarns-channel-find-feeds">Search</a>
	<div id="yarns-feed-picker-list"></div>
</div><!--#yarns-add-subscription-->

<!-- Box for displaying previews -->
<div id="yarns-preview-outer-container">
	<div id="yarns-preview-inner-container">
		<div id="yarns-preview-header">Preview: <span id="yarns-preview-url"></span><a class="button" id="yarns-preview-close">Close preview</a></div>
		<div id="yarns-preview-container"></div>
	</div>
</div>

<!-- List of feeds in this channel -->
<div id="yarns-channel-feeds"><h2>Following:</h2>
	<ul id="yarns-following-list">
		<?php Yarns_Microsub_Admin::list_feeds( $channel ); ?>

	</ul><!--#yarns-following-list-->
</div><!--#yarns-channel-feeds-->
