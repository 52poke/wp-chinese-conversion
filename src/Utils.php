<?php

declare(strict_types=1);

namespace Wpcc;

class Utils {
	public static function set_cookie($name, $value, $expires): void {
		$args = array(
			'expires' => $expires,
			'path' => COOKIEPATH,
			'domain' => COOKIE_DOMAIN,
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		setcookie($name, $value, $args);
	}

	public static function get_variant_regex($include_controls = true): string {
		$options = State::options();
		$langs = array_keys(State::langs());
		if (is_array($options) && !empty($options['wpcc_used_langs'])) {
			$langs = array_values(array_intersect($langs, (array) $options['wpcc_used_langs']));
		}
		if ($include_controls) {
			$langs[] = 'zh';
			$langs[] = 'zh-reset';
		}
		$langs = array_map('preg_quote', $langs);
		return implode('|', $langs);
	}
}
