<?php

declare(strict_types=1);

namespace Wpcc;

class State {
	private static $options = array();
	private static $langs = array();

	public static function options(): array {
		return self::$options;
	}

	public static function setOptions(array $options): void {
		self::$options = $options;
	}

	public static function langs(): array {
		if (!empty(self::$langs)) {
			return self::$langs;
		}
		return Config::langs();
	}

	public static function setLangs(array $langs): void {
		self::$langs = $langs;
	}

	public static function targetLang(): ?string {
		$target = Context::targetLang();
		return $target ?: null;
	}
}
