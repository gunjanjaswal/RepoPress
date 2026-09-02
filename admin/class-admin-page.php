<?php
/**
 * Settings screen, connection test, and manual sync trigger.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Admin_Page {

	const MENU_SLUG      = 'repopress';
	const CAP            = 'manage_options';
	const TOKEN_UNCHANGED = '__repopress_token_unchanged__';

	/** @var RepoPress_Settings */
	private $settings;

	/** @var RepoPress_Logger */
	private $logger;

	public function __construct( RepoPress_Settings $settings, RepoPress_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_repopress_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_repopress_sync_now', array( $this, 'handle_sync_now' ) );
		add_action( 'admin_post_repopress_test', array( $this, 'handle_test' ) );
		add_filter( 'plugin_action_links_' . REPOPRESS_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function add_menu() {
		add_menu_page(
			__( 'RepoPress', 'repopress' ),
			__( 'RepoPress', 'repopress' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-update-alt',
			58
		);
	}

	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'repopress' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Add support and author links to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links
	 * @param string   $file
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( REPOPRESS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}
		$links[] = '<a href="https://ko-fi.com/gunjanjaswal" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support on Ko-fi', 'repopress' ) . '</a>';
		$links[] = '<a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Author', 'repopress' ) . '</a>';
		return $links;
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$settings = $this->settings;
		$logger   = $this->logger;
		require REPOPRESS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/* -------------------------------------------------------------------------
	 * Handlers
	 * ---------------------------------------------------------------------- */

	public function handle_save() {
		// Nonce and capability are verified inline here so the input reads below are provably guarded.
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'repopress' ) );
		}
		check_admin_referer( 'repopress_save' );

		$data = array(
			'owner'           => isset( $_POST['owner'] ) ? sanitize_text_field( wp_unslash( $_POST['owner'] ) ) : '',
			'repo'            => isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '',
			'branch'          => isset( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main',
			'path'            => isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '',
			'post_type'       => isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post',
			'default_status'  => isset( $_POST['default_status'] ) ? sanitize_key( wp_unslash( $_POST['default_status'] ) ) : 'draft',
			'frequency'       => isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'manual',
			'delete_behavior' => isset( $_POST['delete_behavior'] ) ? sanitize_key( wp_unslash( $_POST['delete_behavior'] ) ) : 'ignore',
		);
		$this->settings->update( $data );

		// Token only changes when the field is filled with something new.
		$token = isset( $_POST['token'] ) ? trim( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $token && self::TOKEN_UNCHANGED !== $token ) {
			$this->settings->set_token( $token );
		}

		RepoPress_Plugin::instance()->reschedule();

		$this->redirect_back( 'saved' );
	}

	public function handle_sync_now() {
		$this->guard( 'repopress_sync_now' );

		$engine = new RepoPress_Sync_Engine( $this->settings, $this->logger );
		$result = $engine->sync();

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'sync_error', $result->get_error_message() );
		}
		$this->redirect_back( 'synced' );
	}

	public function handle_test() {
		$this->guard( 'repopress_test' );

		$config = $this->settings->get_all();
		$client = new RepoPress_GitHub_Client( $this->settings->get_token(), $config['owner'], $config['repo'] );
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'test_error', $result->get_error_message() );
		}
		$name = isset( $result['full_name'] ) ? $result['full_name'] : $config['owner'] . '/' . $config['repo'];
		$this->redirect_back( 'test_ok', $name );
	}

	/* -------------------------------------------------------------------------
	 * Internals
	 * ---------------------------------------------------------------------- */

	private function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'repopress' ) );
		}
		check_admin_referer( $action );
	}

	private function redirect_back( $notice, $detail = '' ) {
		$args = array(
			'page'        => self::MENU_SLUG,
			'repopress_notice' => $notice,
		);
		if ( '' !== $detail ) {
			$args['repopress_detail'] = rawurlencode( $detail );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
