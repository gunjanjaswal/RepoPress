<?php
/**
 * Minimal GitHub REST client (read-only for v1).
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_GitHub_Client {

	const API_BASE = 'https://api.github.com';

	/** @var string */
	private $token;

	/** @var string */
	private $owner;

	/** @var string */
	private $repo;

	public function __construct( $token, $owner, $repo ) {
		$this->token = (string) $token;
		$this->owner = (string) $owner;
		$this->repo  = (string) $repo;
	}

	/**
	 * Verify credentials and repo access.
	 *
	 * @return array|WP_Error Repo data on success.
	 */
	public function test_connection() {
		$res = $this->request( 'GET', '/repos/' . $this->owner . '/' . $this->repo );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] ) {
			return $this->error_from_response( $res );
		}
		return $res['body'];
	}

	/**
	 * Recursive tree for a branch.
	 *
	 * @param string $branch
	 * @return array|WP_Error {tree: array, truncated: bool}
	 */
	public function get_tree( $branch ) {
		$path = '/repos/' . $this->owner . '/' . $this->repo . '/git/trees/'
			. rawurlencode( $branch ) . '?recursive=1';

		$res = $this->request( 'GET', $path );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] ) {
			return $this->error_from_response( $res );
		}

		$body = $res['body'];
		return array(
			'tree'      => isset( $body['tree'] ) && is_array( $body['tree'] ) ? $body['tree'] : array(),
			'truncated' => ! empty( $body['truncated'] ),
		);
	}

	/**
	 * Raw decoded file content by path.
	 *
	 * @param string $file_path
	 * @param string $ref
	 * @return string|WP_Error
	 */
	public function get_file_content( $file_path, $ref ) {
		$path = '/repos/' . $this->owner . '/' . $this->repo . '/contents/'
			. $this->encode_path( $file_path ) . '?ref=' . rawurlencode( $ref );

		$res = $this->request( 'GET', $path );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] ) {
			return $this->error_from_response( $res );
		}

		$body = $res['body'];
		if ( isset( $body['content'], $body['encoding'] ) && 'base64' === $body['encoding'] ) {
			$decoded = base64_decode( str_replace( "\n", '', $body['content'] ), true );
			if ( false !== $decoded ) {
				return $decoded;
			}
		}
		return new WP_Error( 'repopress_decode', __( 'Could not decode file content.', 'repopress' ) );
	}

	/* -------------------------------------------------------------------------
	 * Internals
	 * ---------------------------------------------------------------------- */

	/**
	 * @return array|WP_Error {code:int, body:array, remaining:int|null}
	 */
	private function request( $method, $path, $args = array() ) {
		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'WP-GitHub-Sync/' . REPOPRESS_VERSION,
		);
		if ( '' !== $this->token ) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_request(
			self::API_BASE . $path,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => 20,
				'body'    => isset( $args['body'] ) ? $args['body'] : null,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );

		return array(
			'code'      => (int) wp_remote_retrieve_response_code( $response ),
			'body'      => json_decode( wp_remote_retrieve_body( $response ), true ),
			'remaining' => ( '' === $remaining ? null : (int) $remaining ),
		);
	}

	private function error_from_response( $res ) {
		$message = isset( $res['body']['message'] ) ? $res['body']['message'] : __( 'Unknown error', 'repopress' );
		return new WP_Error(
			'repopress_api_' . $res['code'],
			sprintf(
				/* translators: 1: HTTP status code, 2: error message returned by GitHub. */
				__( 'GitHub API error (%1$d): %2$s', 'repopress' ),
				$res['code'],
				$message
			)
		);
	}

	private function encode_path( $file_path ) {
		$segments = explode( '/', $file_path );
		return implode( '/', array_map( 'rawurlencode', $segments ) );
	}
}
