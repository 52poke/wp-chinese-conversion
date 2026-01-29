<?php

declare(strict_types=1);

namespace Wpcc;

class RequestHelpers {
	public static function get_prefered_language($accept_languages, $target_langs, $flag = 0) {
		$langs = array();
		preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\\s*(;\\s*q\\s*=\\s*(1|0\\.[0-9]+))?/i', $accept_languages, $lang_parse);

		if (count($lang_parse[1])) {
			$langs = array_combine($lang_parse[1], $lang_parse[4]);
			foreach ($langs as $lang => $val) {
				if ($val === '') {
					$langs[$lang] = '1';
				}
			}
			arsort($langs, SORT_NUMERIC);
			$langs = array_keys($langs);
			$langs = array_map('strtolower', $langs);

			foreach ($langs as $val) {
				if (in_array($val, $target_langs, true)) {
					return $val;
				}
			}

			if ($flag) {
				$hans_aliases = array('zh-hans');
				$hant_aliases = array('zh-hant');
				if (in_array('zh-hans', $target_langs, true) && array_intersect($hans_aliases, $langs)) {
					return 'zh-hans';
				}
				if (in_array('zh-hant', $target_langs, true) && array_intersect($hant_aliases, $langs)) {
					return 'zh-hant';
				}
			}
			return false;
		}
		return false;
	}

	public static function is_robot(): bool {
		if (empty($_SERVER['HTTP_USER_AGENT'])) {
			return true;
		}
		$ua = (string) $_SERVER['HTTP_USER_AGENT'];

		$robots = array(
			'bot',
			'spider',
			'crawler',
			'dig',
			'search',
			'find'
		);

		foreach ($robots as $val) {
			if (stripos($ua, $val) !== false) {
				return true;
			}
		}

		$browsers = array(
			'compatible; MSIE',
			'UP.Browser',
			'Mozilla',
			'Opera',
			'NSPlayer',
			'Avant Browser',
			'Chrome',
			'Gecko',
			'Safari',
			'Lynx',
		);

		foreach ($browsers as $val) {
			if (stripos($ua, $val) !== false) {
				return false;
			}
		}

		return true;
	}
}
