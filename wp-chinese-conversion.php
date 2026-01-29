<?php
/*
Plugin Name: WP Chinese Conversion
Plugin URI: https://oogami.name/project/wpcc/
Description: Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Blog.
Version: 2.0.0
Author: Ono Oogami
Author URI: https://oogami.name/
*/

/*
Copyright (C) 2009-2013 Ono Oogami, https://oogami.name/ (ono@oogami.name)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * WP Chinese Conversion Plugin main file
 *
 * 為Wordpress增加中文繁簡轉換功能. 轉換過程在服務器端完成. 使用的繁簡字符映射表來源于Mediawiki.
 * 本插件比较耗费资源. 因为对页面内容繁简转换时载入了一个几百KB的转换表(ZhConversion.php), 编译后占用内存超过1.5MB
 * 如果可能, 建议安装xcache/ eAccelerator之类PHP缓存扩展. 可以有效提高速度并降低CPU使用,在生产环境下测试效果非常显著.
 *
 * @package WPCC
 * @version see WPCC_VERSION constant below
 * Modernized to a class-based structure for current WordPress and PHP versions.
 * Originally developed by oogami.name.
 * This fork is updated and maintained by mudkipme.
 *
 */

define('WPCC_ROOT_URL', plugin_dir_url(__FILE__));
define('WPCC_VERSION', '2.0.0');

require_once __DIR__ . '/src/Plugin.php';
\Wpcc\Plugin::bootstrap();

$options = get_option('wpcc_options');
if (!is_array($options)) {
	$options = wpcc_normalize_options(array());
	update_option('wpcc_options', $options);
} else {
	$normalized = wpcc_normalize_options($options);
	if ($normalized !== $options) {
		$options = $normalized;
		update_option('wpcc_options', $options);
	} else {
		$options = $normalized;
	}
}
\Wpcc\State::setOptions(is_array($options) ? $options : array());
\Wpcc\Context::initDefaults();

\Wpcc\State::setLangs(\Wpcc\Config::langs());

function wpcc_normalize_options($options) {
	$langs = \Wpcc\Config::langs();
	$defaults = \Wpcc\Config::defaults();

	if (!is_array($options)) {
		return $defaults;
	}

	$options = array_merge($defaults, $options);
	$options['wpcc_used_langs'] = array_values(array_intersect(
		array_keys($langs),
		(array) $options['wpcc_used_langs']
	));

	if (empty($options['wpcc_used_langs'])) {
		$options['wpcc_used_langs'] = array_keys($langs);
	}

	foreach ($langs as $key => $value) {
		$tip_key = $value[1];
		if (!isset($options[$tip_key])) {
			$options[$tip_key] = '';
		}
	}

	return $options;
}

function wpcc_set_cookie($name, $value, $expires) {
	return \Wpcc\Utils::set_cookie($name, $value, $expires);
}

function wpcc_get_variant_regex($include_controls = true) {
	return \Wpcc\Utils::get_variant_regex($include_controls);
}

\Wpcc\Plugin::register();


/* 全局代码END; 下面的全是函数定义 */

/**
 * 插件初始化
 *
 * 本函数做了下面事情:
 * A. 调用wpcc_get_noconversion_url函数设置 $wpcc_noconversion_url全局变量
 * A. 调用wpcc_get_lang_url函数设置 $wpcc_langs_urls全局(数组)变量
 * B. 如果当前为POST方式提交评论请求, 直接调用wpcc_do_conversion
 * B. 否则, 加载parse_request接口
 */
