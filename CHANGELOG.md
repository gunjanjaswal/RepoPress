# Changelog

All notable changes to RepoPress are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet. Planned work is tracked on the [roadmap](README.md#roadmap).

## [0.1.0] - 2026-09-02

First public release.

### Added

- One-directional sync that pulls Markdown files from a GitHub repository and publishes them as WordPress posts.
- YAML front matter mapping for title, slug, status, post type, categories, tags, excerpt, and date.
- Markdown to HTML conversion via a bundled Parsedown parser, with a `wpautop` fallback.
- Per-file change detection using the Git blob checksum, so unchanged files are skipped.
- Personal access token authentication, with the token stored encrypted at rest.
- Manual sync plus scheduled sync through WP-Cron (every 15 minutes, hourly, twice daily, or daily).
- Connection test against the GitHub API before syncing.
- Configurable behavior when a source file is deleted: leave the post in place, or move it to Trash.
- Activity log on the settings screen.
- Support and author links on the plugin's row on the Plugins page.

[Unreleased]: https://github.com/gunjanjaswal/RepoPress/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/gunjanjaswal/RepoPress/releases/tag/v0.1.0
