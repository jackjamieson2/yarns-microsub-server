<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Yarns_Microsub_Channel_List_Table extends WP_List_Table {
	public function get_columns() {
		return array(
			'channel_name' => __( 'Channel Name', 'yarns_microsub' ),
			'n_feeds'      => __( 'Number of feeds', 'yarns_microsub' ),
			/*'order' => __( 'Order', 'yarns_microsub' ),*/
			// Removed for now, add back once I figure out the best method .
		);
	}

	public function get_sortable_columns() {
		return array();
	}




	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();
		//$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );
		$this->items           = array();


		$channels = Yarns_Microsub_Channels::get( true )['channels'];
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

			if ( isset( $channel['order'] ) ) {
				$value['order'] = $channel['order'];
			} else {
				$value['order'] = null;
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
		$link = esc_url( add_query_arg( array(
				'page'    => Yarns_Microsub_Admin::$options_page_name,
				'channel' => $uid,
				'mode'    => 'channel-feeds'
			), Yarns_Microsub_Admin::admin_page_link() )
		);

		$html = '<strong><a href ="' . $link . '">' . $item['channel_name'] . '</a></strong>';

		//return $item['channel_name'];
		return $html;
	}


	public function column_n_feeds( $item ) {
		return $item['n_feeds'];
	}

	public function column_order( $item ) {

		$link_order_up = esc_url( add_query_arg( array(
				'page'          => Yarns_Microsub_Admin::$options_page_name,
				'action'        => 'order_up',
				'order_channel' => $item['channel_uid'],
			), Yarns_Microsub_Admin::admin_page_link() )
		);

		$link_order_down = esc_url( add_query_arg( array(
				'page'          => Yarns_Microsub_Admin::$options_page_name,
				'action'        => 'order_down',
				'order_channel' => $item['channel_uid'],
			), Yarns_Microsub_Admin::admin_page_link() )
		);
		$actions         = array(
			'order_up'   => '<a href="' . $link_order_up . '">Move up</a>',
			'order_down' => '<a href="' . $link_order_down . '">Move down</a>',
		);


		return $item['order'] . $this->row_actions( $actions );
	}


}