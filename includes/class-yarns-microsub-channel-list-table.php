<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Yarns_Microsub_Channel_List_Table extends WP_List_Table {
	public function get_columns() {
		return array(
			'channel_name' => __( 'Channel Name', 'yarns-microsub-server' ),
			'n_feeds'      => __( 'Number of feeds', 'yarns-microsub-server' ),

		);
	}

	public function get_sortable_columns() {
		return array();
	}




	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();

		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );
		$this->items           = array();

		$channels = Yarns_Microsub_Channels::get( true )['channels'];
		if ( ! $channels ) {
			return;}
		foreach ( $channels as $channel ) {
			if ( isset( $channel['name'] ) ) {
				$value['channel_name'] = $channel['name'];
			} else {
				$value['channel_name'] = '';
			}

			if ( isset( $channel['uid'] ) ) {
				$value['channel_uid'] = $channel['uid'];
			} else {
				$value['channel_uid'] = '';
			}

			if ( isset( $channel['name'] ) ) {
				$value['channel_name'] = $channel['name'];
			} else {
				$value['channel_name'] = '';
			}

			// Get the number of feeds in this channel.
			if ( isset( $channel['items'] ) && is_array( $channel['items'] ) ) {
				$value['n_feeds'] = count( $channel['items'] );
			} else {
				$value['n_feeds'] = 0;
			}
			$this->items[] = $value;
		}
	}


	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}


	public function column_channel_name( $item ) {

		$uid  = $item['channel_uid'];
		$link = esc_url(
			add_query_arg(
				array(
					'page'    => Yarns_Microsub_Admin::$options_page_name,
					'channel' => $uid,
					'mode'    => 'channel-feeds',
				),
				Yarns_Microsub_Admin::admin_page_link()
			)
		);

		$html = '<strong><a data-uid="' . $uid . '" href ="' . $link . '">' . $item['channel_name'] . '</a></strong>';

		//return $item['channel_name'];
		return $html;
	}


	public function column_n_feeds( $item ) {
		return $item['n_feeds'];
	}




}
