<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Yarns_Microsub_Feed_List_Table extends WP_List_Table {
	public function get_columns() {
		return array(
			'url'          => __( 'Feed URL', 'yarns_microsub' ),
			'_name'        => __( 'Name', 'yarns_microsub' ),
			'_type'        => __( 'Type', 'yarns_microsub' ),
			'_last_polled' => __( 'Last Polled', 'yarns_microsub' ),
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

		$link_unfollow = esc_url(
			add_query_arg(
				array(
					'page'     => Yarns_Microsub_Admin::$options_page_name,
					'action'   => 'unfollow',
					'channel'  => $this->channel,
					'feed_url' => $item['url'],
				),
				Yarns_Microsub_Admin::admin_page_link()
			)
		);

		$link_preview = esc_url(
			add_query_arg(
				array(
					'page'     => Yarns_Microsub_Admin::$options_page_name,
					'action'   => 'preview',
					'channel'  => $this->channel,
					'feed_url' => $item['url'],
				),
				Yarns_Microsub_Admin::admin_page_link()
			)
		);


		$actions = array(
			'preview' => '<a class="yarns-feed-preview" data-url="' . $item['url'] . '" href="' . $link_preview . '">Preview</a>',
			'delete'  => '<a class="yarns-feed-unfollow" data-url="' . $item['url'] . '" href="' . $link_unfollow . '">Unfollow</a>',
			//'delete' => '<a class="yarns-feed-unfollow"  data-url="' . $item['url'] . '">Unfollow</a>',
		);

		return $item['url'] . $this->row_actions( $actions );
	}


	public function column__last_polled( $item ) {
		$link_poll = esc_url( add_query_arg( array(
				'page'     => Yarns_Microsub_Admin::$options_page_name,
				'action'   => 'poll',
				'channel'  => $this->channel,
				'feed_url' => $item['url'],
			), Yarns_Microsub_Admin::admin_page_link() )
		);


		$actions = array(
			'poll' => '<a href="' . $link_poll . '">Poll now</a>',
		);

		return $item['_last_polled'] . $this->row_actions( $actions );
	}


}
