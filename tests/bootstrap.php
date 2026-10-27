<?php

declare(strict_types=1);

if (!in_array(PHP_SAPI, array('cli', 'cli-server'), true)) {
	exit;
}

$root = $argv[1] ?? getenv('WPCC_WP_ROOT') ?: dirname(__DIR__, 4);
if (!is_file($root . '/wp-includes/version.php')) {
	fwrite(STDERR, "Usage: php tests/run.php /path/to/wordpress\n");
	exit(1);
}
define('ABSPATH', rtrim($root, '/') . '/');
define('WPINC', 'wp-includes');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('WP_PLUGIN_DIR', dirname(__DIR__, 2));
define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
define('WPMU_PLUGIN_URL', 'https://example.test/wp-content/mu-plugins');
define('WP_PLUGIN_URL', 'https://example.test/wp-content/plugins');
define('WP_DEBUG', true);
define('WPCC_DEBUG', true);
define('COOKIEHASH', 'test');
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', false);
define('YEAR_IN_SECONDS', 31536000);

foreach (array('plugin.php', 'load.php', 'compat.php', 'compat-utf8.php', 'utf8.php', 'formatting.php', 'kses.php', 'functions.php', 'http.php', 'link-template.php', 'class-wp-query.php', 'query.php', 'class-wp-rewrite.php', 'class-wpdb.php', 'class-wp-token-map.php', 'class-wp-dependency.php', 'class-wp-dependencies.php', 'class-wp-scripts.php', 'functions.wp-scripts.php') as $file) {
	require_once ABSPATH . WPINC . '/' . $file;
}
foreach (array('html5-named-character-references.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-attribute-token.php', 'class-wp-html-decoder.php', 'class-wp-html-tag-processor.php') as $file) {
	require_once ABSPATH . WPINC . '/html-api/' . $file;
}

function get_locale() { return 'zh_TW'; }
function get_bloginfo($name = '') { return $name === 'language' ? 'zh-TW' : 'UTF-8'; }
function is_user_logged_in() { return false; }
function current_user_can($capability) { return $GLOBALS['test_admin'] ?? false; }
function _x($text, $context, $domain = 'default') { return $text; }
function __($text, $domain = 'default') { return $text; }

$test_options = array('home' => 'https://example.test', 'siteurl' => 'https://example.test', 'blog_charset' => 'UTF-8', 'wpcc_options' => array());
add_filter('pre_option', static function($value, $option) {
	return $GLOBALS['test_options'][$option] ?? '';
}, 10, 2);

class TestDatabase extends wpdb {
	public function __construct() { $this->posts = 'wp_posts'; }
	public function _real_escape($value) { return $this->add_placeholder_escape(addslashes($value)); }
}
class TestQuery extends WP_Query {
	public function search(array $vars): string {
		$this->query_vars = $vars + array('s' => '', 'search_columns' => array(), 'exact' => false, 'sentence' => false);
		$_GET['s'] = $this->query_vars['s'];
		return $this->parse_search($this->query_vars);
	}
}
$wp_plugin_paths = array();
$wpdb = new TestDatabase();
$wp_rewrite = new WP_Rewrite();
$wp_rewrite->use_trailing_slashes = true;
$wp_rewrite->permalink_structure = '/%postname%/';
$wp_query = new WP_Query();
$wp_the_query = $wp_query;
$_SERVER['REQUEST_METHOD'] ??= 'GET';
$_SERVER['HTTP_USER_AGENT'] ??= 'Mozilla/5.0';
$_SERVER['REQUEST_URI'] ??= '/';
require dirname(__DIR__) . '/wp-chinese-conversion.php';

set_error_handler(static function(int $severity, string $message, string $file, int $line): bool {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});
