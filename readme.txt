=== RepoPress ===
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: github, markdown, sync, content, deployment
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish WordPress content straight from a GitHub repository. Write Markdown with front matter, and your posts stay in sync from the branch you pick.

== Description ==

RepoPress lets you keep your content in a GitHub repository and have it appear as WordPress posts. Point the plugin at a repo, a branch, and a folder, and every Markdown file there becomes a post. Edit the file in GitHub, and the post updates on your next sync.

It is built for people who like writing in Markdown, want their content under version control, or manage docs and changelogs in a repo and need them mirrored onto a WordPress site.

**How it works**

* You add a repository owner, name, branch, and an optional sub-folder.
* You provide a fine-grained personal access token with read-only access to that repo.
* Each Markdown file is read, its YAML front matter maps to the post title, slug, status, type, date, categories, and tags, and the body is converted to HTML.
* The plugin tracks each file by its Git blob checksum, so unchanged files are skipped and only real changes trigger an update.

**One-directional by design**

Your repository is the source of truth. WordPress is the place it gets published. The plugin only reads from GitHub, it never writes back, so there is nothing to reconcile and no risk of the plugin changing your repo.

**Example file**

`
---
title: "Getting Started"
slug: getting-started
status: publish
type: post
categories: [Guides]
tags: [setup, intro]
date: 2026-08-14
---

# Getting Started

Your **Markdown** body goes here.
`

== External services ==

This plugin connects to the GitHub REST API (https://api.github.com) to read files from the repository you configure. It sends requests only after you enter a repository and an access token and only to fetch the repository details, its file tree, and the contents of Markdown files in it. No request is made until you save a configuration, and nothing is sent anywhere else.

* GitHub Terms of Service: https://docs.github.com/site-policy/github-terms/github-terms-of-service
* GitHub Privacy Statement: https://docs.github.com/site-policy/privacy-policies/github-privacy-statement

The plugin does not collect analytics, does not phone home, and stores your token encrypted in your own database.

== Installation ==

1. Upload the `repopress` folder to `/wp-content/plugins/`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin.
3. Go to Tools > GitHub Sync.
4. Enter the repository owner, name, branch, and optional folder path.
5. Paste a fine-grained personal access token with read-only Contents permission for that repository.
6. Use Test connection to confirm access, then Sync now, or set an automatic schedule.

== Frequently Asked Questions ==

= Does it write my WordPress posts back to GitHub? =

No. Version 0.1 is read-only from GitHub to WordPress. Your repository is never modified.

= What token does it need? =

A fine-grained personal access token scoped to the single repository, with read-only access to Contents. That is the least access the plugin can work with.

= What happens when I delete a file from the repo? =

By default the matching post is left untouched and the event is logged. You can switch to moving that post to Trash in the settings.

= Does it work with page builders? =

Content is stored as HTML in the post body, which any theme and the block editor read. Builders that store their own layout data, such as Elementor or WPBakery, own the editing surface for a page, so synced HTML shows as standard content rather than editable builder modules.

= Where is development happening? =

Source code and issues live on GitHub at https://github.com/gunjanjaswal/RepoPress.

== Screenshots ==

1. The settings screen under Tools > GitHub Sync.
2. The activity log after a sync.

== Changelog ==

= 0.1.0 =
* First release: one-directional pull from GitHub, Markdown with YAML front matter, manual and scheduled sync, per-file change detection, connection test, and an activity log.

== Upgrade Notice ==

= 0.1.0 =
First release.
