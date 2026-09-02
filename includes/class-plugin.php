<?php
/**
 * Plugin orchestrator: wiring, cron, lifecycle.
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RepoPress_Plugin {

	const CRON_HOOK = 'repopress_scheduled_sync';

	/** @var RepoPress_Plugin|null */
	private static $instance = null;

	/** @var RepoPress_Settings */
	public $settings;

	/** @var RepoPress_Logger */
	public $logger;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->logger   = new RepoPress_Logger();
		$this->settings = new RepoPress_Settings();
	}

	public function init() {
		// Translations for WordPress.org-hosted plugins are loaded automatically since WP 4.6.
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_sync' ) );

		if ( is_admin() ) {
			$admin = new RepoPress_Admin_Page( $this->settings, $this->logger );
			$admin->init();
		}
	}

	public function run_scheduled_sync() {
		$engine = new RepoPress_Sync_Engine( $this->settings, $this->logger );
		$engine->sync();
	}

	/**
	 * Add a 15-minute schedule for users who want tighter polling.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['repopress_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (RepoPress)', 'repopress' ),
		);
		return $schedules;
	}

	/**
	 * Re-schedule the cron event to match the configured frequency.
	 * Called on activation and whenever settings are saved.
	 */
	public function reschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		$frequency = $this->settings->get( 'frequency', 'manual' );
		$valid     = array( 'repopress_15min', 'hourly', 'twicedaily', 'daily' );

		if ( in_array( $frequency, $valid, true ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $frequency, self::CRON_HOOK );
		}
	}

	/* -------------------------------------------------------------------------
	 * Lifecycle
	 * ---------------------------------------------------------------------- */

	public static function activate() {
		self::instance()->reschedule();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
