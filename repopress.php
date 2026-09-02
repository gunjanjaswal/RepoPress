<?php
/**
 * Plugin Name:       RepoPress
 * Plugin URI:        https://github.com/gunjanjaswal/RepoPress
 * Description:        Publish WordPress content from a GitHub repository. Markdown files with YAML front matter become posts, kept in sync from the branch you choose.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Gunjan Jaswal
 * Author URI:        https://www.gunjanjaswal.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       repopress
 *
 * @package WP_GitHub_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REPOPRESS_VERSION', '0.1.0' );
define( 'REPOPRESS_PLUGIN_FILE', __FILE__ );
define( 'REPOPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REPOPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REPOPRESS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Optional Composer autoload (for a bundled Markdown parser), harmless if absent.
if ( file_exists( REPOPRESS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once REPOPRESS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Bundled Markdown parser, used when Composer's autoload is not present.
if ( ! class_exists( 'Parsedown' ) && file_exists( REPOPRESS_PLUGIN_DIR . 'includes/lib/Parsedown.php' ) ) {
	require_once REPOPRESS_PLUGIN_DIR . 'includes/lib/Parsedown.php';
}

require_once REPOPRESS_PLUGIN_DIR . 'includes/class-logger.php';
require_once REPOPRESS_PLUGIN_DIR . 'includes/class-settings.php';
require_once REPOPRESS_PLUGIN_DIR . 'includes/class-github-client.php';
require_once REPOPRESS_PLUGIN_DIR . 'includes/class-content-parser.php';
require_once REPOPRESS_PLUGIN_DIR . 'includes/class-sync-engine.php';
require_once REPOPRESS_PLUGIN_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once REPOPRESS_PLUGIN_DIR . 'admin/class-admin-page.php';
}

register_activation_hook( __FILE__, array( 'RepoPress_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RepoPress_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		RepoPress_Plugin::instance()->init();
	}
);
