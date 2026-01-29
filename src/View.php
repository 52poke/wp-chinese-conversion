<?php

declare(strict_types=1);

namespace Wpcc;

class View {
	public static function output_navi($args = ''): void {
		$wpcc_langs = State::langs();
		$wpcc_options = State::options();
		$wpcc_target_lang = Context::targetLang();
		$wpcc_noconversion_url = Context::noconversionUrl();
		$wpcc_langs_urls = Context::langsUrls();

		$parsed = wp_parse_args($args, array('mode' => 'normal', 'echo' => 1));
		$mode = $parsed['mode'];
		$echo = $parsed['echo'];
		if ($mode == 'wrap') {
			self::output_navi2();
			return;
		}

		if (!empty($wpcc_options['nctip'])) {
			$noconverttip = $wpcc_options['nctip'];
		} else {
			$locale = str_replace('_', '-', strtolower(get_locale()));
			if (in_array($locale, array('zh-hant'))) {
				$noconverttip = '不轉換';
			} else {
				$noconverttip = '不转换';
			}
		}
		if ($wpcc_target_lang) {
			$noconverttip = zhconversion($noconverttip);
		}
		if (($wpcc_options['wpcc_browser_redirect'] == 2 || $wpcc_options['wpcc_use_cookie_variant'] == 2) &&
			$wpcc_target_lang
		) {
			$default_url = wpcc_link_conversion($wpcc_noconversion_url, 'zh');
			if ($wpcc_options['wpcc_use_permalink'] != 0 && is_home() && !is_paged()) {
				$default_url = trailingslashit($default_url);
			}
		} else {
			$default_url = $wpcc_noconversion_url;
		}

		$output = "\n" . '<div id="wpcc_widget_inner"><!--WPCC_NC_START-->' . "\n";
		$output .= "\t" . '<span id="wpcc_original_link" class="' . ($wpcc_target_lang == false ? 'wpcc_current_lang' : 'wpcc_lang') . '" ><a class="wpcc_link" href="' . esc_url($default_url) . '" title="' . esc_html($noconverttip) . '">' . esc_html($noconverttip) . '</a></span>' . "\n";

		foreach ($wpcc_langs_urls as $key => $value) {
			$tip = !empty($wpcc_options[$wpcc_langs[$key][1]]) ? esc_html($wpcc_options[$wpcc_langs[$key][1]]) : $wpcc_langs[$key][2];
			$output .= "\t" . '<span id="wpcc_' . $key . '_link" class="' . ($wpcc_target_lang == $key ? 'wpcc_current_lang' : 'wpcc_lang') . '" ><a class="wpcc_link" rel="nofollow" href="' . esc_url($value) . '" title="' . esc_html($tip) . '" >' . esc_html($tip) . '</a></span>' . "\n";
		}
		$output .= '<!--WPCC_NC_END--></div>' . "\n";
		if (!$echo) {
			return;
		}
		echo $output;
	}

	public static function output_navi2(): void {
		$wpcc_options = State::options();
		$wpcc_target_lang = Context::targetLang();
		$wpcc_noconversion_url = Context::noconversionUrl();
		$wpcc_langs_urls = Context::langsUrls();

		if (($wpcc_options['wpcc_browser_redirect'] == 2 || $wpcc_options['wpcc_use_cookie_variant'] == 2) &&
			$wpcc_target_lang
		) {
			$default_url = wpcc_link_conversion($wpcc_noconversion_url, 'zh');
			if ($wpcc_options['wpcc_use_permalink'] != 0 && is_home() && !is_paged()) {
				$default_url = trailingslashit($default_url);
			}
		} else {
			$default_url = $wpcc_noconversion_url;
		}

		$output = "\n" . '<div id="wpcc_widget_inner"><!--WPCC_NC_START-->' . "\n";
		$output .= "\t" . '<span id="wpcc_original_link" class="' . ($wpcc_target_lang == false ? 'wpcc_current_lang' : 'wpcc_lang') . '" ><a class="wpcc_link" href="' . esc_url($default_url) . '" title="不轉換">不轉換</a></span>' . "\n";
		$output .= "\t" . '<span id="wpcc_hans_link" class="' . ($wpcc_target_lang == 'zh-hans' ? 'wpcc_current_lang' : 'wpcc_lang') . '" ><a class="wpcc_link" rel="nofollow" href="' . esc_url($wpcc_langs_urls['zh-hans']) . '" title="简体中文" >简体中文' . '</a></span>' . "\n";
		$output .= "\t" . '<span id="wpcc_hant_link" class="' . ($wpcc_target_lang == 'zh-hant' ? 'wpcc_current_lang' : 'wpcc_lang') . '" ><a class="wpcc_link" rel="nofollow" href="' . esc_url($wpcc_langs_urls['zh-hant']) . '" title="繁體中文" >繁體中文' . '</a></span>' . "\n";

		$output .= '<!--WPCC_NC_END--></div>' . "\n";
		echo $output;
	}
}
