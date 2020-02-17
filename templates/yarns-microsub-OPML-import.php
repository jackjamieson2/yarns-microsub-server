<?php

$yarns_channels = json_decode( get_site_option( 'yarns_channels' ), true );


// Check that the nonce is valid, and the user can edit this post.
if (
	isset( $_POST['yarns-opml-import-file-nonce'] )
	&& wp_verify_nonce( $_POST['yarns-opml-import-file-nonce'], 'yarns-opml-import-file' )
	//&& current_user_can( 'edit_post', $_POST['post_id'] )
) {
	// The nonce was valid and the user has the capabilities, it is safe to continue.

	// These files need to be included as dependencies when on the front end.
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );

	$file = $_FILES["yarns-opml-import-file"];

	//echo wp_json_encode( $file );

	$overrides = array(
		'test_form' => false,
		'test_type' => false,  // Allows .xml or .opml uploads.
	);
	// Let WordPress handle the upload.
	// Remember, 'my_image_upload' is the name of our file input in our form above.
	$opml_upload = wp_handle_upload( $file, $overrides );


	if ( $opml_upload && ! isset( $opml_upload['error'] ) ) {
		echo "File is valid, and was successfully uploaded.\n";
		var_dump( $opml_upload );
	} else {
		/**
		 * Error generated by _wp_handle_upload()
		 *
		 * @see _wp_handle_upload() in wp-admin/includes/file.php
		 */
		echo wp_json_encode( $opml_upload['error'] );
	}
} else {
	echo "security error";
	// The security check failed, maybe show the user an error.
}


/*




$file = $_FILES["yarns-opml-import-file"];



$file_info = array(
	'name' => $file['name'],
	'type' => $file['type'],
	'tmp_name' => $file['tmp_name'],
	'error' => $file['error'],
	'size' => $file['size']
);
$_FILES = array("upload_file" => $file);
$attachment_id = media_handle_upload("upload_file", 0);

if (is_wp_error($attachment_id)) {
	// There was an error uploading the image.
	echo "Error adding file";
} else {
	// The image was uploaded successfully!
	echo "File added successfully with ID: " . $attachment_id . "<br>";
	echo wp_get_attachment_image($attachment_id, array(800, 600)) . "<br>"; //Display the uploaded image with a size you wish. In this case it is 800x600
}



*/
?>
<h2> Import feeds from OPML</h2>
<p>OPML is a format used to import/export lists of feeds. If you have an OPML file (e.g. exported from an RSS reader)
	you can import it into Yarns.</p>