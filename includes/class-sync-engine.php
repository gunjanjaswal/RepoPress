<?php
/**
 * The pull loop: read the repo tree, create/update posts for changed files.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Sync_Engine {

	const LOCK_TRANSIENT = 'repopress_sync_lock';
	const META_PATH      = '_repopress_repo_path';
	const META_SHA       = '_repopress_blob_sha';
	const META_SYNCED    = '_repopress_synced_at';

	/** @var RepoPress_Settings */
	private $settings;

	/** @var RepoPress_Logger */
	private $logger;

	public function __construct( RepoPress_Settings $settings, RepoPress_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Run one full sync pass.
	 *
	 * @return array|WP_Error Result counts on success.
	 */
	public function sync() {
		if ( ! $this->settings->is_configured() ) {
			return new WP_Error( 'repopress_not_configured', __( 'Connect a repository and token first.', 'repopress' ) );
		}

		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return new WP_Error( 'repopress_locked', __( 'A sync is already running.', 'repopress' ) );
		}
		set_transient( self::LOCK_TRANSIENT, time(), 5 * MINUTE_IN_SECONDS );

		$result = $this->run();

		delete_transient( self::LOCK_TRANSIENT );
		return $result;
	}

	private function run() {
		$config = $this->settings->get_all();
		$client = new RepoPress_GitHub_Client( $this->settings->get_token(), $config['owner'], $config['repo'] );
		$parser = new RepoPress_Content_Parser( $this->settings );

		$tree = $client->get_tree( $config['branch'] );
		if ( is_wp_error( $tree ) ) {
			$this->logger->log( 'error', $tree->get_error_message() );
			return $tree;
		}
		if ( $tree['truncated'] ) {
			$this->logger->log( 'warning', __( 'The repository tree was truncated by GitHub; some files may be skipped. Consider a sub-folder path.', 'repopress' ) );
		}

		$prefix = $this->normalize_prefix( $config['path'] );
		$counts = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'removed' => 0,
		);
		$seen = array();

		foreach ( $tree['tree'] as $node ) {
			if ( empty( $node['type'] ) || 'blob' !== $node['type'] || empty( $node['path'] ) ) {
				continue;
			}
			$path = $node['path'];

			if ( '' !== $prefix && 0 !== strpos( $path, $prefix ) ) {
				continue;
			}
			if ( ! preg_match( '/\.(md|markdown)$/i', $path ) ) {
				continue;
			}

			$seen[ $path ] = true;
			$existing      = $this->find_post_by_path( $path );

			if ( $existing && get_post_meta( $existing->ID, self::META_SHA, true ) === $node['sha'] ) {
				++$counts['skipped'];
				continue;
			}

			$content = $client->get_file_content( $path, $config['branch'] );
			if ( is_wp_error( $content ) ) {
				++$counts['errors'];
				$this->logger->log( 'error', $path . ': ' . $content->get_error_message() );
				continue;
			}

			$parsed  = $parser->parse( $content, $path );
			$postarr = $parsed['post'];

			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) ) {
				++$counts['errors'];
				$this->logger->log( 'error', $path . ': ' . $post_id->get_error_message() );
				continue;
			}

			update_post_meta( $post_id, self::META_PATH, $path );
			update_post_meta( $post_id, self::META_SHA, $node['sha'] );
			update_post_meta( $post_id, self::META_SYNCED, current_time( 'mysql' ) );

			foreach ( $parsed['terms'] as $taxonomy => $names ) {
				if ( ! empty( $names ) && taxonomy_exists( $taxonomy ) ) {
					wp_set_object_terms( $post_id, $names, $taxonomy, false );
				}
			}

			if ( $existing ) {
				++$counts['updated'];
			} else {
				++$counts['created'];
			}
		}

		$counts['removed'] = $this->handle_removed( $seen, $config['delete_behavior'] );

		$this->logger->log(
			'success',
			sprintf(
				/* translators: sync summary counts. */
				__( 'Sync finished. Created %1$d, updated %2$d, skipped %3$d, removed %4$d, errors %5$d.', 'repopress' ),
				$counts['created'],
				$counts['updated'],
				$counts['skipped'],
				$counts['removed'],
				$counts['errors']
			)
		);

		update_option( 'repopress_last_sync', current_time( 'mysql' ), false );

		return $counts;
	}

	/**
	 * Apply the delete policy to posts whose source file is gone.
	 *
	 * @param array  $seen     Paths present in this pass.
	 * @param string $behavior ignore|trash.
	 * @return int Number acted on.
	 */
	private function handle_removed( $seen, $behavior ) {
		$tracked = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'no_found_rows'  => true,
			)
		);

		$acted = 0;
		foreach ( $tracked as $post_id ) {
			$path = get_post_meta( $post_id, self::META_PATH, true );
			if ( '' === $path || isset( $seen[ $path ] ) ) {
				continue;
			}
			if ( 'trash' === $behavior ) {
				wp_trash_post( $post_id );
				$this->logger->log( 'warning', sprintf( /* translators: repo file path. */ __( 'Trashed post for removed file: %s', 'repopress' ), $path ) );
				++$acted;
			} else {
				$this->logger->log( 'info', sprintf( /* translators: repo file path. */ __( 'Source file gone, post left untouched: %s', 'repopress' ), $path ) );
			}
		}
		return $acted;
	}

	/**
	 * @param string $path
	 * @return WP_Post|null
	 */
	private function find_post_by_path( $path ) {
		$found = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return empty( $found ) ? null : $found[0];
	}

	private function normalize_prefix( $path ) {
		$path = trim( (string) $path, "/ \t\n\r\0\x0B" );
		return '' === $path ? '' : $path . '/';
	}
}