function wpcc_init() {
	global $wp_rewrite;
	$wpcc_options = \Wpcc\State::options();

	if( $wpcc_options['wpcc_use_permalink'] !=0 && empty($wp_rewrite->permalink_structure) ) {
		$wpcc_options['wpcc_use_permalink'] = 0;
		update_option('wpcc_options', $wpcc_options);
		\Wpcc\State::setOptions($wpcc_options);
	}
	if( $wpcc_options['wpcc_use_permalink'] != 0 )
		add_filter('rewrite_rules_array', 'wpcc_rewrite_rules');

	$php_self = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '';
	if( (strpos($php_self, 'wp-comments-post.php') !== false
		|| strpos($php_self, 'ajax-comments.php') !== false
		|| strpos($php_self, 'comments-ajax.php') !== false
		) &&
		(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') &&
		!empty($_POST['variant'])
	) {
		$variant = sanitize_key(wp_unslash($_POST['variant']));
		if (in_array($variant, $wpcc_options['wpcc_used_langs'], true)) {
			\Wpcc\Context::setTargetLang($variant);
			wpcc_do_conversion();
			return;
		}
	}

	add_action('parse_request', 'wpcc_parse_query');
	add_action('template_redirect', 'wpcc_template_redirect', -100);
}

/**
 * 输出Header信息
 *
 * 在繁简转换页<header>部分输出一些JS和noindex的meta信息.
 * noindex的meta头是为了防止搜索引擎索引重复内容;
 *
 * JS信息是为了客户端一些应用和功能保留的.
 * 举例, 当访客在一个繁简转换页面提交搜索时, 本插件载入的JS脚本会在GET请求里附加一个variant变量,
 * 如 /?s=test&variant=zh-hant
 * 这样服务器端能够获取用户当前中文语言, 并显示对应语言的搜索结果页
 *
 */
function wpcc_header() {
	\Wpcc\FrontendService::header();
}

/*
 * 设置url. 包括当前页面原始URL和各个语言版本URL
 * @since 1.1.7
 *
 */
function wpcc_template_redirect() {
	\Wpcc\RequestService::template_redirect();
}

/**
 * 在Wordpress的query vars里增加一个variant变量.
 *
 */
function wpcc_insert_query_vars($vars) {
	$vars[] = 'variant';
	return $vars;
}

/**
 * Widget Class
 * @since 1.1.8
 *
 */
class Wpcc_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct('widget_wpcc', 'Chinese Conversion', array('classname' => 'widget_wpcc', 'description' =>'Chinese Conversion Widget'));
	}

	public function widget($args, $instance) {
		$title = isset($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
		$before_widget = isset($args['before_widget']) ? $args['before_widget'] : '';
		$before_title = isset($args['before_title']) ? $args['before_title'] : '';
		$after_title = isset($args['after_title']) ? $args['after_title'] : '';
		$after_widget = isset($args['after_widget']) ? $args['after_widget'] : '';
		echo $before_widget;
		if ($title) {
			echo $before_title . $title . $after_title;
		}
		wpcc_output_navi( isset($instance['args']) ? $instance['args'] : '' );
		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		return array(
			'title' => isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '',
			'args' => isset($new_instance['args']) ? sanitize_text_field($new_instance['args']) : '',
		);
	}

	public function form($instance) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$args = isset($instance['args']) ? esc_attr($instance['args']) : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
			<label for="<?php echo $this->get_field_id('args'); ?>">Args: <input class="widefat" id="<?php echo $this->get_field_id('args'); ?>" name="<?php echo $this->get_field_name('args'); ?>" type="text" value="<?php echo $args; ?>" /></label>
		</p>
		<?php
	}
}

/**
 * 转换字符串到当前请求的中文语言
 *
 * @param string $str string inputed
 * @param string $variant optional, Default to null, chinese language code you want string to be converted, if null (default), uses the current target language
 * @return converted string
 *
 * 这是本插件繁简转换页使用的基本中文转换函数. 例如, 如果访客请求一个"繁體中文"版本页面,
 * $wpcc_conversion_function被设置为'zhconversion_hant',
 * 本函数调用其把字符串转换为"繁體中文"版本
 *
 */
function zhconversion($str, $variant = null) {
	return \Wpcc\ConversionService::zhconversion($str, $variant);
}


function zhconversion2($str, $variant = null) {
	return \Wpcc\ConversionService::zhconversion2($str, $variant);
}


/**
 * get a unique id number
 */
function wpcc_id() {
	static $wpcc_id = 1000;
	return $wpcc_id++;
}

/**
 * filter the content
 * @since 1.1.14
 *
 */
function wpcc_no_conversion_filter($str) {
	return \Wpcc\ConversionService::wpcc_no_conversion_filter($str);
}


