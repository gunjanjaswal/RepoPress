<?php
/**
 * Lightweight sync log kept in an option (capped ring buffer).
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Logger {

	const OPTION = 'repopress_log';
	const MAX_ENTRIES = 100;

	/**
	 * Append an entry.
	 *
	 * @param string $level   info|success|warning|error.
	 * @param string $message Human readable message.
	 */
	public function log( $level, $message ) {
		$entries   = $this->get_entries();
		$entries[] = array(
			'time'    => current_time( 'mysql' ),
			'level'   => sanitize_key( $level ),
			'message' => wp_strip_all_tags( (string) $message ),
		);

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * @return array[]
	 */
	public function get_entries() {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? $entries : array();
	}

	public function clear() {
		delete_option( self::OPTION );
	}
}
