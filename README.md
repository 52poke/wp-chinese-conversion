# WP Chinese Conversion (Modern Fork)

Originally developed by oogami.name. This fork is updated and maintained by **mudkipme**.

## Overview
This plugin adds server-side conversion between Chinese Simplified (zh-hans) and Chinese Traditional (zh-hant) for WordPress.

## Features
- Server-side conversion using MediaWiki conversion tables.
- URL variants supported via query string or permalink variant segment.
- Optional browser language detection and cookie-based preference storage.
- Optional search keyword conversion across Simplified/Traditional.
- Optional exclusion from conversion for:
  - `lang="ja"` elements
  - specific HTML tag names
  - `<!--WPCC_NC_START--> ... <!--WPCC_NC_END-->` blocks

## Compatibility
- WordPress: 6.9+
- PHP: 8.3+

## Installation
1. Upload the plugin folder to `wp-content/plugins/wp-chinese-conversion`.
2. Activate **WP Chinese Conversion** in WordPress admin.
3. (Optional) Add the widget to your sidebar or call `wpcc_output_navi()` in a theme template.

## Debug
To enable debug output in the frontend footer, add the following line to `wp-chinese-conversion.php` right after `\Wpcc\Plugin::bootstrap();`:

```php
define('WPCC_DEBUG', true);
\Wpcc\Diagnostics::set_debug_data(array());
```

## Notes
- This fork only supports **zh-hans** and **zh-hant**.
- The "do not convert" HTML tag option supports **tag names only** (no CSS selectors).
