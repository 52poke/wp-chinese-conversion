<?php

declare(strict_types=1);

namespace Wpcc;

class Diagnostics {
	private static $debugData = array();

	public static function set_debug_data(array $data): void {
		self::$debugData = $data;
	}

	public static function debug_output(): void {
		global $wp_rewrite;
		$wpcc_langs = State::langs();
		$wpcc_options = State::options();
		$debug_data = self::$debugData;
		$wpcc_noconversion_url = Context::noconversionUrl();
		$wpcc_target_lang = Context::targetLang();
		$wpcc_langs_urls = Context::langsUrls();
		echo '<!--';
		echo '<p style="font-size:20px;color:red;">';
		echo 'WP Chinese Conversion Plugin Debug Output:<br />';
		echo '默认URL: <a href="'. $wpcc_noconversion_url . '">' . $wpcc_noconversion_url . '</a><br />';
		echo '当前语言(空则是不转换): ' . $wpcc_target_lang . "<br />";
		echo 'Query String: ' . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '') . '<br />';
		echo 'Request URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . '<br />';
		foreach ($wpcc_langs_urls as $key => $value) {
			echo $key . ' URL: <a href="' . $value . '">' . $value . '</a><br />';
		}
		echo 'Category feed link: ' . get_category_feed_link(1) . '<br />';
		echo 'Search feed link: ' . get_search_feed_link('test');
		echo 'Rewrite Rules: <br />';
		echo nl2br(htmlspecialchars(var_export($wp_rewrite->rewrite_rules(), true))) . '<br />';
		echo 'Debug Data: <br />';
		echo nl2br(htmlspecialchars(var_export($debug_data, true)));
		echo '</p>';
		echo '-->';
	}
}
