<?php

declare(strict_types=1);

namespace Wpcc;

class Context {
	private static $noconversionUrl = false;
	private static $redirectTo = false;
	private static $directConversionFlag = false;
	private static $langsUrls = array();
	private static $targetLang = false;

	public static function initDefaults(): void {
		self::$noconversionUrl = false;
		self::$redirectTo = false;
		self::$directConversionFlag = false;
		self::$langsUrls = array();
		self::$targetLang = false;
	}

	public static function targetLang() {
		return self::$targetLang;
	}

	public static function setTargetLang($lang): void {
		self::$targetLang = $lang;
	}

	public static function noconversionUrl() {
		return self::$noconversionUrl;
	}

	public static function setNoconversionUrl($url): void {
		self::$noconversionUrl = $url;
	}

	public static function redirectTo() {
		return self::$redirectTo;
	}

	public static function setRedirectTo($value): void {
		self::$redirectTo = $value;
	}

	public static function directConversionFlag() {
		return self::$directConversionFlag;
	}

	public static function setDirectConversionFlag($value): void {
		self::$directConversionFlag = $value;
	}

	public static function langsUrls(): array {
		return self::$langsUrls;
	}

	public static function setLangsUrls(array $value): void {
		self::$langsUrls = $value;
	}
}
