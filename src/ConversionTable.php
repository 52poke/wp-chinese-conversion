<?php

declare(strict_types=1);

namespace Wpcc;

class ConversionTable {
	public static function load(): void {
		global $zh2Hans;
		if (!is_array($zh2Hans) || empty($zh2Hans)) {
			global $zh2Hant;
			require_once dirname(__DIR__) . '/ZhConversion.php';
			if (file_exists(WP_CONTENT_DIR . '/extra_zhconversion.php')) {
				require_once WP_CONTENT_DIR . '/extra_zhconversion.php';
			}
		}
	}
}
