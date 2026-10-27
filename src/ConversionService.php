<?php

declare(strict_types=1);

namespace Wpcc;

class ConversionService {
	private static bool $loaded = false;

	public static function zhconversion($str, $variant = null): string {
		$variant ??= Context::targetLang();
		return is_string($variant) ? self::convert((string) $str, $variant) : (string) $str;
	}

	public static function zhconversion2($str, $variant = null): string {
		$variant ??= Context::targetLang();
		if (!is_string($variant) || !isset(Config::langs()[$variant])) {
			return (string) $str;
		}
		return self::limit_zhconversion((string) $str, static function(string $text) use ($variant): string {
			return self::zhconversion($text, $variant);
		});
	}

	public static function wpcc_no_conversion_filter($str): string {
		$options = Config::options();
		if (empty($options['wpcc_no_conversion_ja']) && empty($options['wpcc_no_conversion_tag'])) {
			return (string) $str;
		}
		$tags = array_filter(explode(',', $options['wpcc_no_conversion_tag']));
		return HtmlConverter::protect((string) $str, $tags, !empty($options['wpcc_no_conversion_ja']));
	}

	public static function zhconversion_safe($str, $variant = null): string {
		return self::zhconversion($str, $variant);
	}

	public static function zhconversion_all($str, array $langs = array('zh-hans', 'zh-hant')): array {
		$result = array();
		foreach ($langs as $variant) {
			if (!is_string($variant) || !isset(Config::langs()[$variant])) {
				continue;
			}
			$converted = self::zhconversion($str, $variant);
			if ($converted !== $str) {
				$result[] = $converted;
			}
		}
		return array_values(array_unique($result));
	}

	public static function zhconversion_deep($value) {
		if (is_array($value)) {
			return array_map(array(self::class, 'zhconversion_deep'), $value);
		}
		return is_string($value) ? self::zhconversion($value) : $value;
	}

	public static function limit_zhconversion($str, callable $function): string {
		preg_match_all('/<!--WPCC_NC(\d*)_(START|END)-->/', $str, $matches, PREG_OFFSET_CAPTURE);
		$result = '';
		$offset = 0;
		$stack = array();
		foreach ($matches[0] as $i => [$marker, $position]) {
			$text = substr($str, $offset, $position - $offset);
			$result .= empty($stack) ? $function($text) : $text;
			$id = $matches[1][$i][0];
			if ($matches[2][$i][0] === 'START') {
				$stack[] = $id;
			} elseif ($stack && end($stack) === $id) {
				array_pop($stack);
			} else {
				$result .= $marker;
			}
			$offset = $position + strlen($marker);
		}
		$text = substr($str, $offset);
		return $result . (empty($stack) ? $function($text) : $text);
	}

	public static function load(): void {
		if (self::$loaded) {
			return;
		}
		global $zh2Hans, $zh2Hant;
		require_once dirname(__DIR__) . '/ZhConversion.php';
		if (defined('WP_CONTENT_DIR') && is_file(WP_CONTENT_DIR . '/extra_zhconversion.php')) {
			require_once WP_CONTENT_DIR . '/extra_zhconversion.php';
		}
		self::$loaded = true;
	}

	public static function convert(string $text, string $variant): string {
		if (!isset(Config::langs()[$variant])) {
			return $text;
		}
		self::load();
		global $zh2Hans, $zh2Hant;
		$table = $variant === 'zh-hans' ? $zh2Hans : $zh2Hant;
		return is_array($table) ? strtr($text, $table) : $text;
	}
}
