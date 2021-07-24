<?php

class WP_Microsub_Error extends WP_REST_Response {

	public function __construct( $error, $error_description, $code = 200, $debug = null ) {
		$this->set_status( $code );
		$data = array(
			'error'             => $error,
			'error_description' => $error_description,
			'data'              => $debug,
		);
		$data = array_filter( $data );
		$this->set_data( $data );
		if ( WP_DEBUG ) {
			error_log( $this->to_log() ); // phpcs:ignore
		}

	}

	public function set_debug( $array ) {
		$data = $this->get_data();
		$this->set_data( array_merge( $data, $array ) );
	}

	public function to_wp_error() {
		$data   = $this->get_data();
		$status = $this->get_status();
		return new WP_Error( $data['error'], $data['error_description'], array( 'status' => $status ) );
	}

	public function to_log() {
		$data   = $this->get_data();
		$status = $this->get_status();
		$debug  = yarns_get( $data, 'debug', array() );
		return sprintf( 'Microsub Error: %1$s %2$s - %3$s', $status, $data['error'], $data['error_description'], wp_json_encode( $debug ) );
	}

}

function get_microsub_error( $obj ) {
	if ( is_array( $obj ) ) {
		// When checking the result of wp_remote_post
		if ( isset( $obj['body'] ) ) {
			$body = json_decode( $obj['body'], true );
			if ( isset( $body['error'] ) ) {
				return new WP_Microsub_Error(
					$body['error'],
					isset( $body['error_description'] ) ? $body['error_description'] : null,
					$obj['response']['code']
				);
			}
		}
	} elseif ( is_object( $obj ) && 'WP_Microsub_Error' === get_class( $obj ) ) {
		$data = $obj->get_data();
		if ( isset( $data['error'] ) ) {
			return $obj;
		}
	}
	return false;
}

function is_microsub_error( $obj ) {
	return ( $obj instanceof WP_Microsub_Error );
}

// Converts WP_Error into Microsub Error
function microsub_wp_error( $error ) {
	if ( is_wp_error( $error ) ) {
		$data   = $error->get_error_data();
		$status = isset( $data['status'] ) ? $data['status'] : 200;
		if ( is_array( $data ) ) {
			unset( $data['status'] );
		}
		return new WP_Microsub_Error( $error->get_error_code(), $error->get_error_message(), $status, $data );
	}
	return null;
}

