<?php

declare(strict_types=1);

namespace Wpcc;

class LocaleService {
	public static function variant_attribute($default = 'zh', $variant = false) {
		$langs = State::langs();
		if (!$variant) {
			$variant = Context::targetLang();
		}
		if (!$variant) {
			return $default;
		}
		return $langs[$variant][3];
	}

	public static function variant($default = false) {
		$target = Context::targetLang();
		if (!$target) {
			return $default;
		}
		return $target;
	}

	public static function body_class(array $classes): array {
		$target = Context::targetLang();
		$classes[] = $target ? $target : 'zh';
		return $classes;
	}

	public static function locale(string $output, string $doctype = 'html'): string {
		$langs = State::langs();
		$lang = get_bloginfo('language');
		$target = Context::targetLang();
		if ($target && strpos($lang, 'zh-') === 0) {
			$lang = $langs[$target][3];
			$output = preg_replace('/lang="[^"]+"/', "lang=\"{$lang}\"", $output);
		}
		return $output;
	}
}
