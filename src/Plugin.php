<?php

declare(strict_types=1);

namespace Wpcc;

class Plugin {
	public static function bootstrap(): void {
		require_once __DIR__ . '/Config.php';
		require_once __DIR__ . '/State.php';
		require_once __DIR__ . '/Context.php';
		require_once __DIR__ . '/ConversionTable.php';
		require_once __DIR__ . '/ConversionService.php';
		require_once __DIR__ . '/ConversionPipeline.php';
		require_once __DIR__ . '/LinkService.php';
		require_once __DIR__ . '/RequestService.php';
		require_once __DIR__ . '/View.php';
		require_once __DIR__ . '/Diagnostics.php';
		require_once __DIR__ . '/FrontendService.php';
		require_once __DIR__ . '/LocaleService.php';
		require_once __DIR__ . '/RequestHelpers.php';
		require_once __DIR__ . '/Utils.php';
		require_once __DIR__ . '/SearchService.php';
		require_once __DIR__ . '/Admin.php';
		require_once __DIR__ . '/Conversion.php';
	}

	public static function register(): void {
		Conversion::register();
		Admin::register();
	}
}
