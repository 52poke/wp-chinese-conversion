<?php

declare(strict_types=1);

namespace Wpcc;

class FrontendService {
	private static bool $started = false;

	public static function enqueue_scripts(): void {
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		$inline = 'window.wpcc_target_lang=' . wp_json_encode(Context::targetLang(), $flags) . ';';
		$inline .= 'window.wpcc_noconversion_url=' . wp_json_encode(Context::noconversionUrl(), $flags) . ';';
		$inline .= 'window.wpcc_langs_urls=' . wp_json_encode(Context::langsUrls(), $flags) . ';';
		wp_enqueue_script('wpcc-search-js', WPCC_ROOT_URL . 'search-variant.min.js', array(), WPCC_VERSION, true);
		wp_add_inline_script('wpcc-search-js', $inline, 'before');
	}

	public static function header(): void {
		if (!Context::directConversionFlag()) {
			echo '<meta name="robots" content="noindex,follow" />' . "\n";
		}
	}

	public static function canonical(): void {
		if (!is_singular()) {
			return;
		}
		$link = wp_get_canonical_url();
		if ($link) {
			echo '<link rel="canonical" href="' . esc_url(LinkService::cancel_link_conversion($link)) . '" />' . "\n";
		}
	}

	public static function ob_callback($buffer) {
		foreach (headers_list() as $header) {
			if (stripos($header, 'Content-Type:') === 0 && !preg_match('#^Content-Type:\\s*(?:text/html|application/xhtml\\+xml)(?:;|$)#i', $header)) {
				return $buffer;
			}
		}
		if (!Context::targetLang()) {
			return $buffer;
		}
		$converted = HtmlConverter::convert($buffer, null, true);
		if ($converted !== $buffer && !headers_sent()) {
			header_remove('Content-Length');
		}
		return $converted;
	}

	public static function debug(): void {
		Diagnostics::debug_output();
	}

	public static function do_conversion(): void {
		if (self::$started || !Context::targetLang()) {
			return;
		}
		self::$started = true;
		$options = Config::options();
		$direct_flag = Context::directConversionFlag();

		add_action('wp_enqueue_scripts', array(self::class, 'enqueue_scripts'));
		add_action('wp_head', array(self::class, 'header'));

		if (!$direct_flag) {
			remove_action('wp_head', 'rel_canonical');
			add_action('wp_head', array(self::class, 'canonical'));

			foreach (array('post_link', 'post_type_link', 'term_link', 'author_link', 'month_link', 'day_link', 'year_link', 'page_link', 'tag_link', 'category_link', 'feed_link', 'search_feed_link') as $hook) {
				add_filter($hook, array(LinkService::class, 'link_conversion'));
			}
			foreach (array('category_feed_link', 'tag_feed_link', 'author_feed_link', 'post_comments_feed_link', 'get_comments_pagenum_link', 'get_comment_link') as $hook) {
				add_filter($hook, array(LinkService::class, 'fix_link_conversion'));
			}
			add_filter('attachment_link', array(LinkService::class, 'cancel_link_conversion'));

			add_filter('get_pagenum_link', array(LinkService::class, 'pagenum_link_fix'));
			add_filter('redirect_canonical', array(LinkService::class, 'cancel_incorrect_redirect'), 10, 2);
		}

		if (!empty($options['wpcc_no_conversion_ja']) || !empty($options['wpcc_no_conversion_tag'])) {
			add_filter('the_content', array(ConversionService::class, 'wpcc_no_conversion_filter'), 15);
			add_filter('the_content_rss', array(ConversionService::class, 'wpcc_no_conversion_filter'), 15);
		}

		if (!empty($options['wpcc_use_fullpage_conversion']) && !is_feed()) {
			ob_start(array(self::class, 'ob_callback'));
			return;
		}

		add_filter('the_content', array(HtmlConverter::class, 'convert'), 20);
		add_filter('the_content_rss', array(HtmlConverter::class, 'convert'), 20);
		add_filter('the_excerpt', array(HtmlConverter::class, 'convert'), 20);
		add_filter('the_excerpt_rss', array(HtmlConverter::class, 'convert'), 20);

		foreach (array('the_title', 'bloginfo', 'list_cats', 'single_cat_title', 'single_post_title', 'bloginfo_rss', 'the_title_rss') as $hook) {
			add_filter($hook, array(ConversionService::class, 'zhconversion'));
		}
		foreach (array('comment_text', 'comment_text_rss', 'wp_tag_cloud', 'the_category', 'category_description') as $hook) {
			add_filter($hook, array(HtmlConverter::class, 'convert'));
		}
		foreach (array('the_tags', 'term_links-post_tag') as $hook) {
			add_filter($hook, array(HtmlConverter::class, 'convert_deep'));
		}
	}

	public static function variant_attribute($default = 'zh', $variant = false) {
		$langs = Config::langs();
		if (!$variant) {
			$variant = Context::targetLang();
		}
		if (!$variant) {
			return $default;
		}
		return $langs[$variant][3] ?? $default;
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
		$langs = Config::langs();
		$lang = get_bloginfo('language');
		$target = Context::targetLang();
		if ($target && preg_match('/^zh(?:[-_]|$)/i', $lang)) {
			$lang = $langs[$target][3];
			$output = preg_replace('/lang="[^"]+"/', "lang=\"{$lang}\"", $output);
		}
		return $output;
	}

	public static function output_navi($args = ''): string {
		$args = wp_parse_args($args, array('mode' => 'normal', 'echo' => 1));
		$options = Config::options();
		$target = Context::targetLang();
		$original = Context::noconversionUrl() ?: home_url('/');
		if ($target && ($options['wpcc_browser_redirect'] === 2 || $options['wpcc_use_cookie_variant'] === 2)) {
			$original = LinkService::link_conversion($original, 'zh');
		}
		$locale = str_replace('_', '-', strtolower(get_locale()));
		$tip = $options['nctip'] ?: (preg_match('/^zh-(?:hant|tw|hk|mo)(?:-|$)/', $locale) ? '不轉換' : '不转换');
		$tip = ConversionService::zhconversion($tip);
		$links = array('original' => array($original, $tip));
		foreach ($options['wpcc_used_langs'] as $variant) {
			$language = Config::langs()[$variant];
			$tip = $options[$language[1]] ?: $language[2];
			$links[$variant] = array(Context::langsUrls()[$variant] ?? LinkService::link_conversion(Context::noconversionUrl() ?: home_url('/'), $variant), $tip);
		}
		$output = "\n<div id=\"wpcc_widget_inner\"><!--WPCC_NC_START-->\n";
		foreach ($links as $variant => [$url, $label]) {
			$current = $variant === 'original' ? !$target : $target === $variant;
			$id = $args['mode'] === 'wrap' && $variant !== 'original' ? substr($variant, 3) : $variant;
			$output .= '<span id="wpcc_' . esc_attr($id) . '_link" class="' . ($current ? 'wpcc_current_lang' : 'wpcc_lang') . '"><a class="wpcc_link"' . ($variant === 'original' ? '' : ' rel="nofollow"') . ' href="' . esc_url($url) . '" title="' . esc_attr($label) . '">' . esc_html($label) . "</a></span>\n";
		}
		$output .= "<!--WPCC_NC_END--></div>\n";
		if ($args['echo']) {
			echo $output;
		}
		return $output;
	}

	public static function output_navi2(): string {
		return self::output_navi(array('mode' => 'wrap'));
	}
}
