<?php

declare(strict_types=1);

namespace Wpcc;

class Config {
	public static function langs(): array {
		return array(
			'zh-hans' => array('zhconversion_hans', 'hanstip', '简体中文', 'zh-Hans'),
			'zh-hant' => array('zhconversion_hant', 'hanttip', '繁體中文', 'zh-Hant'),
		);
	}

	public static function defaults(): array {
		return array(
			'wpcc_search_conversion' => 1,
			'wpcc_used_langs' => array_keys(self::langs()),
			'wpcc_browser_redirect' => 0,
			'wpcc_auto_language_recong' => 0,
			'wpcc_flag_option' => 1,
			'wpcc_use_cookie_variant' => 0,
			'wpcc_use_fullpage_conversion' => 1,
			'wpcc_use_permalink' => 0,
			'wpcc_no_conversion_tag' => '',
			'wpcc_no_conversion_ja' => 0,
			'wpcc_no_conversion_qtag' => 0,
			'wpcc_engine' => 'mediawiki',
			'nctip' => '',
		);
	}
}
