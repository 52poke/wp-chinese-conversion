<?php

declare(strict_types=1);

namespace Wpcc;

class Context {
	private static string|false $noconversionUrl = false;
	private static string|false $redirectTo = false;
	private static bool $directConversionFlag = false;
	private static array $langsUrls = array();
	private static string|false $targetLang = false;

	public static function initDefaults(): void {
		self::$noconversionUrl = false;
		self::$redirectTo = false;
		self::$directConversionFlag = false;
		self::$langsUrls = array();
		self::$targetLang = false;
	}

	public static function targetLang(): string|false {
		return self::$targetLang;
	}

	public static function setTargetLang($lang): void {
		self::$targetLang = is_string($lang) && isset(Config::langs()[$lang]) ? $lang : false;
	}

	public static function noconversionUrl(): string|false {
		return self::$noconversionUrl;
	}

	public static function setNoconversionUrl($url): void {
		self::$noconversionUrl = $url;
	}

	public static function redirectTo(): string|false {
		return self::$redirectTo;
	}

	public static function setRedirectTo($value): void {
		self::$redirectTo = is_string($value) && isset(Config::langs()[$value]) ? $value : false;
	}

	public static function directConversionFlag(): bool {
		return self::$directConversionFlag;
	}

	public static function setDirectConversionFlag($value): void {
		self::$directConversionFlag = (bool) $value;
	}

	public static function langsUrls(): array {
		return self::$langsUrls;
	}

	public static function setLangsUrls(array $value): void {
		self::$langsUrls = $value;
	}
}
