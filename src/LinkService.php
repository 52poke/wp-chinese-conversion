<?php

declare(strict_types=1);

namespace Wpcc;

class LinkService {
	private static function is_internal(string $link): bool {
		if ($link === '' || str_starts_with($link, '#')) {
			return false;
		}
		$home = wp_parse_url(home_url('/'));
		$url = wp_parse_url($link);
		if ($url === false || isset($url['user']) || isset($url['pass'])) {
			return false;
		}
		if (isset($url['scheme']) && !in_array(strtolower($url['scheme']), array('http', 'https'), true)) {
			return false;
		}
		if (isset($url['host']) && (strcasecmp($url['host'], $home['host']) !== 0 || ($url['port'] ?? null) !== ($home['port'] ?? null))) {
			return false;
		}
		if (!isset($url['host']) && isset($url['path']) && !str_starts_with($url['path'], '/')) {
			return false;
		}
		$path = $url['path'] ?? '/';
		$base = rtrim($home['path'] ?? '', '/');
		return $base === '' || $path === $base || str_starts_with($path, $base . '/');
	}

	public static function link_conversion($link, $variant = null) {
		$variant ??= Context::targetLang();
		if (!is_string($variant) || !preg_match('/^(?:' . Config::get_variant_regex() . ')$/D', $variant) || !self::is_internal((string) $link)) {
			return $link;
		}
		$link = self::strip_variant((string) $link);
		$options = Config::options();
		if (str_contains($link, '?') || empty($options['wpcc_use_permalink'])) {
			return add_query_arg('variant', $variant, $link);
		}
		$fragment = '';
		if (str_contains($link, '#')) {
			[$link, $fragment] = explode('#', $link, 2);
			$fragment = '#' . $fragment;
		}
		$path = wp_parse_url($link, PHP_URL_PATH) ?: '/';
		$base = rtrim(wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '', '/');
		$is_home = rtrim($path, '/') === $base;
		if ($options['wpcc_use_permalink'] === 1 || $is_home) {
			$link = user_trailingslashit(trailingslashit($link) . $variant);
			return ($is_home ? trailingslashit($link) : $link) . $fragment;
		}
		$prefix = substr($link, 0, strlen($link) - strlen($path));
		$path = $base . '/' . $variant . '/' . ltrim(substr($path, strlen($base)), '/');
		return $prefix . $path . $fragment;
	}

	public static function link_conversion_auto($link, $variant = null) {
		if (!Context::targetLang() || Context::directConversionFlag()) {
			return $link;
		}
		return self::link_conversion($link, $variant);
	}

	public static function strip_variant(string $link): string {
		if (!self::is_internal($link)) {
			return $link;
		}
		$link = remove_query_arg('variant', $link);
		if (empty(Config::options()['wpcc_use_permalink'])) {
			return $link;
		}
		$path = wp_parse_url($link, PHP_URL_PATH) ?: '/';
		$base = rtrim(wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '', '/');
		$relative = substr($path, strlen($base));
		$reg = Config::get_variant_regex();
		if (Config::options()['wpcc_use_permalink'] === 2) {
			$relative = preg_replace('#^/(?:' . $reg . ')(?=/|$)#', '', $relative, 1);
		} else {
			$relative = preg_replace('#^(.*)/(?:' . $reg . ')(?=/|$)(.*)$#', '$1$2', $relative, 1);
		}
		$new_path = $base . ($relative === '' ? '/' : $relative);
		if ($new_path !== $path) {
			$new_path = $relative === '' ? trailingslashit($new_path) : user_trailingslashit($new_path);
			$position = strpos($link, $path, str_contains($link, '://') ? strpos($link, '://') + 3 : 0);
			$link = substr_replace($link, $new_path, $position, strlen($path));
		}
		return $link;
	}

	public static function get_noconversion_url() {
		$uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
		$uri = '/' . ltrim($uri, '/');
		$home = wp_parse_url(home_url('/'));
		$origin = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '');
		return self::strip_variant($origin . $uri);
	}

	public static function pagenum_link_fix($link) {
		global $paged;
		$options = Config::options();
		$target_lang = Context::targetLang();
		if ($options['wpcc_use_permalink'] != 1) {
			return $link;
		}
		if (!$target_lang) {
			return $link;
		}

		if (preg_match('/^(.*)\/page\/\d+\/' . $target_lang . '\/page\/(\d+)\/?$/', $link, $tmp) ||
			preg_match('/^(.*)\/' . $target_lang . '\/page\/(\d+)\/?$/', $link, $tmp)) {
			return user_trailingslashit($tmp[1] . '/page/' . $tmp[2] . '/' . $target_lang);
		} else if (preg_match('/^(.*)\/page\/(\d+)\/' . $target_lang . '\/?$/', $link, $tmp) && $tmp[2] == 2 && $paged == 2) {
			if ($tmp[1] == untrailingslashit(home_url(''))) {
				return $tmp[1] . '/' . $target_lang . '/';
			}
			return user_trailingslashit($tmp[1] . '/' . $target_lang);
		}
		return $link;
	}

	public static function fix_link_conversion($link) {
		$mode = Config::options()['wpcc_use_permalink'];
		$reg = Config::get_variant_regex();
		$path = wp_parse_url($link, PHP_URL_PATH) ?: '';
		if ($mode === 1 && preg_match('#/(' . $reg . ')(?=/|$)#', $path, $match)) {
			return self::link_conversion(self::strip_variant($link), $match[1]);
		}
		if ($mode === 0 && preg_match('#^(.*)\?variant=([-a-zA-Z]+)/(.*)$#', $link, $match)) {
			return add_query_arg('variant', $match[2], trailingslashit($match[1]) . $match[3]);
		}
		return $link;
	}

	public static function cancel_link_conversion($link) {
		return self::strip_variant((string) $link);
	}

	public static function cancel_incorrect_redirect($redirect_to, $redirect_from) {
		if (!$redirect_to || !Context::targetLang()) {
			return $redirect_to;
		}
		$converted = self::link_conversion($redirect_to);
		return $converted === $redirect_from ? false : $converted;
	}

	public static function rewrite_rules($rules) {
		$options = Config::options();
		if (empty($options['wpcc_use_permalink']) || empty($options['wpcc_used_langs'])) {
			return $rules;
		}
		$reg = Config::get_variant_regex();
		$converted = array('^(' . $reg . ')/?$' => 'index.php?variant=$matches[1]');
		foreach ($rules as $pattern => $query) {
			if (str_contains($pattern, 'print') || str_contains($query, 'lang=') || str_contains($query, 'variant=') || !str_ends_with($pattern, '$')) {
				continue;
			}
			if ($options['wpcc_use_permalink'] === 1) {
				preg_match("\x01(?:" . $pattern . ")|\x01", '', $captures, PREG_UNMATCHED_AS_NULL);
				$number = count($captures);
				$stem = str_ends_with($pattern, '/?$') ? substr($pattern, 0, -3) : rtrim(substr($pattern, 0, -1), '/');
				$converted[$stem . '/(' . $reg . ')/?$'] = $query . '&variant=$matches[' . $number . ']';
			} else {
				$converted['^(' . $reg . ')/' . ltrim($pattern, '^')] = preg_replace_callback('/\\$matches\\[(\\d+)\\]/', array(self::class, 'permalink_preg_callback'), $query) . '&variant=$matches[1]';
			}
		}
		return $converted + $rules;
	}

	public static function permalink_preg_callback($matches) {
		return '$matches[' . ((int) $matches[1] + 1) . ']';
	}
}
