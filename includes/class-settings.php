<?php
/**
 * Settings storage and encrypted token handling.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Settings {

	const OPTION_SETTINGS = 'repopress_settings';
	const OPTION_TOKEN    = 'repopress_token';

	/**
	 * Default configuration.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'owner'           => '',
			'repo'            => '',
			'branch'          => 'main',
			'path'            => '',        // Sub-folder in the repo, empty means repo root.
			'post_type'       => 'post',
			'default_status'  => 'draft',   // Status used when front matter omits it.
			'frequency'       => 'manual',  // manual|repopress_15min|hourly|twicedaily|daily.
			'delete_behavior' => 'ignore',  // ignore|trash — what to do when a file disappears.
		);
	}

	/**
	 * @return array
	 */
	public function get_all() {
		$saved = get_option( self::OPTION_SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return wp_parse_args( $saved, $this->defaults() );
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->get_all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Merge and persist settings (token handled separately).
	 *
	 * @param array $data
	 */
	public function update( array $data ) {
		$current = $this->get_all();
		$merged  = wp_parse_args( $data, $current );
		// Only keep known keys.
		$merged = array_intersect_key( $merged, $this->defaults() );
		update_option( self::OPTION_SETTINGS, $merged, false );
	}

	public function is_configured() {
		$all = $this->get_all();
		return '' !== $all['owner'] && '' !== $all['repo'] && '' !== $this->get_token();
	}

	/* -------------------------------------------------------------------------
	 * Token
	 * ---------------------------------------------------------------------- */

	public function get_token() {
		$stored = get_option( self::OPTION_TOKEN, '' );
		return '' === $stored ? '' : $this->decrypt( $stored );
	}

	public function has_token() {
		return '' !== get_option( self::OPTION_TOKEN, '' );
	}

	/**
	 * @param string $token Raw PAT, or empty string to clear.
	 */
	public function set_token( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			delete_option( self::OPTION_TOKEN );
			return;
		}
		update_option( self::OPTION_TOKEN, $this->encrypt( $token ), false );
	}

	/* -------------------------------------------------------------------------
	 * Encryption at rest (obfuscation using site salts).
	 * ---------------------------------------------------------------------- */

	const CIPHER_PREFIX = 'repopress1:';

	private function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plain; // Stored as-is; still access-controlled by the DB.
		}
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', $this->crypt_key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plain;
		}
		return self::CIPHER_PREFIX . base64_encode( $iv . $cipher );
	}

	private function decrypt( $stored ) {
		if ( 0 !== strpos( $stored, self::CIPHER_PREFIX ) ) {
			return $stored; // Plaintext / legacy.
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, strlen( self::CIPHER_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $this->crypt_key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	private function crypt_key() {
		$material = '';
		if ( defined( 'AUTH_KEY' ) ) {
			$material .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_SALT' ) ) {
			$material .= SECURE_AUTH_SALT;
		}
		if ( '' === $material && function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'auth' );
		}
		return hash( 'sha256', $material, true );
	}
}