/**
 * 转换字符到多种中文语言,返回数组
 *
 * @param string $str string to be converted
 * @param array $langs Optional, Default to array('zh-hans', 'zh-hant'). array of chinese languages codes you want string to be converted to
 * @return array converted strings array
 *
 * Example: zhconversion('網絡');
 * Return: array('網路', '网络');
 *
 */
function zhconversion_all($str, $langs = array('zh-hans', 'zh-hant')) {
	return \Wpcc\ConversionService::zhconversion_all($str, $langs);
}

/**
 * 递归的对数组中元素用zhconversion函数转换, 返回处理后数组.
 *
 */
function zhconversion_deep($value) {
	return \Wpcc\ConversionService::zhconversion_deep($value);
}

/**
 * 对输入字符串进行有限中文转换. 不转换<!--WPCC_NC_START-->和<!--WPCC_NC_END-->之間的中文
 *
 * @param string $str string inputed
 * @param string $function conversion function for current requested chinese language
 * @return converted string
 *
 */
function limit_zhconversion($str, $function) {
	return \Wpcc\ConversionService::limit_zhconversion($str, $function);
}


/**
 * 中文轉換函數. (zhconversion_hans轉換字符串為简体中文, zhconversion_hant轉換字符串為繁體中文)
 *
 * @param string $str string to be converted
 * @return string converted chinese string
 *
 *
 */
function zhconversion_hant($str) {
	global $zh2Hant;
	if (!is_array($zh2Hant) || empty($zh2Hant)) {
		wpcc_load_conversion_table();
	}
	if (!is_array($zh2Hant) || empty($zh2Hant)) {
		return $str;
	}
	return strtr($str, $zh2Hant);
}

function zhconversion_hans($str) {
	global $zh2Hans;
	if (!is_array($zh2Hans) || empty($zh2Hans)) {
		wpcc_load_conversion_table();
	}
	if (!is_array($zh2Hans) || empty($zh2Hans)) {
		return $str;
	}
	return strtr($str, $zh2Hans);
}

/**
 * 不推荐, 为向后兼容保留的函数
 * 为模板预留的函数, 把链接安全转换为当前中文语言版本的, 你可以在模板中调用其转换硬编码的链接.
/**
 * 取消WP错误的重定向
 *
 * @param string $redirect_to 'redirect_canonical' filter's first argument
 * @param string $redirect_from 'redirect_canonical' filter's second argument
 * @return string|false
 *
 * 因为Wordpress canonical url机制, 有时会把繁简转换页重定向到错误URL
 * 本函数检测并取消这种重定向(通过返回false)
 *
 */
function wpcc_cancel_incorrect_redirect($redirect_to, $redirect_from) {
	return \Wpcc\LinkService::cancel_incorrect_redirect($redirect_to, $redirect_from);
}

/**
 * 修改WP Rewrite规则数组, 增加本插件添加的Rewrite规则
 *
 * @param array $rules 'rewrite_rules_array' filter's argument , WP rewrite rules array
 * @return array processed rewrite rules array
 *
 *
 * 基本上, 本函数对WP的Rewrite规则数组这样处理:
 *
 * 对 '..../?$' => 'index.php?var1=$matches[1]..&varN=$matches[N]' 这样一条规则,
 * 如果规则体部分 '.../?$' 含有 'attachment', 'print', 不做处理
 * 否则, 增加一条 '.../zh-hant|zh-hans|zh|zh-reset/?$' => 'index.php?var1=$matches[1]..&varN=$matches[N]&variant=$matches[N+1]'的新规则
 * 1.1.6版本后, 因为增加了/zh-hant/original/permalink/这种URL形式, 情况更加复杂
 *
 */
function wpcc_rewrite_rules($rules) {
	return \Wpcc\LinkService::rewrite_rules($rules);
}

function _wpcc_permalink_preg_callback($matches) {
	return \Wpcc\LinkService::permalink_preg_callback($matches);
}

/**
 * 修改繁簡轉換頁面WP內部鏈接
 *
 * @param string $link URL to be converted
 * @return string converted URL
 *
 * 如果訪客請求一個繁簡轉換頁面, 本函數把該頁的所有鏈接轉換為對應中文語言版本的
 * 例如把分類頁鏈接轉換為 /category/cat-name/zh-xx/, 把Tag頁鏈接轉換為 /tag/tag-name/zh-xx/
 *
 */
