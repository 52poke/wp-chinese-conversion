<?php

declare(strict_types=1);

namespace Wpcc;

class Diagnostics {
	private static array $debugData = array();

	public static function set_debug_data(array $data): void {
		self::$debugData = $data;
	}

	public static function debug_output(): void {
		if (!defined('WPCC_DEBUG') || WPCC_DEBUG !== true || !current_user_can('manage_options')) {
			return;
		}
		$data = array(
			'original_url' => Context::noconversionUrl(),
			'variant' => Context::targetLang(),
			'variant_urls' => Context::langsUrls(),
			'data' => self::$debugData,
		);
		$output = wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		echo "\n<!-- WPCC " . str_replace('--', '\\u002d\\u002d', $output ?: '{}') . " -->\n";
	}
}
