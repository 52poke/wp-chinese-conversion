<?php

declare(strict_types=1);

namespace Wpcc;

class Admin {
	private static $instance = null;

	public static function register(): void {
		add_action('admin_menu', array(self::class, 'init'));
	}

	public static function init(): void {
		if (!class_exists('Wpcc_Admin')) {
			require_once __DIR__ . '/AdminPage.php';
		}
		self::$instance = new \Wpcc_Admin();
	}
}
