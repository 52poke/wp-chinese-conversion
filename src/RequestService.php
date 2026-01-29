<?php

declare(strict_types=1);

namespace Wpcc;

class RequestService {
	public static function template_redirect(): void {
		$options = State::options();
		$noconversion_url = Context::noconversionUrl();
		$langs_urls = Context::langsUrls();
		$redirect_to = Context::redirectTo();
		$target_lang = Context::targetLang();

		if ($noconversion_url == home_url('/') && !empty($options['wpcc_use_permalink'])) {
			foreach ($options['wpcc_used_langs'] as $value) {
				$langs_urls[$value] = $noconversion_url . $value . '/';
			}
		} else {
			foreach ($options['wpcc_used_langs'] as $value) {
				$langs_urls[$value] = wpcc_link_conversion($noconversion_url, $value);
			}
		}
		Context::setLangsUrls($langs_urls);

		if (!is_404() && $redirect_to) {
			wpcc_set_cookie('wpcc_is_redirect_' . COOKIEHASH, '1', 0);
			wp_safe_redirect($langs_urls[$redirect_to], 302);
			exit;
		}

		if (!$target_lang) {
			return;
		}

		add_action('comment_form', function() use ($target_lang) {
			echo '<input type="hidden" name="variant" value="' . esc_attr($target_lang) . '" />';
		});
		wpcc_do_conversion();
	}

	public static function parse_query($query): void {
		if (is_robots()) {
			return;
		}
		$options = State::options();

		if (!is_404()) {
			Context::setNoconversionUrl(wpcc_get_noconversion_url());
		} else {
			Context::setNoconversionUrl(home_url('/'));
			Context::setTargetLang(false);
			return;
		}

		$noconversion_url = Context::noconversionUrl();
		$request_lang = isset($query->query_vars['variant']) ? sanitize_key($query->query_vars['variant']) : '';
		$cookie_lang = isset($_COOKIE['wpcc_variant_' . COOKIEHASH]) ? sanitize_key($_COOKIE['wpcc_variant_' . COOKIEHASH]) : '';

		Context::setTargetLang($request_lang && in_array($request_lang, $options['wpcc_used_langs'], true) ? $request_lang : false);
		$target_lang = Context::targetLang();

		if (!$target_lang) {
			if ($request_lang == 'zh') {
				if ($options['wpcc_use_cookie_variant'] != 0) {
					wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, 'zh', time() + 30000000);
				} else {
					wpcc_set_cookie('wpcc_is_redirect_' . COOKIEHASH, '1', 0);
				}
				wp_safe_redirect($noconversion_url);
				exit;
			}
			if ($request_lang == 'zh-reset') {
				wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, '', time() - 30000000);
				wpcc_set_cookie('wpcc_is_redirect_' . COOKIEHASH, '', time() - 30000000);
				wp_safe_redirect($noconversion_url);
				exit;
			}

			if ($cookie_lang == 'zh') {
				if ($options['wpcc_use_cookie_variant'] != 0) {
					if ($options['wpcc_search_conversion'] == 2) {
						wpcc_apply_filter_search_rule();
					}
					return;
				}
				wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, '', time() - 30000000);
			}

			if (!$request_lang && !empty($_COOKIE['wpcc_is_redirect_' . COOKIEHASH])) {
				if ($options['wpcc_use_cookie_variant'] != 0) {
					wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, 'zh', time() + 30000000);
					wpcc_set_cookie('wpcc_is_redirect_' . COOKIEHASH, '', time() - 30000000);
				} else if ($cookie_lang) {
					wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, '', time() - 30000000);
				}
				if ($options['wpcc_search_conversion'] == 2) {
					wpcc_apply_filter_search_rule();
				}
				return;
			}

			$is_robot = wpcc_is_robot();
			if ($options['wpcc_use_cookie_variant'] != 0 && !$is_robot && $cookie_lang) {
				if (in_array($cookie_lang, $options['wpcc_used_langs'], true)) {
					if ($options['wpcc_use_cookie_variant'] == 2) {
						Context::setTargetLang($cookie_lang);
						Context::setDirectConversionFlag(true);
					} else {
						Context::setRedirectTo($cookie_lang);
					}
				} else {
					wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, '', time() - 30000000);
				}
			} else {
				if ($cookie_lang) {
					wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, '', time() - 30000000);
				}
				if (
					$options['wpcc_browser_redirect'] != 0 &&
					!$is_robot &&
					!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) &&
					$wpcc_browser_lang = wpcc_get_prefered_language(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']), $options['wpcc_used_langs'], $options['wpcc_auto_language_recong'])
				) {
					if ($options['wpcc_browser_redirect'] == 2) {
						Context::setTargetLang($wpcc_browser_lang);
						Context::setDirectConversionFlag(true);
					} else {
						Context::setRedirectTo($wpcc_browser_lang);
					}
				}
			}
		}

		if ($options['wpcc_search_conversion'] == 2 ||
			(Context::targetLang() && $options['wpcc_search_conversion'] == 1)
		) {
			wpcc_apply_filter_search_rule();
		}

		$target_lang = Context::targetLang();
		if ($target_lang && $options['wpcc_use_cookie_variant'] != 0 && $cookie_lang != $target_lang) {
			wpcc_set_cookie('wpcc_variant_' . COOKIEHASH, $target_lang, time() + 30000000);
		}
	}
}
