<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Yarns_Microsub_Feed_List_Table extends WP_List_Table {
	public function get_columns() {
		return array(
			'url'          => __( 'Feed URL', 'yarns-microsub-server' ),
			/*'_name'        => __( 'Name', 'yarns_microsub' ),*/
			/*'_type'        => __( 'Type', 'yarns_microsub' ),*/
			'_last_polled' => __( 'Last Polled', 'yarns-microsub-server' ),
		);
	}

	public function get_sortable_columns() {
		return array();
	}


	private $channel;

	public function set_channel( $channel ) {
		$this->channel = $channel;
	}


	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();
		//$this->process_action();
		$feeds = Yarns_Microsub_Channels::list_follows( $this->channel );

		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );
		$this->items           = array();

		/*echo wp_json_encode( $this->channel );*/
		if ( ! is_array( $feeds ) ) {
			return;
		}

		foreach ( $feeds['items'] as $feed ) {
			if ( isset( $feed['url'] ) ) {
				$value['url'] = $feed['url'];
			} else {
				$value['url'] = '';
			}

			if ( isset( $feed['_name'] ) ) {
				$value['_name'] = $feed['_name'];
			} else {
				$value['_name'] = '';
			}

			if ( isset( $feed['_type'] ) ) {
				$value['_type'] = $feed['_type'];
			} else {
				$value['_type'] = '';
			}

			if ( isset( $feed['_last_polled'] ) ) {
				$value['_last_polled'] = $feed['_last_polled'];
			} else {
				$value['_last_polled'] = '';
			}

			$this->items[] = $value;
		}

	}


	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	public function column_url( $item ) {

		$link_unfollow_args = array(
			'action'   => 'unfollow',
			'feed_url' => $item['url'],
		);
		$link_unfollow      = Yarns_Microsub_Admin::admin_channel_feeds_link( $this->channel, $link_unfollow_args );

		$link_preview_args = array(
			'action'   => 'preview',
			'feed_url' => $item['url'],
		);
		$link_preview      = Yarns_Microsub_Admin::admin_channel_feeds_link( $this->channel, $link_preview_args );

		$actions = array(
			'preview' => '<a class="yarns-feed-preview" data-url="' . $item['url'] . '" href="' . $link_preview . '">Preview</a>',
			'delete'  => '<a class="yarns-feed-unfollow" data-url="' . $item['url'] . '" href="' . $link_unfollow . '">Unfollow</a>',
			//'delete' => '<a class="yarns-feed-unfollow"  data-url="' . $item['url'] . '">Unfollow</a>',
		);

		return $item['url'] . $this->row_actions( $actions );
	}


	public function column__last_polled( $item ) {

		/* The polling link doesn't work yet, so removing it for now */
		/*
		$link_poll_args = array(
			'action' => 'poll',
			'feed_url' => $item['url'],
		);
		$link_poll =	Yarns_Microsub_Admin::admin_channel_feeds_link($this->channel, $link_poll_args);


		$actions = array(
			'poll' => '<a href="' . $link_poll . '">Poll now</a>',
		);

		return $item['_last_polled'] . $this->row_actions( $actions );
		*/
		return $item['_last_polled'];
	}


}
