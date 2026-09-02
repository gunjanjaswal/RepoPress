# Contributing to RepoPress

Thanks for taking the time to help. This document covers local setup, coding
standards, how versioning works, and how a release gets published to the
WordPress plugin directory.

## Local setup

Clone into a WordPress install's plugins folder:

```bash
git clone https://github.com/gunjanjaswal/RepoPress.git wp-content/plugins/repopress
```

RepoPress ships with a bundled copy of Parsedown in `includes/lib/`, so it runs
without any build step. If you would rather manage it with Composer:

```bash
composer install
```

When `vendor/autoload.php` is present it is used first; otherwise the bundled
file is loaded.

## Project layout

```
repopress/
├── repopress.php              Main file: header, constants, bootstrap
├── uninstall.php              Removes options on uninstall (keeps your posts)
├── readme.txt                 WordPress.org readme
├── includes/
│   ├── class-plugin.php       Orchestrator, cron scheduling
│   ├── class-settings.php     Config and encrypted token storage
│   ├── class-github-client.php  GitHub REST client
│   ├── class-content-parser.php Front matter and Markdown parsing
│   ├── class-sync-engine.php  The pull loop and change detection
│   ├── class-logger.php       Activity log
│   └── lib/Parsedown.php      Bundled Markdown parser (MIT)
└── admin/
    ├── class-admin-page.php   Settings screen and handlers
    └── views/settings.php     The form and activity table
```

## Coding standards

- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).
- Escape on output, sanitize on input, and use nonces plus capability checks for every action.
- Keep changes surgical. Touch what the change needs and match the surrounding style.

Lint everything before you push:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

If you have PHP_CodeSniffer with the WordPress standard installed:

```bash
phpcs --standard=WordPress .
```

## Versioning

RepoPress uses [Semantic Versioning](https://semver.org): `MAJOR.MINOR.PATCH`.

- **PATCH** for backward-compatible fixes.
- **MINOR** for backward-compatible features.
- **MAJOR** for breaking changes.

The version number lives in four places and they must match on every release:

| Location | Field |
| --- | --- |
| `repopress.php` | `Version:` header |
| `repopress.php` | `REPOPRESS_VERSION` constant |
| `readme.txt` | `Stable tag:` |
| `CHANGELOG.md` | The new version heading |

## Release process

1. Update the four version locations above.
2. Move the relevant notes from `Unreleased` into a dated version section in `CHANGELOG.md`, and mirror them in the `readme.txt` changelog.
3. Commit the bump, for example `Release 0.2.0`.
4. Tag it and push the tag:

   ```bash
   git tag v0.2.0
   git push origin main --tags
   ```

5. Create a GitHub release from that tag. Publishing the release triggers the deploy workflow.

## Deployment

Deployment to the WordPress plugin directory is automated with GitHub Actions in
[`.github/workflows/deploy.yml`](.github/workflows/deploy.yml). When a GitHub
release is published, the workflow builds the plugin (respecting
[`.distignore`](.distignore)) and commits it to the WordPress.org SVN
repository, then attaches a zip to the release.

Two repository secrets are required:

| Secret | Value |
| --- | --- |
| `SVN_USERNAME` | Your WordPress.org username |
| `SVN_PASSWORD` | Your WordPress.org password |

Add them under **Settings → Secrets and variables → Actions** on GitHub.

The `.distignore` file keeps development-only files (this guide, workflows, git
metadata) out of what ships to users. Runtime files, including the bundled
Parsedown, are always included.

## Reporting issues

Open an issue at
[github.com/gunjanjaswal/RepoPress/issues](https://github.com/gunjanjaswal/RepoPress/issues).
A sample Markdown file and your plugin settings make bugs much faster to track down.