function wpcc_link_conversion($link, $variant = null) {
	return \Wpcc\LinkService::link_conversion($link, $variant);
}

/**
 * don't convert a link in "direct_conversion" mode;
 * @since 1.1.14.2
 */
function wpcc_link_conversion_auto($link, $variant = null) {
	return \Wpcc\LinkService::link_conversion_auto($link, $variant);
}

/**
 * 獲取當前頁面原始URL
 * @return original permalink of current page
 *
 * 本函數返回當前請求頁面"原始版本" URL.
 * 即如果當前URL是 /YYYY/mm/sample-post/zh-hant/ 形式的繁體版本,
 * 會返回 /YYYY/mm/sample-post/ 的原始(不進行中文轉換)版本鏈接.
 *
 */
function wpcc_get_noconversion_url() {
	return \Wpcc\LinkService::get_noconversion_url();
}

/**
 * 修復繁簡轉換頁分頁鏈接
 *
 * @param string $link URL to be fixed
 * @return string Fixed URL
 *
 * 本函數修復繁簡轉換頁面 /.../page/N 形式的分頁鏈接為正確形式. 具體說明略.
 *
 * 你可以在本函數內第一行加上 'return $link;' 然后訪問你博客首頁或存檔頁的繁體或简体版本,
 * 會發現"上一頁"(previous posts page)和"下一頁"(next posts page)的鏈接URL是錯誤的.
 * 本函数算法极为愚蠢- -, 但是没有其它办法, 因为wordpress对于分页链接的生成策略非常死板且无法更多地通过filter控制
 *
 */
function wpcc_pagenum_link_fix($link) {
	return \Wpcc\LinkService::pagenum_link_fix($link);
}

/**
 * 修复繁简转换后页面部分内部链接.
 *
 * @param string $link URL to be fixed
 * @return string Fixed URL
 *
 * 本插件会添加 post_link钩子, 从而修改繁简转换页单篇文章页永久链接, 但WP的很多内部链接生成依赖这个permalink.
 * (为什么加载在post_link钩子上而不是the_permalink钩子上? 有很多原因,这里不说了.)
 *
 * 举例而言, 本插件把 繁简转换页的文章permalink修改为 /YYYY/mm/sample-post/zh-hant/ (如果您原来的Permalink是/YYYY/mm/sample-post/)
 * 那么WP生成的该篇文章评论Feed链接是 /YYYY/mm/sample-post/zh-hant/feed/, 出错
 * 本函数把这个链接修复为 /YYYY/mm/sample-post/feed/zh-hant/ 的正确形式.
 *
 */
function wpcc_fix_link_conversion($link) {
	return \Wpcc\LinkService::fix_link_conversion($link);
}

/**
 * "取消"繁简转换后页面部分内部链接轉換.
 * @param string $link URL to be fixed
 * @return string Fixed URL
 *
 * 本函數作用與上面的wpcc_fix_link_conversion類似, 不同的是本函數"取消"而不是"修復"繁簡轉換頁內部鏈接
 * 例如, 把已加入語言字段的鏈接修復為不帶語言字段的正確形式.
 *
 */
function wpcc_cancel_link_conversion($link) {
	return \Wpcc\LinkService::cancel_link_conversion($link);
}

/**
 * ...
 */
function wpcc_rel_canonical() {
	if ( !is_singular() )
		return;
	global $wp_the_query;
	if ( !$id = $wp_the_query->get_queried_object_id() )
		return;
	$link = wpcc_cancel_link_conversion(get_permalink( $id ));
	echo "<link rel='canonical' href='" . esc_url($link) . "' />\n";
}


/**
 * 返回w3c標準的當前中文語言代碼,如 zh-Hans
 * 返回值可以用在html元素的 lang=""標籤裡
 *
 * @since 1.1.9
 * @link http://www.w3.org/International/articles/language-tags/ W3C關於language attribute文章.
 */
