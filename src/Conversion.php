<?php

declare(strict_types=1);

namespace Wpcc;

class Conversion {
	public static function register(): void {
		$wpcc_options = State::options();

		if (!empty($wpcc_options['wpcc_used_langs']) && is_array($wpcc_options['wpcc_used_langs'])) {
			add_action('widgets_init', function() {
				register_widget('Wpcc_Widget');
			});
			add_filter('query_vars', 'wpcc_insert_query_vars');
			add_action('init', 'wpcc_init');

			if (WP_DEBUG || (defined('WPCC_DEBUG') && WPCC_DEBUG === true)) {
				add_action('wp_footer', 'wpcc_debug');
			}
		}
	}
}
