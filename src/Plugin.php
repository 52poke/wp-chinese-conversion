<?php

declare(strict_types=1);

namespace Wpcc;

class Plugin {
	private static bool $registered = false;
	private static ?AdminPage $adminPage = null;

	public static function bootstrap(): void {
		spl_autoload_register(static function(string $class): void {
			if (str_starts_with($class, __NAMESPACE__ . '\\')) {
				$file = __DIR__ . '/' . substr($class, strlen(__NAMESPACE__) + 1) . '.php';
				if (is_file($file)) {
					require_once $file;
				}
			}
		});
		require_once __DIR__ . '/functions.php';
		Config::setOptions(Config::normalize(get_option('wpcc_options')));
		Context::initDefaults();
	}

	public static function register(): void {
		if (self::$registered) {
			return;
		}
		self::$registered = true;
		add_action('admin_menu', array(self::class, 'admin_menu'));
		add_action('admin_print_footer_scripts', 'wpcc_appthemes_add_quicktags');
		add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/wp-chinese-conversion.php'), static function(array $links): array {
			$links[] = '<a href="' . esc_url(admin_url('options-general.php?page=' . plugin_basename(dirname(__DIR__) . '/wp-chinese-conversion.php'))) . '">Settings</a>';
			return $links;
		});
		self::register_frontend();
	}

	public static function activate(): void {
		global $wp_rewrite;
		$options = Config::normalize(get_option('wpcc_options'));
		if (empty($wp_rewrite->permalink_structure)) {
			$options['wpcc_use_permalink'] = 0;
		}
		update_option('wpcc_options', $options);
		Config::setOptions($options);
		add_filter('rewrite_rules_array', array(LinkService::class, 'rewrite_rules'));
		flush_rewrite_rules(false);
	}

	public static function deactivate(): void {
		remove_filter('rewrite_rules_array', array(LinkService::class, 'rewrite_rules'));
		remove_filter('rewrite_rules_array', 'wpcc_rewrite_rules');
		flush_rewrite_rules(false);
	}

	public static function admin_menu(): void {
		self::$adminPage ??= new AdminPage();
	}

	private static function register_frontend(): void {
		if (empty(Config::options()['wpcc_used_langs'])) {
			return;
		}
		add_filter('body_class', array(FrontendService::class, 'body_class'));
		add_filter('language_attributes', array(FrontendService::class, 'locale'), 10, 2);
		add_action('widgets_init', static function(): void {
			require_once __DIR__ . '/Widget.php';
			register_widget('Wpcc_Widget');
		});
		add_filter('query_vars', static function(array $vars): array {
			$vars[] = 'variant';
			return array_unique($vars);
		});
		add_action('init', array(RequestService::class, 'init'));
		if (defined('WPCC_DEBUG') && WPCC_DEBUG === true) {
			add_action('wp_footer', array(Diagnostics::class, 'debug_output'));
		}
	}
}