function variant_attribute($default = "zh", $variant = false) {
	return \Wpcc\LocaleService::variant_attribute($default, $variant);
}
/**
 * 返回當前語言代碼
 * @since 1.1.9
 */
function variant($default = false) {
	return \Wpcc\LocaleService::variant($default);
}

/**
 * 输出当前页面不同中文语言版本链接
 * @param bool $return Optional, Default to false, return or echo result.
 *
 * 本插件Widget会调用这个函数.
 *
 */
function wpcc_output_navi($args = '') {
	return \Wpcc\View::output_navi($args);
}

/**
 * 从给定的语言列表中, 解析出浏览器客户端首选语言, 返回解析出的语言字符串或false
 *
 * @param string $accept_languages the languages sting, should set to $_SERVER['HTTP_ACCEPT_LANGUAGE']
 * @param array $target_langs given languages array
 * @param int|bool $flag Optional, default to 0 ( mean false ), description missing.
 * @return string|bool the parsed lang or false if it doesn't exists
 *
 * 使用举例: 调用形式 wpcc_get_prefered_language($_SERVER['HTTP_ACCEPT_LANGUAGE'], $target_langs)
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: ja,zh-hant;q=0.8,fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-hant', 'en')
 * 返回值: zh-hant
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-hant', 'en')
 * 返回值: en
 *
 * $_SERVER['HTTP_ACCEPT_LANGUAGE']: ja,zh-hant;q=0.8,fr;q=0.5,en;q=0.3
 * $target_langs: array('zh-hant', 'zh-hans')
 * 返回值: false
 *
 */
function wpcc_get_prefered_language($accept_languages, $target_langs, $flag = 0) {
	return \Wpcc\RequestHelpers::get_prefered_language($accept_languages, $target_langs, $flag);
}

/**
 * 判断当前请求是否为搜索引擎访问.
 * 使用的算法极为保守, 只要不是几个主要的浏览器,就判定为Robots
 *
 * @uses $_SERVER['HTTP_USER_AGENT']
 * @return bool
 */
function wpcc_is_robot() {
	return \Wpcc\RequestHelpers::is_robot();
}

/**
 * fix a relative bug
 * @since 1.1.14
 *
 */
function wpcc_apply_filter_search_rule() {
	\Wpcc\SearchService::apply_filter_search_rule();
}

/**
 * 对Wordpress搜索时生成sql语句的 where 条件部分进行处理, 使其同时在数据库中搜索关键词的中文简繁体形式.
 *
 * @param string $where 'post_where' filter's argument, 'WHERE...' part of the wordpesss query sql sentence
 * @return string WHERE sentence have been processed
 *
 * 使用方法: add_filter('posts_where', 'wpcc_filter_search_rule');
 * 原理说明: 假设访客通过表单搜索 "简体 繁體 中文", Wordpress生成的sql语句条件$where中一部分是这样的:
 *
 * ((wp_posts.post_title LIKE '%简体%') OR (wp_posts.post_content LIKE '%简体%')) AND ((wp_posts.post_title LIKE '%繁體%') OR (wp_posts.post_content LIKE '%繁體%')) AND ((wp_posts.post_title LIKE '%中文%') OR (wp_posts.post_content LIKE '%中文%')) OR (wp_posts.post_title LIKE '%简体 繁體 中文%') OR (wp_posts.post_content LIKE '%简体 繁體 中文%')
 *
 * 本函数把$where中的上面这部分替换为:
 *
 * ( ( wp_posts.post_title LIKE '%简体%') OR ( wp_posts.post_content LIKE '%简体%') OR ( wp_posts.post_title LIKE '%简体%') OR ( wp_posts.post_content LIKE '%简体%') ) AND ( ( wp_posts.post_title LIKE '%繁體%') OR ( wp_posts.post_content LIKE '%繁體%') OR ( wp_posts.post_title LIKE '%繁體%') OR ( wp_posts.post_content LIKE '%繁體%') ) AND ( ( wp_posts.post_title LIKE '%中文%') OR ( wp_posts.post_content LIKE '%中文%') ) OR ( wp_posts.post_title LIKE '%简体 繁體 中文%') OR ( wp_posts.post_content LIKE '%简体 繁體 中文%') OR ( wp_posts.post_title LIKE '%简体 繁體 中文%') OR ( wp_posts.post_content LIKE '%简体 繁體 中文%') OR ( wp_posts.post_title LIKE '%简体 繁體 中文%') OR ( wp_posts.post_content LIKE '%简体 繁體 中文%')
 *
 */
