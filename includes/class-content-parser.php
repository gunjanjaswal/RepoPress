<?php
/**
 * Splits YAML front matter from Markdown and builds a post array.
 *
 * The front matter parser handles the constrained subset used in practice:
 * scalar values, quoted strings, inline arrays ([a, b]) and block lists.
 * It is intentionally not a full YAML implementation.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Content_Parser {

	/** @var RepoPress_Settings */
	private $settings;

	public function __construct( RepoPress_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param string $raw       File contents.
	 * @param string $file_path Repo path, used for slug/title fallback.
	 * @return array {post: array, terms: array<string,string[]>}
	 */
	public function parse( $raw, $file_path ) {
		$raw   = preg_replace( '/\r\n|\r/', "\n", (string) $raw );
		$front = array();
		$body  = $raw;

		if ( preg_match( '/^(?:\xEF\xBB\xBF)?\s*---\s*\n(.*?)\n---\s*(?:\n|$)(.*)$/s', $raw, $m ) ) {
			$front = $this->parse_front_matter( $m[1] );
			$body  = $m[2];
		}

		$title = $this->resolve_title( $front, $body, $file_path );

		$status = isset( $front['status'] ) && '' !== $front['status']
			? sanitize_key( $front['status'] )
			: $this->settings->get( 'default_status', 'draft' );

		$type = isset( $front['type'] ) && '' !== $front['type']
			? sanitize_key( $front['type'] )
			: $this->settings->get( 'post_type', 'post' );

		$post = array(
			'post_title'   => $title,
			'post_content' => $this->markdown_to_html( $body ),
			'post_status'  => $status,
			'post_type'    => $type,
		);

		if ( ! empty( $front['slug'] ) ) {
			$post['post_name'] = sanitize_title( $front['slug'] );
		}

		if ( ! empty( $front['date'] ) ) {
			$ts = strtotime( is_array( $front['date'] ) ? reset( $front['date'] ) : $front['date'] );
			if ( $ts ) {
				$post['post_date'] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		if ( isset( $front['excerpt'] ) && '' !== $front['excerpt'] ) {
			$post['post_excerpt'] = wp_strip_all_tags( (string) $front['excerpt'] );
		}

		$terms = array(
			'category' => $this->to_name_list( isset( $front['categories'] ) ? $front['categories'] : array() ),
			'post_tag' => $this->to_name_list( isset( $front['tags'] ) ? $front['tags'] : array() ),
		);

		return array(
			'post'  => $post,
			'terms' => array_filter( $terms ),
		);
	}

	/* -------------------------------------------------------------------------
	 * Front matter
	 * ---------------------------------------------------------------------- */

	private function parse_front_matter( $text ) {
		$data        = array();
		$current_key = null;

		foreach ( explode( "\n", $text ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			// Block list item ("  - value") belonging to the previous key.
			if ( null !== $current_key && preg_match( '/^\s*-\s+(.*)$/', $line, $m ) ) {
				if ( ! is_array( $data[ $current_key ] ) ) {
					$data[ $current_key ] = array();
				}
				$data[ $current_key ][] = $this->clean_scalar( $m[1] );
				continue;
			}

			if ( ! preg_match( '/^([A-Za-z0-9_\-]+)\s*:\s*(.*)$/', $line, $m ) ) {
				continue;
			}

			$key   = strtolower( $m[1] );
			$value = trim( $m[2] );

			if ( '' === $value ) {
				$data[ $key ] = array(); // Expect a following block list.
				$current_key  = $key;
				continue;
			}

			$current_key = $key;

			if ( preg_match( '/^\[(.*)\]$/', $value, $arr ) ) {
				$items        = array_filter( array_map( 'trim', explode( ',', $arr[1] ) ), 'strlen' );
				$data[ $key ] = array_map( array( $this, 'clean_scalar' ), $items );
			} else {
				$data[ $key ] = $this->clean_scalar( $value );
			}
		}

		return $data;
	}

	private function clean_scalar( $v ) {
		$v = trim( $v );
		if ( strlen( $v ) >= 2 ) {
			$first = $v[0];
			$last  = substr( $v, -1 );
			if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
				$v = substr( $v, 1, -1 );
			}
		}
		return $v;
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	private function resolve_title( $front, $body, $file_path ) {
		if ( ! empty( $front['title'] ) ) {
			return sanitize_text_field( is_array( $front['title'] ) ? reset( $front['title'] ) : $front['title'] );
		}
		if ( preg_match( '/^\s*#\s+(.+)$/m', $body, $m ) ) {
			return sanitize_text_field( trim( $m[1] ) );
		}
		$base = pathinfo( $file_path, PATHINFO_FILENAME );
		return sanitize_text_field( ucwords( str_replace( array( '-', '_' ), ' ', $base ) ) );
	}

	private function to_name_list( $value ) {
		if ( '' === $value || array() === $value ) {
			return array();
		}
		$value = is_array( $value ) ? $value : array( $value );
		$names = array_map( 'sanitize_text_field', $value );
		return array_values( array_filter( $names, 'strlen' ) );
	}

	/**
	 * Convert Markdown to HTML. Uses Parsedown when available, otherwise a
	 * conservative fallback that keeps paragraphs and existing HTML intact.
	 *
	 * @param string $markdown
	 * @return string
	 */
	private function markdown_to_html( $markdown ) {
		$markdown = ltrim( (string) $markdown, "\n" );

		if ( class_exists( '\\Parsedown' ) ) {
			$parser = new \Parsedown();
			if ( method_exists( $parser, 'setSafeMode' ) ) {
				$parser->setSafeMode( true );
			}
			$html = $parser->text( $markdown );
		} else {
			// Fallback: preserve raw HTML and paragraph breaks without a parser.
			$html = wpautop( $markdown );
		}

		/**
		 * Filter the HTML produced for a synced post's content.
		 *
		 * @param string $html
		 * @param string $markdown
		 */
		return apply_filters( 'repopress_post_content_html', $html, $markdown );
	}
}
