# Markdown parser

The content parser uses [Parsedown](https://github.com/erusev/parsedown) (MIT,
GPL-compatible) when it is available, and falls back to a basic `wpautop`
conversion when it is not.

For a release build, add Parsedown one of two ways:

**Composer (recommended)**

```bash
composer require erusev/parsedown
```

This creates `vendor/autoload.php`, which the plugin loads automatically.

**Single file**

Drop `Parsedown.php` into this folder and require it from the main plugin file,
or commit the whole `vendor/` directory. WordPress.org requires the source of
any bundled library to be available, so keep the original, unminified file.

The plugin runs without Parsedown, but Markdown rendering is limited until it is
present.
