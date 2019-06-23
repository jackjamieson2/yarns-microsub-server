<?php

/**
 * Microsub Preview Class
 *
 * @author Jack Jamieson
 */
class Yarns_Microsub_Preview {

	private $data = array();
	private $html = '';

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 * @access public
	 */
	public function __construct( $data = null ) {
		if ( is_array( $data ) && isset( $data['items'] ) ) {
			$this->data = $data['items'];
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

		$html = '';
		foreach ( $this->data as $item ) {

			if ( isset( $item['type'] ) && 'entry' === $item['type'] ) {
				$html .= '<div class="yarns-preview-item">';

				$html .= $this::author( $item );
				$html .= $this::name( $item );
				$html .= $this::published( $item );
				$html .= $this::content( $item );
				$html .= '</div><!--.yarns-preview-item-->';
			}
		}

		return $html;

	}

	private function author( $item ) {
		$html = '<div class=yarns-preview-author>';
		if ( ! isset( $item['author'] ) ) {
			$html .= '(no author information)';
		} else {
			if ( isset( $item['author']['photo'] ) ) {
				$html .= '<img src="' . $item['author']['photo'] . '">';
			}
			$html .= '<span>';
			if ( isset( $item['author']['url'] ) ) {
				$html .= '<a href="' . $item['author']['url'] . '">';
			}
			if ( isset( $item['author']['name'] ) ) {
				$html .= $item['author']['name'];
			} else {
				$html .= 'unknown';
			}
			if ( isset( $item['author']['url'] ) ) {
				$html .= '</a>';
			}
			$html .= '</span>';
		}
		$html .= '</div><!--.yarns-preview-author-->';

		return $html;
	}

	private function name( $item ) {
		if ( ! isset( $item['name'] ) ) {
			return;
		} else {
			$html  = '<h2 class=yarns-preview-title>';
			$html .= $item['name'];
			$html .= '</h2><!--.yarns-preview-title-->';
			return $html;
		}
	}

	private function published( $item ) {
		if ( ! isset( $item['published'] ) ) {
			return;
		} else {
			$html  = '<div class=yarns-preview-published>';
			$html .= '<a href="' . $item['url'] . '">';
			$html .= $item['published'];
			$html .= '</a>';
			$html .= '</div><!--.yarns-preview-published-->';
			return $html;
		}
	}

	private function content( $item ) {
		if ( isset( $item['content'] ) ) {
			$html = '<div class=yarns-preview-content>';
			if ( ! is_array( $item['content'] ) ) {
				$html .= $item['content'];
			} elseif ( isset( $item['content']['html'] ) ) {
				$html .= $item['content']['html'];
			} elseif ( isset( $item['content']['text'] ) ) {
				$html .= $item['content']['text'];
			}
			$html .= '</div><!--.yarns-preview-content-->';
			return $html;
		} elseif ( isset( $item['summary'] ) ) {
			// Fall back to ['summary'].
			$html  = '<div class=yarns-preview-content>';
			$html .= $item['summary'];
			$html .= '</div><!--.yarns-preview-content-->';
			return $html;
		} else {
			return;
		}
	}



}
