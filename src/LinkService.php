<?php

declare(strict_types=1);

namespace Wpcc;

class LinkService {
	public static function link_conversion($link, $variant = null) {
		$options = State::options();

		static $wpcc_wp_homepath;
		if (empty($wpcc_wp_homepath)) {
			$home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
			$wpcc_wp_homepath = trailingslashit($home_path ? $home_path : '/');
		}

		if ($variant === null) {
			$variant = Context::targetLang();
		}
		if ($variant === false || $variant === null) {
			return $link;
		}

		if (strpos($link, '?') !== false || empty($options['wpcc_use_permalink'])) {
			return add_query_arg('variant', $variant, $link);
		}
		if ($options['wpcc_use_permalink'] == 1) {
			return user_trailingslashit(trailingslashit($link) . $variant);
		}
		return preg_replace('#^(http(s?)://[^/]+' . $wpcc_wp_homepath . ')#', '\\1' . $variant . '/', $link);
	}

	public static function link_conversion_auto($link, $variant = null) {
		$options = State::options();
		$target_lang = Context::targetLang();
		$direct_flag = Context::directConversionFlag();

		if ($link == home_url('')) {
			$link .= '/';
		}
		if (!$target_lang || $direct_flag) {
			return $link;
		}
		if ($link == home_url('/') && !empty($options['wpcc_use_permalink'])) {
			return trailingslashit(self::link_conversion($link, $variant));
		}
		return self::link_conversion($link, $variant);
	}

	public static function get_noconversion_url() {
		$options = State::options();
		$reg = implode('|', $options['wpcc_used_langs']);
		$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
		$tmp = home_url($request_uri);
		$tmp = trim(strtolower(remove_query_arg('variant', $tmp)));

		if (preg_match('/^(.*)\/(' . $reg . '|zh|zh-reset)(\/.*)?$/', $tmp, $matches)) {
			$tmp = user_trailingslashit(trailingslashit($matches[1]) . ltrim($matches[3], '/'));
			if ($tmp == untrailingslashit(home_url(''))) {
				$tmp .= '/';
			}
		}
		return $tmp;
	}

	public static function pagenum_link_fix($link) {
		global $paged;
		$options = State::options();
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
		$options = State::options();
		if ($options['wpcc_use_permalink'] == 1) {
			if ($flag = strstr($link, '#')) {
				$link = substr($link, 0, -strlen($flag));
			}
			$reg = wpcc_get_variant_regex(true);
			if (preg_match('/^(.*\/)(' . $reg . ')\/(.+)$/', $link, $tmp)) {
				return user_trailingslashit($tmp[1] . trailingslashit($tmp[3]) . $tmp[2]) . $flag;
			}
			return $link . $flag;
		} else if ($options['wpcc_use_permalink'] == 0) {
			if (preg_match('/^(.*)\?variant=([-a-zA-Z]+)\/(.*)$/', $link, $tmp)) {
				return add_query_arg('variant', $tmp[2], trailingslashit($tmp[1]) . $tmp[3]);
			}
			return $link;
		}
		return $link;
	}

	public static function cancel_link_conversion($link) {
		$options = State::options();
		if ($options['wpcc_use_permalink']) {
			$reg = wpcc_get_variant_regex(true);
			if (preg_match('/^(.*\/)(' . $reg . ')\/(.+)$/', $link, $tmp)) {
				return $tmp[1] . $tmp[3];
			}
			return $link;
		}

		if (preg_match('/^(.*)\?variant=[-a-zA-Z]+\/(.*)$/', $link, $tmp)) {
			return trailingslashit($tmp[1]) . $tmp[2];
		}
		return $link;
	}

	public static function cancel_incorrect_redirect($redirect_to, $redirect_from) {
		global $wp_rewrite;
		$reg = wpcc_get_variant_regex(true);
		if (preg_match('/^.*\/(' . $reg . ')\/?.+$/', $redirect_to)) {
			if (($wp_rewrite->use_trailing_slashes && substr($redirect_from, -1) != '/') ||
				(!$wp_rewrite->use_trailing_slashes && substr($redirect_from, -1) == '/')) {
				return user_trailingslashit($redirect_from);
			}
			return false;
		}
		return $redirect_to;
	}

	public static function rewrite_rules($rules) {
		$options = State::options();
		$reg = implode('|', $options['wpcc_used_langs']);
		$rules2 = array();
		if ($options['wpcc_use_permalink'] == 1) {
			foreach ($rules as $key => $value) {
				if (strpos($key, 'print') !== false || strpos($value, 'lang=') !== false) {
					continue;
				}
				if (substr($key, -3) == '/?$') {
					if (!preg_match_all('/\\$matches\\[(\\d+)\\]/', $value, $matches, PREG_PATTERN_ORDER)) {
						continue;
					}
					$number = count($matches[0]) + 1;
					$rules2[substr($key, 0, -3) . '/(' . $reg . '|zh|zh-reset)/?$'] = $value . '&variant=$matches[' . $number . ']';
				}
			}
		} else {
			foreach ($rules as $key => $value) {
				if (strpos($key, 'print') !== false || strpos($value, 'lang=') !== false) {
					continue;
				}
				if (substr($key, -3) == '/?$') {
					$rules2['(' . $reg . '|zh|zh-reset)/' . $key] = preg_replace_callback('/\\$matches\\[(\\d+)\\]/', array(self::class, 'permalink_preg_callback'), $value) . '&variant=$matches[1]';
				}
			}
		}
		$rules2['^(' . $reg . '|zh|zh-reset)/?$'] = 'index.php?variant=$matches[1]';
		return array_merge($rules2, $rules);
	}

	public static function permalink_preg_callback($matches) {
		return '$matches[' . (intval($matches[1]) + 1) . ']';
	}
}