function wpcc_filter_search_rule($where) {
	return \Wpcc\SearchService::filter_search_rule($where);
}

/**
 * ob_start Callback function
 *
 */
function wpcc_ob_callback($buffer) {
	return \Wpcc\FrontendService::ob_callback($buffer);
}

/**
 * Debug Function
 *
 * 要开启本插件Debug, 定义WPCC_DEBUG并调用 Wpcc\Diagnostics::set_debug_data().
 * Debug信息将输出在页面footer位置( wp_footer action)
 *
 */
function wpcc_debug() {
	\Wpcc\FrontendService::debug();
}

/**
 * Parse current request
 * @param object $query 'parse_request' filter' argument, the 'WP' object
 *
 * Core codes of this plugin (modernized; uses WordPress query vars where possible).
 * 本函数获取当前请求中文语言并保存到 $wpcc_target_lang全局变量里.
 * 并且还做其它一些事情.
 *
 */
function wpcc_parse_query($query) {
	\Wpcc\RequestService::parse_query($query);

}

/**
 * 载入繁简转换表.
 *
 * 出于节省内存考虑, 本插件并不总是载入繁简转换表. 而仅在繁简转换页面才这样做.
 */
function wpcc_load_conversion_table() {
	\Wpcc\ConversionTable::load();
}

/**
 * 进行繁简转换. 加载若干filter转换页面内容和内部链接
 *
 */
function wpcc_do_conversion() {
	\Wpcc\ConversionPipeline::do_conversion();
}

/**
 * 在html的body标签class属性里添加当前中文语言代码
 * thanks to chad luo.
 * @since 1.1.13
 *
 */
function wpcc_body_class($classes) {
	return \Wpcc\LocaleService::body_class($classes);
}
add_filter("body_class", "wpcc_body_class");

/**
 * 自動修改html tag 的 lang=""標籤為當前中文語言
 * @since 1.1.16
 *
 */
function wpcc_locale($output, $doctype = 'html') {
	return \Wpcc\LocaleService::locale($output, $doctype);
}
add_filter('language_attributes', 'wpcc_locale');

/**
 * add a WPCC_NC button to html editor toolbar.
 * @since 1.1.14
 */
function wpcc_appthemes_add_quicktags() {
	$wpcc_options = \Wpcc\State::options();
	if ( !empty($wpcc_options['wpcc_no_conversion_qtag']) && wp_script_is('quicktags') ) {
?>
	<script type="text/javascript">
//<![CDATA[
		QTags.addButton('eg_wpcc_nc', 'WPCC_NC', '<!--WPCC_NC_START-->', '<!--WPCC_NC_END-->', null, 'WP Chinese Conversion DO-NOT Convert Tag', 120 );
//]]>
	</script>
<?php
	}
}
add_action( 'admin_print_footer_scripts', 'wpcc_appthemes_add_quicktags' );

/**
 * Function executed when plugin is activated
 *
 * add or update 'wpcc_option' in wp_option table of the wordpress database
 * your current settings will be reserved if you have installed this plugin before.
 *
 */
function wpcc_activate() {
	$current_options = (array) get_option('wpcc_options');
	$wpcc_options = \Wpcc\Config::defaults();

	foreach( $current_options as $key => $value )
		if( isset($wpcc_options[$key]) )
			$wpcc_options[$key] = $value;

	foreach( array('zh-hans' => "hanstip", 'zh-hant' => "hanttip") as $lang => $tip ) {
		if( !empty($current_options[$tip]) )
			$wpcc_options[$tip] = $current_options[$tip];
	}

	// WordPress will automatically add the option if it doesn't exist (first install).
	update_option('wpcc_options', $wpcc_options);
	\Wpcc\State::setOptions($wpcc_options);
}
register_activation_hook(__FILE__, 'wpcc_activate');
