<?php

/**
 * Microsub Preview Class
 *
 * @author Jack Jamieson
 */
class Yarns_Microsub_Preview {

	private $data = array();
	private $output = '';

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 * @access public
	 */
	public function __construct( $data = null ) {
		if ( is_array( $data ) ) {
			$this->data = $data;
		}
		$this->html = $this::generate_html();
	}

	public function html() {
		return $this->html;
	}

	private function generate_html() {
		if ( ! $this->data ) {
			return;
		}

		foreach ( $this->data as $item ) {
			$html = '<div class="yarns-preview-item">';
			$html .= $this::author( $item_html );
			//$html .= $this::title($item_html);
			//$html .= $this::content($item_html);
			$html .= '</div><!--.yarns-preview-item-->';
		}
	}

	private function author( $item ) {
		$html = '<div class=yarns-preview-author>';
		if ( ! $item['author'] ) {
			$html .= 'no author information.';
		} else {
			$author = $item['author'];

			if ( $author['photo'] ) {
				$html .= '<img src="' . $author['photo'] . '">';
			}

			$html .= '<span>';
			if ( $author['url'] ) {
				$html .= '<a href="' . $author['url'] . '">';
			}

			if ( $author['name'] ) {
				$html .= $author['name'];
			} else {
				$html .= 'unknown';
			}

			if ( $author['url'] ) {
				$html .= '</a>';
			}

			$html .= '</span>';
		}
		$html .= '</div><!--.yarns-preview-author-->';

		return $html;

	}


}
