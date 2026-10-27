# WP Chinese Conversion (Modern Fork)

Originally developed by oogami.name. This fork is maintained by **mudkipme**.

Server-side conversion between Simplified Chinese (`zh-hans`) and Traditional Chinese (`zh-hant`) using MediaWiki conversion tables.

## Requirements

- WordPress 6.9+
- PHP 8.3+

## Installation

1. Upload this folder to `wp-content/plugins/wp-chinese-conversion`.
2. Activate **WP Chinese Conversion**.
3. Configure it under **Settings → Chinese Conversion**.
4. Add the Chinese Conversion widget or call `wpcc_output_navi()` in a theme template.

Variants can use query parameters, a permalink suffix, or a permalink prefix. Browser language detection recognizes script and regional codes, including `zh-CN`, `zh-TW`, and `zh-HK`. It and remembered preferences can redirect visitors or convert the current URL directly. Personalized responses send cache prevention headers.

Search conversion expands Simplified and Traditional forms while preserving WordPress search terms, exclusions, exact matching, selected columns, and password restrictions.

HTML conversion translates displayed text and attributes such as `title`, `alt`, and `aria-label`. URLs, element identifiers, data attributes, JavaScript, and CSS retain their original values. Feed content uses filters rather than full-page HTML buffering. REST endpoints, XML-RPC, AJAX, cron, robots, favicons, and sitemaps skip page conversion.

## Excluding content

Use `<!--WPCC_NC_START--> ... <!--WPCC_NC_END-->` to preserve content. Nested exclusion blocks are supported.

Settings can also exclude article elements by tag name, such as `pre,code`, or by Japanese language attributes (`lang="ja"` and regional forms such as `ja-JP`). Tag names are supported; CSS selectors are not. Excluded markup is preserved without DOM reserialization.

Uncheck both variants to disable conversion. Deactivation removes variant rewrite rules and preserves settings. The settings page also provides an explicit settings deletion action.

## Theme functions

Existing helpers remain available, including:

```php
zhconversion('漢語', 'zh-hans');
zhconversion2('汉语 <!--WPCC_NC_START-->汉语<!--WPCC_NC_END-->', 'zh-hant');
wpcc_link_conversion(get_permalink(), 'zh-hant');
wpcc_output_navi();
$navigation = wpcc_output_navi('echo=0');
```

`zhconversion()` converts a string directly. `zhconversion2()` respects exclusion markers. The frontend pipeline uses HTML-aware conversion to preserve machine-readable attributes. Both navigation modes respect enabled variants and `echo=0`.

The conversion tables load only when conversion is needed. Existing custom mappings in `wp-content/extra_zhconversion.php` are still supported through `$zh2Hans` and `$zh2Hant`.

## Diagnostics

Add this to `wp-config.php` before WordPress loads:

```php
define('WPCC_DEBUG', true);
```

Diagnostics appear in an HTML comment in the frontend footer only for users who can manage options. Additional data can be supplied using `\Wpcc\Diagnostics::set_debug_data(array(...))` after the plugin loads. `WP_DEBUG` alone does not enable plugin diagnostics.
