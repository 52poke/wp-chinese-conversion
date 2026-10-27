<?php

declare(strict_types=1);

namespace Wpcc;

class Config {
	private static array $options = array();

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
			'hanstip' => '',
			'hanttip' => '',
		);
	}

	public static function normalize($options): array {
		$defaults = self::defaults();
		$options = is_array($options) ? array_intersect_key($options, $defaults) + $defaults : $defaults;
		foreach ($defaults as $key => $default) {
			if (is_int($default)) {
				$max = in_array($key, array('wpcc_search_conversion', 'wpcc_browser_redirect', 'wpcc_use_cookie_variant', 'wpcc_use_permalink'), true) ? 2 : 1;
				$value = is_bool($options[$key]) ? (int) $options[$key] : filter_var($options[$key], FILTER_VALIDATE_INT);
				$options[$key] = $value !== false && $value >= 0 && $value <= $max ? $value : $default;
			} elseif (is_string($default)) {
				$options[$key] = is_string($options[$key]) ? sanitize_text_field($options[$key]) : $default;
			}
		}
		$options['wpcc_used_langs'] = array_values(array_intersect(
			array_keys(self::langs()),
			is_array($options['wpcc_used_langs']) ? array_filter($options['wpcc_used_langs'], 'is_string') : $defaults['wpcc_used_langs']
		));
		$tags = preg_split('/[,|]/', strtolower($options['wpcc_no_conversion_tag']));
		$tags = array_filter(array_map('trim', $tags), static function($tag) {
			return preg_match('/^[a-z][a-z0-9-]*$/D', $tag);
		});
		$options['wpcc_no_conversion_tag'] = implode(',', array_unique($tags));
		$options['wpcc_engine'] = 'mediawiki';
		return $options;
	}

	public static function options(): array {
		return self::$options;
	}

	public static function setOptions(array $options): void {
		self::$options = $options;
	}

	public static function get_variant_regex($include_controls = true): string {
		$options = self::options();
		$langs = array_keys(self::langs());
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
