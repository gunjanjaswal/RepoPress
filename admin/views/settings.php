<?php
/**
 * Settings screen markup.
 *
 * @package RepoPress
 * @var RepoPress_Settings $settings
 * @var RepoPress_Logger   $logger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$repopress_config    = $settings->get_all();
$repopress_has_token = $settings->has_token();
$repopress_last_sync = get_option( 'repopress_last_sync', '' );
$repopress_is_ready  = ( '' !== $repopress_config['owner'] && '' !== $repopress_config['repo'] && $repopress_has_token );

// Read notice (redirect params are display-only, sanitized here).
$repopress_notice = isset( $_GET['repopress_notice'] ) ? sanitize_key( wp_unslash( $_GET['repopress_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$repopress_detail = isset( $_GET['repopress_detail'] ) ? sanitize_text_field( wp_unslash( $_GET['repopress_detail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$repopress_notices = array(
	'saved'      => array( 'success', __( 'Settings saved.', 'repopress' ) ),
	'synced'     => array( 'success', __( 'Sync complete. See the activity log below.', 'repopress' ) ),
	'test_ok'    => array( 'success', __( 'Connected to repository:', 'repopress' ) ),
	'sync_error' => array( 'error', __( 'Sync failed:', 'repopress' ) ),
	'test_error' => array( 'error', __( 'Connection failed:', 'repopress' ) ),
);

$repopress_statuses  = array( 'publish', 'draft', 'pending', 'private' );
$repopress_freqs     = array(
	'manual'          => __( 'Manual only', 'repopress' ),
	'repopress_15min' => __( 'Every 15 minutes', 'repopress' ),
	'hourly'          => __( 'Hourly', 'repopress' ),
	'twicedaily'      => __( 'Twice daily', 'repopress' ),
	'daily'           => __( 'Daily', 'repopress' ),
);
$repopress_posttypes = get_post_types( array( 'show_ui' => true ), 'objects' );
$repopress_post_url  = esc_url( admin_url( 'admin-post.php' ) );
?>
<div class="wrap repopress-wrap">
	<style>
		.repopress-wrap { max-width: 1180px; }
		.repopress-wrap * { box-sizing: border-box; }
		.rp-hero {
			display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;
			background: linear-gradient(120deg, #1b1f24 0%, #24292f 55%, #2f6f4f 140%);
			color: #fff; border-radius: 14px; padding: 22px 26px; margin: 18px 0 8px;
			box-shadow: 0 10px 30px rgba(20,25,30,.18);
		}
		.rp-hero-brand { display: flex; align-items: center; gap: 16px; }
		.rp-logo {
			width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center;
			font-weight: 800; font-size: 20px; letter-spacing: .5px; color: #fff;
			background: linear-gradient(135deg, #3fb950, #2ea043); box-shadow: inset 0 0 0 1px rgba(255,255,255,.15);
		}
		.rp-hero h1 { color: #fff; margin: 0; font-size: 22px; line-height: 1.1; padding: 0; }
		.rp-tag { margin: 4px 0 0; color: #c9d1d9; font-size: 13px; }
		.rp-hero-meta { display: flex; align-items: center; gap: 10px; }
		.rp-version { font-size: 12px; color: #c9d1d9; background: rgba(255,255,255,.08); padding: 4px 10px; border-radius: 999px; }
		.rp-pill { font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 7px; }
		.rp-pill::before { content: ""; width: 8px; height: 8px; border-radius: 50%; }
		.rp-pill.is-on { background: rgba(63,185,80,.18); color: #56d364; }
		.rp-pill.is-on::before { background: #3fb950; box-shadow: 0 0 0 3px rgba(63,185,80,.25); }
		.rp-pill.is-off { background: rgba(210,153,34,.18); color: #e3b341; }
		.rp-pill.is-off::before { background: #d29922; }

		.rp-layout { display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 22px; margin-top: 18px; align-items: start; }
		@media (max-width: 960px) { .rp-layout { grid-template-columns: 1fr; } }

		.rp-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 12px; padding: 20px 22px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
		.rp-card h2 { margin: 0 0 4px; font-size: 15px; }
		.rp-card .rp-desc { margin: 0 0 16px; color: #646970; font-size: 13px; }
		.rp-field { margin-bottom: 16px; }
		.rp-field:last-child { margin-bottom: 0; }
		.rp-field label.rp-label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; }
		.rp-field input[type=text], .rp-field input[type=password], .rp-field select { width: 100%; max-width: 460px; }
		.rp-field .description { margin-top: 5px; }
		.rp-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
		@media (max-width: 600px) { .rp-grid2 { grid-template-columns: 1fr; } }
		.rp-radio { display: block; padding: 4px 0; }

		.rp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
		.rp-actions form { display: inline; }
		.rp-last { margin-left: auto; color: #646970; font-size: 12px; }

		.rp-side .rp-card { padding: 18px 20px; }
		.rp-side h2 { font-size: 14px; }
		.rp-steps { margin: 0; padding-left: 18px; }
		.rp-steps li { margin-bottom: 8px; font-size: 13px; color: #3c434a; }
		.rp-code { background: #0d1117; color: #c9d1d9; border-radius: 10px; padding: 14px; font-size: 12px; line-height: 1.55; overflow-x: auto; white-space: pre; }
		.rp-glossary { margin: 0; }
		.rp-glossary dt { font-weight: 600; font-size: 13px; margin-top: 12px; color: #1d2327; }
		.rp-glossary dt:first-child { margin-top: 0; }
		.rp-glossary dd { margin: 3px 0 0; font-size: 12.5px; color: #646970; line-height: 1.5; }

		.rp-support { text-align: center; background: linear-gradient(160deg,#fff, #fff7f2); border-color: #ffd9cc; }
		.rp-support p { font-size: 13px; color: #50575e; margin: 4px 0 14px; }
		.rp-kofi { display: inline-flex; align-items: center; gap: 8px; background: #ff5e5b; color: #fff !important; text-decoration: none; font-weight: 700; padding: 10px 18px; border-radius: 999px; box-shadow: 0 6px 16px rgba(255,94,91,.35); }
		.rp-kofi:hover { background: #ff4744; color: #fff; }
		.rp-author { text-align: center; font-size: 12.5px; color: #646970; }
		.rp-author a { text-decoration: none; }

		.rp-log { border: 1px solid #e2e4e7; border-radius: 12px; overflow: hidden; }
		.rp-log table { border: 0; }
		.rp-level { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; padding: 2px 8px; border-radius: 999px; }
		.rp-level.success { background: #e6f4ea; color: #1a7f37; }
		.rp-level.error { background: #fce8e6; color: #b42318; }
		.rp-level.warning { background: #fff4e5; color: #9a6700; }
		.rp-level.info { background: #eef2f6; color: #475467; }
	</style>

	<header class="rp-hero">
		<div class="rp-hero-brand">
			<span class="rp-logo">RP</span>
			<div>
				<h1>RepoPress</h1>
				<p class="rp-tag"><?php esc_html_e( 'Publish WordPress content from a GitHub repository', 'repopress' ); ?></p>
			</div>
		</div>
		<div class="rp-hero-meta">
			<?php if ( $repopress_is_ready ) : ?>
				<span class="rp-pill is-on"><?php esc_html_e( 'Configured', 'repopress' ); ?></span>
			<?php else : ?>
				<span class="rp-pill is-off"><?php esc_html_e( 'Not connected', 'repopress' ); ?></span>
			<?php endif; ?>
			<span class="rp-version">v<?php echo esc_html( REPOPRESS_VERSION ); ?></span>
		</div>
	</header>

	<?php if ( '' !== $repopress_notice && isset( $repopress_notices[ $repopress_notice ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $repopress_notices[ $repopress_notice ][0] ); ?> is-dismissible">
			<p>
				<?php echo esc_html( $repopress_notices[ $repopress_notice ][1] ); ?>
				<?php if ( '' !== $repopress_detail ) : ?><code><?php echo esc_html( $repopress_detail ); ?></code><?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="rp-layout">
		<main class="rp-main">
			<form method="post" action="<?php echo $repopress_post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="repopress_save" />
				<?php wp_nonce_field( 'repopress_save' ); ?>

				<div class="rp-card">
					<h2><?php esc_html_e( 'Repository', 'repopress' ); ?></h2>
					<p class="rp-desc"><?php esc_html_e( 'Where RepoPress reads your content from. It only ever reads, never writes.', 'repopress' ); ?></p>

					<div class="rp-grid2">
						<div class="rp-field">
							<label class="rp-label" for="repopress-owner"><?php esc_html_e( 'Owner', 'repopress' ); ?></label>
							<input name="owner" id="repopress-owner" type="text" value="<?php echo esc_attr( $repopress_config['owner'] ); ?>" placeholder="octocat" />
							<p class="description"><?php esc_html_e( 'The user or organization that owns the repo.', 'repopress' ); ?></p>
						</div>
						<div class="rp-field">
							<label class="rp-label" for="repopress-repo"><?php esc_html_e( 'Repository', 'repopress' ); ?></label>
							<input name="repo" id="repopress-repo" type="text" value="<?php echo esc_attr( $repopress_config['repo'] ); ?>" placeholder="my-content" />
							<p class="description"><?php esc_html_e( 'The repository name, without the owner.', 'repopress' ); ?></p>
						</div>
						<div class="rp-field">
							<label class="rp-label" for="repopress-branch"><?php esc_html_e( 'Branch', 'repopress' ); ?></label>
							<input name="branch" id="repopress-branch" type="text" value="<?php echo esc_attr( $repopress_config['branch'] ); ?>" placeholder="main" />
						</div>
						<div class="rp-field">
							<label class="rp-label" for="repopress-path"><?php esc_html_e( 'Folder path', 'repopress' ); ?></label>
							<input name="path" id="repopress-path" type="text" value="<?php echo esc_attr( $repopress_config['path'] ); ?>" placeholder="content/posts" />
							<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the repo root.', 'repopress' ); ?></p>
						</div>
					</div>

					<div class="rp-field" style="margin-top:16px">
						<label class="rp-label" for="repopress-token"><?php esc_html_e( 'Access token', 'repopress' ); ?></label>
						<input name="token" id="repopress-token" type="password" autocomplete="new-password" value="" placeholder="<?php echo $repopress_has_token ? esc_attr__( 'Saved — leave blank to keep the current token', 'repopress' ) : 'ghp_...'; ?>" />
						<p class="description">
							<?php esc_html_e( 'Fine-grained personal access token with read-only Contents access to this repository. Stored encrypted.', 'repopress' ); ?>
							<a href="https://github.com/settings/tokens?type=beta" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create one', 'repopress' ); ?></a>
						</p>
					</div>
				</div>

				<div class="rp-card">
					<h2><?php esc_html_e( 'Content mapping', 'repopress' ); ?></h2>
					<p class="rp-desc"><?php esc_html_e( 'How each Markdown file becomes a post. A file can override these with its own front matter.', 'repopress' ); ?></p>
					<div class="rp-grid2">
						<div class="rp-field">
							<label class="rp-label" for="repopress-post-type"><?php esc_html_e( 'Post type', 'repopress' ); ?></label>
							<select name="post_type" id="repopress-post-type">
								<?php foreach ( $repopress_posttypes as $repopress_pt ) : ?>
									<option value="<?php echo esc_attr( $repopress_pt->name ); ?>" <?php selected( $repopress_config['post_type'], $repopress_pt->name ); ?>><?php echo esc_html( $repopress_pt->labels->singular_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="rp-field">
							<label class="rp-label" for="repopress-status"><?php esc_html_e( 'Default status', 'repopress' ); ?></label>
							<select name="default_status" id="repopress-status">
								<?php foreach ( $repopress_statuses as $repopress_st ) : ?>
									<option value="<?php echo esc_attr( $repopress_st ); ?>" <?php selected( $repopress_config['default_status'], $repopress_st ); ?>><?php echo esc_html( $repopress_st ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="rp-card">
					<h2><?php esc_html_e( 'Sync behavior', 'repopress' ); ?></h2>
					<p class="rp-desc"><?php esc_html_e( 'When RepoPress checks GitHub, and what to do about files you remove.', 'repopress' ); ?></p>
					<div class="rp-field">
						<label class="rp-label" for="repopress-frequency"><?php esc_html_e( 'Automatic sync', 'repopress' ); ?></label>
						<select name="frequency" id="repopress-frequency">
							<?php foreach ( $repopress_freqs as $repopress_key => $repopress_label ) : ?>
								<option value="<?php echo esc_attr( $repopress_key ); ?>" <?php selected( $repopress_config['frequency'], $repopress_key ); ?>><?php echo esc_html( $repopress_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Runs through WP-Cron. On quiet sites, cron fires when someone visits.', 'repopress' ); ?></p>
					</div>
					<div class="rp-field">
						<span class="rp-label"><?php esc_html_e( 'When a file is deleted from the repo', 'repopress' ); ?></span>
						<label class="rp-radio"><input type="radio" name="delete_behavior" value="ignore" <?php checked( $repopress_config['delete_behavior'], 'ignore' ); ?> /> <?php esc_html_e( 'Leave the post as-is (log only)', 'repopress' ); ?></label>
						<label class="rp-radio"><input type="radio" name="delete_behavior" value="trash" <?php checked( $repopress_config['delete_behavior'], 'trash' ); ?> /> <?php esc_html_e( 'Move the post to Trash', 'repopress' ); ?></label>
					</div>
				</div>

				<?php submit_button( __( 'Save settings', 'repopress' ) ); ?>
			</form>

			<div class="rp-card">
				<h2><?php esc_html_e( 'Run a sync', 'repopress' ); ?></h2>
				<p class="rp-desc"><?php esc_html_e( 'Check the connection, or pull the latest content right now.', 'repopress' ); ?></p>
				<div class="rp-actions">
					<form method="post" action="<?php echo $repopress_post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
						<input type="hidden" name="action" value="repopress_test" />
						<?php wp_nonce_field( 'repopress_test' ); ?>
						<?php submit_button( __( 'Test connection', 'repopress' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo $repopress_post_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
						<input type="hidden" name="action" value="repopress_sync_now" />
						<?php wp_nonce_field( 'repopress_sync_now' ); ?>
						<?php submit_button( __( 'Sync now', 'repopress' ), 'primary', 'submit', false ); ?>
					</form>
					<?php if ( '' !== $repopress_last_sync ) : ?>
						<span class="rp-last"><?php printf( /* translators: date/time of last sync. */ esc_html__( 'Last sync: %s', 'repopress' ), esc_html( $repopress_last_sync ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<h2 style="margin-top:26px"><?php esc_html_e( 'Activity log', 'repopress' ); ?></h2>
			<?php $repopress_entries = array_reverse( $logger->get_entries() ); ?>
			<?php if ( empty( $repopress_entries ) ) : ?>
				<div class="rp-card"><p class="rp-desc" style="margin:0"><?php esc_html_e( 'Nothing yet. Run a sync and the results will show up here.', 'repopress' ); ?></p></div>
			<?php else : ?>
				<div class="rp-log">
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width:170px"><?php esc_html_e( 'Time', 'repopress' ); ?></th>
								<th style="width:100px"><?php esc_html_e( 'Level', 'repopress' ); ?></th>
								<th><?php esc_html_e( 'Message', 'repopress' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $repopress_entries, 0, 40 ) as $repopress_e ) : ?>
								<tr>
									<td><?php echo esc_html( $repopress_e['time'] ); ?></td>
									<td><span class="rp-level <?php echo esc_attr( $repopress_e['level'] ); ?>"><?php echo esc_html( $repopress_e['level'] ); ?></span></td>
									<td><?php echo esc_html( $repopress_e['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</main>

		<aside class="rp-side">
			<div class="rp-card">
				<h2><?php esc_html_e( 'Quick start', 'repopress' ); ?></h2>
				<ol class="rp-steps">
					<li><?php esc_html_e( 'Enter your repository owner, name, and branch.', 'repopress' ); ?></li>
					<li><?php esc_html_e( 'Paste a read-only access token for that repo.', 'repopress' ); ?></li>
					<li><?php esc_html_e( 'Pick the post type and default status.', 'repopress' ); ?></li>
					<li><?php esc_html_e( 'Save, then click Test connection.', 'repopress' ); ?></li>
					<li><?php esc_html_e( 'Click Sync now, or set an automatic schedule.', 'repopress' ); ?></li>
				</ol>
			</div>

			<div class="rp-card">
				<h2><?php esc_html_e( 'File format', 'repopress' ); ?></h2>
				<p class="rp-desc" style="margin-bottom:12px"><?php esc_html_e( 'Each Markdown file starts with front matter, then the body.', 'repopress' ); ?></p>
				<div class="rp-code">---
title: "Getting Started"
slug: getting-started
status: publish
type: post
categories: [Guides]
tags: [setup, intro]
date: 2026-08-14
---

# Getting Started

Your **Markdown** body here.</div>
			</div>

			<div class="rp-card">
				<h2><?php esc_html_e( 'What things mean', 'repopress' ); ?></h2>
				<dl class="rp-glossary">
					<dt><?php esc_html_e( 'Branch', 'repopress' ); ?></dt>
					<dd><?php esc_html_e( 'The line of the repo to publish from, usually main. Content on other branches is ignored.', 'repopress' ); ?></dd>
					<dt><?php esc_html_e( 'Folder path', 'repopress' ); ?></dt>
					<dd><?php esc_html_e( 'Limit syncing to one sub-folder, so the rest of the repo is left alone.', 'repopress' ); ?></dd>
					<dt><?php esc_html_e( 'Access token', 'repopress' ); ?></dt>
					<dd><?php esc_html_e( 'A read-only key that lets RepoPress see the repo. It is stored encrypted and never shared.', 'repopress' ); ?></dd>
					<dt><?php esc_html_e( 'Front matter', 'repopress' ); ?></dt>
					<dd><?php esc_html_e( 'The block between the --- lines at the top of a file. It sets the title, status, categories, and more.', 'repopress' ); ?></dd>
					<dt><?php esc_html_e( 'Change detection', 'repopress' ); ?></dt>
					<dd><?php esc_html_e( 'RepoPress remembers each file by its Git checksum, so it only updates posts when a file actually changes.', 'repopress' ); ?></dd>
				</dl>
			</div>

			<div class="rp-card rp-support">
				<h2><?php esc_html_e( 'Enjoying RepoPress?', 'repopress' ); ?></h2>
				<p><?php esc_html_e( 'It is free and always will be. If it saves you time, a coffee keeps it going.', 'repopress' ); ?></p>
				<a class="rp-kofi" href="https://ko-fi.com/gunjanjaswal" target="_blank" rel="noopener noreferrer">
					<span aria-hidden="true">&#9829;</span> <?php esc_html_e( 'Support on Ko-fi', 'repopress' ); ?>
				</a>
			</div>

			<div class="rp-card rp-author">
				<?php esc_html_e( 'Built by', 'repopress' ); ?>
				<a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener noreferrer">Gunjan Jaswal</a>
			</div>
		</aside>
	</div>
</div>
