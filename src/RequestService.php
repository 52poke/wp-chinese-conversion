<?php

declare(strict_types=1);

namespace Wpcc;

class RequestService {
	private static bool $initialized = false;

	public static function init(): void {
		if (self::$initialized) {
			return;
		}
		self::$initialized = true;
		global $wp_rewrite;
		$options = Config::options();
		if (empty($wp_rewrite->permalink_structure)) {
			$options['wpcc_use_permalink'] = 0;
			Config::setOptions($options);
		}
		add_filter('rewrite_rules_array', array(LinkService::class, 'rewrite_rules'));
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
			return;
		}
		$script = basename(self::request_string($_SERVER, 'SCRIPT_NAME'));
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array($script, array('wp-comments-post.php', 'ajax-comments.php', 'comments-ajax.php'), true)) {
			$variant = sanitize_key(self::request_string($_POST, 'variant'));
			if (in_array($variant, $options['wpcc_used_langs'], true)) {
				Context::setTargetLang($variant);
			}
			return;
		}
		add_action('parse_request', array(self::class, 'parse_query'));
		add_action('template_redirect', array(self::class, 'template_redirect'), -100);
	}

	public static function template_redirect(): void {
		if (!Context::noconversionUrl()) {
			return;
		}
		$options = Config::options();
		if (is_404()) {
			Context::setNoconversionUrl(home_url('/'));
			Context::setTargetLang(false);
			Context::setRedirectTo(false);
		}
		$urls = array();
		foreach ($options['wpcc_used_langs'] as $variant) {
			$urls[$variant] = LinkService::link_conversion(Context::noconversionUrl(), $variant);
		}
		Context::setLangsUrls($urls);
		$redirect = Context::redirectTo();
		if ($redirect && self::can_redirect()) {
			self::set_cookie('wpcc_is_redirect_' . COOKIEHASH, '1', 0);
			nocache_headers();
			wp_safe_redirect($urls[$redirect], 302);
			exit;
		}
		$target = Context::targetLang();
		if (!$target) {
			return;
		}
		add_action('comment_form', static function() use ($target): void {
			echo '<input type="hidden" name="variant" value="' . esc_attr($target) . '" />';
		});
		FrontendService::do_conversion();
	}

	private static function can_redirect(): bool {
		return in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true);
	}

	public static function parse_query($query): void {
		Context::initDefaults();
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || !empty($query->query_vars['rest_route']) || !empty($query->query_vars['robots']) || !empty($query->query_vars['favicon']) || !empty($query->query_vars['sitemap']) || !empty($query->query_vars['sitemap-stylesheet'])) {
			return;
		}
		$options = Config::options();
		Context::setNoconversionUrl(LinkService::get_noconversion_url());
		$request = sanitize_key(self::request_string($query->query_vars, 'variant', false));
		$cookie = sanitize_key(self::request_string($_COOKIE, 'wpcc_variant_' . COOKIEHASH));
		$remember = $options['wpcc_use_cookie_variant'];
		if (in_array($request, $options['wpcc_used_langs'], true)) {
			Context::setTargetLang($request);
		} elseif (in_array($request, array('zh', 'zh-reset'), true) && self::can_redirect()) {
			$expires = $request === 'zh' && $remember ? time() + YEAR_IN_SECONDS : time() - YEAR_IN_SECONDS;
			self::set_cookie('wpcc_variant_' . COOKIEHASH, $request === 'zh' && $remember ? 'zh' : '', $expires);
			self::set_cookie('wpcc_is_redirect_' . COOKIEHASH, $request === 'zh' && !$remember ? '1' : '', $request === 'zh' ? 0 : time() - YEAR_IN_SECONDS);
			nocache_headers();
			wp_safe_redirect(Context::noconversionUrl(), 302);
			exit;
		} elseif ($request === '') {
			$suppressed = $remember && $cookie === 'zh';
			if (!empty($_COOKIE['wpcc_is_redirect_' . COOKIEHASH])) {
				$suppressed = true;
				if ($remember) {
					self::set_cookie('wpcc_variant_' . COOKIEHASH, 'zh', time() + YEAR_IN_SECONDS);
					self::set_cookie('wpcc_is_redirect_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS);
				}
			}
			if (!$suppressed && !self::is_robot()) {
				$variant = $remember && in_array($cookie, $options['wpcc_used_langs'], true) ? $cookie : false;
				$mode = $variant ? $remember : $options['wpcc_browser_redirect'];
				if (!$variant && $mode) {
					$variant = self::get_prefered_language(self::request_string($_SERVER, 'HTTP_ACCEPT_LANGUAGE'), $options['wpcc_used_langs'], $options['wpcc_auto_language_recong']);
				}
				if ($variant && $mode === 2) {
					Context::setTargetLang($variant);
					Context::setDirectConversionFlag(true);
				} elseif ($variant && $mode === 1 && self::can_redirect()) {
					Context::setRedirectTo($variant);
				}
			}
		}
		if ($options['wpcc_search_conversion'] === 2 || ($options['wpcc_search_conversion'] === 1 && Context::targetLang())) {
			SearchService::apply_filter_search_rule();
		}
		if (Context::targetLang() && $remember && $cookie !== Context::targetLang()) {
			self::set_cookie('wpcc_variant_' . COOKIEHASH, Context::targetLang(), time() + YEAR_IN_SECONDS);
		}
		if ($remember || $options['wpcc_browser_redirect']) {
			nocache_headers();
		}
	}

	public static function get_prefered_language($accept_languages, $target_langs, $flag = 0) {
		if (!is_string($accept_languages) || !is_array($target_langs)) {
			return false;
		}
		$preferences = array();
		foreach (explode(',', $accept_languages) as $entry) {
			if (!preg_match('/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*)\s*(?:;\s*q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?\s*$/iD', $entry, $match)) {
				continue;
			}
			$quality = isset($match[2]) ? (float) $match[2] : 1.0;
			$lang = strtolower($match[1]);
			$preferences[$lang] = max($preferences[$lang] ?? 0, $quality);
		}
		arsort($preferences, SORT_NUMERIC);
		foreach ($preferences as $lang => $quality) {
			if ($quality <= 0) {
				continue;
			}
			$variant = $lang;
			if (preg_match('/^zh-(hans|hant)(?:-|$)/', $lang, $match)) {
				$variant = 'zh-' . $match[1];
			} else {
				$aliases = array('zh-cn' => 'zh-hans', 'zh-sg' => 'zh-hans', 'zh-my' => 'zh-hans', 'zh-tw' => 'zh-hant', 'zh-hk' => 'zh-hant', 'zh-mo' => 'zh-hant');
				$variant = $aliases[$lang] ?? $lang;
			}
			if (in_array($variant, $target_langs, true)) {
				return $variant;
			}
			if (!str_starts_with($lang, 'zh')) {
				return false;
			}
		}
		return false;
	}

	public static function is_robot(): bool {
		if (empty($_SERVER['HTTP_USER_AGENT'])) {
			return true;
		}
		$ua = (string) $_SERVER['HTTP_USER_AGENT'];

		$robots = array(
			'bot',
			'spider',
			'crawler',
			'dig',
			'search',
			'find'
		);

		foreach ($robots as $val) {
			if (stripos($ua, $val) !== false) {
				return true;
			}
		}

		$browsers = array(
			'compatible; MSIE',
			'UP.Browser',
			'Mozilla',
			'Opera',
			'NSPlayer',
			'Avant Browser',
			'Chrome',
			'Gecko',
			'Safari',
			'Lynx',
		);

		foreach ($browsers as $val) {
			if (stripos($ua, $val) !== false) {
				return false;
			}
		}

		return true;
	}

	public static function set_cookie($name, $value, $expires): bool {
		if (headers_sent()) {
			return false;
		}
		$args = array(
			'expires' => $expires,
			'path' => COOKIEPATH ?: '/',
			'domain' => COOKIE_DOMAIN ?: '',
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		return setcookie($name, $value, $args);
	}

	public static function request_string(array $source, string $key, bool $unslash = true): string {
		$value = $source[$key] ?? '';
		return is_string($value) ? ($unslash ? wp_unslash($value) : $value) : '';
	}
}
