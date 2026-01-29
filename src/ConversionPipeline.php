<?php

declare(strict_types=1);

namespace Wpcc;

class ConversionPipeline {
	public static function do_conversion(): void {
		$options = State::options();
		$direct_flag = Context::directConversionFlag();

		wpcc_load_conversion_table();

		add_action('wp_head', 'wpcc_header');

		if (!$direct_flag) {
			remove_action('wp_head', 'rel_canonical');
			add_action('wp_head', 'wpcc_rel_canonical');

			add_filter('post_link', 'wpcc_link_conversion');
			add_filter('month_link', 'wpcc_link_conversion');
			add_filter('day_link', 'wpcc_link_conversion');
			add_filter('year_link', 'wpcc_link_conversion');
			add_filter('page_link', 'wpcc_link_conversion');
			add_filter('tag_link', 'wpcc_link_conversion');
			add_filter('category_link', 'wpcc_link_conversion');
			add_filter('feed_link', 'wpcc_link_conversion');
			add_filter('search_feed_link', 'wpcc_link_conversion');

			add_filter('category_feed_link', 'wpcc_fix_link_conversion');
			add_filter('tag_feed_link', 'wpcc_fix_link_conversion');
			add_filter('author_feed_link', 'wpcc_fix_link_conversion');
			add_filter('post_comments_feed_link', 'wpcc_fix_link_conversion');
			add_filter('get_comments_pagenum_link', 'wpcc_fix_link_conversion');
			add_filter('get_comment_link', 'wpcc_fix_link_conversion');

			add_filter('attachment_link', 'wpcc_cancel_link_conversion');

			add_filter('get_pagenum_link', 'wpcc_pagenum_link_fix');
			add_filter('redirect_canonical', 'wpcc_cancel_incorrect_redirect', 10, 2);
		}

		if (!empty($options['wpcc_no_conversion_ja']) || !empty($options['wpcc_no_conversion_tag'])) {
			add_filter('the_content', 'wpcc_no_conversion_filter', 15);
			add_filter('the_content_rss', 'wpcc_no_conversion_filter', 15);
		}

		if (!empty($options['wpcc_use_fullpage_conversion'])) {
			ob_start('wpcc_ob_callback');
			return;
		}

		add_filter('the_content', 'zhconversion2', 20);
		add_filter('the_content_rss', 'zhconversion2', 20);
		add_filter('the_excerpt', 'zhconversion2', 20);
		add_filter('the_excerpt_rss', 'zhconversion2', 20);

		add_filter('the_title', 'zhconversion');
		add_filter('comment_text', 'zhconversion');
		add_filter('bloginfo', 'zhconversion');
		add_filter('the_tags', 'zhconversion_deep');
		add_filter('term_links-post_tag', 'zhconversion_deep');
		add_filter('wp_tag_cloud', 'zhconversion');
		add_filter('the_category', 'zhconversion');
		add_filter('list_cats', 'zhconversion');
		add_filter('category_description', 'zhconversion');
		add_filter('single_cat_title', 'zhconversion');
		add_filter('single_post_title', 'zhconversion');
		add_filter('bloginfo_rss', 'zhconversion');
		add_filter('the_title_rss', 'zhconversion');
		add_filter('comment_text_rss', 'zhconversion');
	}
}
