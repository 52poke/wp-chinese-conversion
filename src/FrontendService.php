<?php

declare(strict_types=1);

namespace Wpcc;

class FrontendService {
	public static function header(): void {
		$target_lang = Context::targetLang();
		$noconversion_url = Context::noconversionUrl();
		$langs_urls = Context::langsUrls();
		$direct_flag = Context::directConversionFlag();
		echo "\n" . '<!-- WP Chinese Conversion Plugin Version ' . WPCC_VERSION . ' -->';
		$inline = 'window.wpcc_target_lang=' . wp_json_encode($target_lang) . ';';
		$inline .= 'window.wpcc_noconversion_url=' . wp_json_encode($noconversion_url) . ';';
		$inline .= 'window.wpcc_langs_urls=' . wp_json_encode($langs_urls) . ';';
		$handle = 'wpcc-search-js';
		if (!$direct_flag) {
			wp_register_script($handle, WPCC_ROOT_URL . 'search-variant.min.js', array(), WPCC_VERSION, false);
		} else {
			wp_register_script($handle, false, array(), WPCC_VERSION, false);
		}
		wp_enqueue_script($handle);
		wp_add_inline_script($handle, $inline, 'before');

		if ($direct_flag ||
			((class_exists('All_in_One_SEO_Pack') || class_exists('Platinum_SEO_Pack')) &&
			!is_single() && !is_home() && !is_page() && !is_search())
		) {
			return;
		}
		echo '<meta name="robots" content="noindex,follow" />';
	}

	public static function ob_callback($buffer) {
		$target_lang = Context::targetLang();
		$direct_flag = Context::directConversionFlag();
		if ($target_lang && !$direct_flag) {
			$wpcc_home_url = wpcc_link_conversion_auto(home_url('/'));
			$buffer = preg_replace('|(<a\s(?!class="wpcc_link")[^<>]*?href=([\'"]))' . preg_quote(esc_url(home_url('')), '|') . '/?(\2[^<>]*?>)|', '\\1' . esc_url($wpcc_home_url) . '\\3', $buffer);
		}
		return zhconversion2($buffer) . "\n" . '<!-- WP Chinese Conversion Full Page Converted. Target Lang: ' . $target_lang . ' -->';
	}

	public static function debug(): void {
		Diagnostics::debug_output();
	}
}
