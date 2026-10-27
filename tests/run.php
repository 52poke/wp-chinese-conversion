<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Wpcc\Config;
use Wpcc\Context;
use Wpcc\ConversionService;
use Wpcc\FrontendService;
use Wpcc\LinkService;
use Wpcc\RequestService;
use Wpcc\SearchService;

$checks = 0;
$failures = array();
function same($expected, $actual, string $label): void {
	$GLOBALS['checks']++;
	if ($expected !== $actual) {
		$GLOBALS['failures'][] = $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
	}
}
function contains(string $needle, string $haystack, string $label): void {
	same(true, str_contains($haystack, $needle), $label);
}
function options(array $overrides = array()): void {
	Config::setOptions(Config::normalize($overrides));
	Context::initDefaults();
}

same(false, isset($GLOBALS['zh2Hans']), 'conversion tables load lazily');
same('<!--WPCC_NC_START-->汉语<!--WPCC_NC_END-->', zhconversion2('<!--WPCC_NC_START-->汉语<!--WPCC_NC_END-->'), 'inactive conversion leaves markers untouched');
same('汉语', zhconversion('漢語', 'zh-hans'), 'simplified conversion');
same('漢語', zhconversion('汉语', 'zh-hant'), 'traditional conversion');
same('汉语', zhconversion('汉语', 'unknown'), 'unsupported variant is unchanged');
same(array('漢語', false, 3, null), zhconversion_deep(array('漢語', false, 3, null)), 'deep conversion preserves nonstrings');
same(array('汉语'), zhconversion_all('漢語', array('missing', 'zh-hans')), 'invalid conversion variants are ignored');
same('A b c d E', limit_zhconversion('a <!--WPCC_NC_START-->b <!--WPCC_NC4_START-->c<!--WPCC_NC4_END--> d<!--WPCC_NC_END--> e', 'strtoupper'), 'nested exclusions');
same('A b', limit_zhconversion('a <!--WPCC_NC_START-->b', 'strtoupper'), 'unfinished exclusion preserves remaining text');

options(array('wpcc_no_conversion_tag' => 'pre,code', 'wpcc_no_conversion_ja' => 1));
$html = '<p>汉语</p><pre data-x="a&b"><code>汉语<br>汉语 &amp; A</code></pre><span LANG="ja-JP">汉语 &nbsp;</span><p>汉语</p>';
$expected = '<p>漢語</p><pre data-x="a&b"><code>汉语<br>汉语 &amp; A</code></pre><span LANG="ja-JP">汉语 &nbsp;</span><p>漢語</p>';
same($expected, zhconversion2(wpcc_no_conversion_filter($html), 'zh-hant'), 'excluded HTML preserves markup and entities');
$html = '<!-- wp:html --><table><tr><td><code>汉语 &amp; <br></code></td></tr></table><!-- /wp:html -->';
same($html, zhconversion2(wpcc_no_conversion_filter($html), 'zh-hant'), 'table and block comments survive exclusions');
same('<p>漢語</p><pre>汉语', zhconversion2(wpcc_no_conversion_filter('<p>汉语</p><pre>汉语'), 'zh-hant'), 'unclosed excluded tag');

$normalized = Config::normalize(array('wpcc_used_langs' => array('zh-hant', array('bad')), 'wpcc_use_permalink' => 99, 'wpcc_browser_redirect' => array(), 'nctip' => array(), 'wpcc_no_conversion_tag' => ' PRE | code, .invalid,pre,script', 'hanstip' => '<b>A & B</b>'));
same(array('zh-hant'), $normalized['wpcc_used_langs'], 'enabled variants validated');
same(0, $normalized['wpcc_use_permalink'], 'invalid enum rejected');
same(0, $normalized['wpcc_browser_redirect'], 'array option rejected');
same('', $normalized['nctip'], 'array label rejected');
same('pre,code,script', $normalized['wpcc_no_conversion_tag'], 'tag options normalized');
same('A & B', $normalized['hanstip'], 'labels sanitized');
same(array(), Config::normalize(array('wpcc_used_langs' => array()))['wpcc_used_langs'], 'all variants may be disabled');

foreach (array('', '/Blog.Sub') as $base) {
	$test_options['home'] = 'https://example.test' . $base;
	foreach (array(0, 1, 2) as $mode) {
		options(array('wpcc_use_permalink' => $mode));
		$original = $test_options['home'] . '/MixedCase/';
		$expected = $mode === 0 ? $original . '?variant=zh-hant' : ($mode === 1 ? $original . 'zh-hant/' : $test_options['home'] . '/zh-hant/MixedCase/');
		same($expected . '#Section', wpcc_link_conversion($original . '#Section', 'zh-hant'), "URL mode $mode at $base");
		same($expected, wpcc_link_conversion($expected, 'zh-hant'), "idempotent URL mode $mode at $base");
		same($original, wpcc_cancel_link_conversion($expected), "canonical URL mode $mode at $base");
		same($original . '?Token=AbC#Section', wpcc_cancel_link_conversion(wpcc_link_conversion($original . '?Token=AbC#Section', 'zh-hant')), "query and fragment mode $mode at $base");
		$_SERVER['REQUEST_URI'] = substr($expected, strlen('https://example.test'));
		same($original, wpcc_get_noconversion_url(), "request URL mode $mode at $base");
		same('https://outside.test/Post/', wpcc_link_conversion('https://outside.test/Post/', 'zh-hant'), 'external URL unchanged');
		same('mailto:user@example.test', wpcc_link_conversion('mailto:user@example.test', 'zh-hant'), 'non-HTTP URL unchanged');
	}
}
$test_options['home'] = 'https://example.test';
options(array('wpcc_use_permalink' => 1));
same('https://example.test/Post/feed/zh-hant/#comments', wpcc_fix_link_conversion('https://example.test/Post/zh-hant/feed/#comments'), 'feed fragment preserved');
same('https://example.test/Post/feed/zh-hant/', wpcc_fix_link_conversion('https://example.test/Post/zh-hant/feed/'), 'feed URL without fragment');
same('https://example.test/Post/zh-hant/', wpcc_link_conversion('https://example.test/Post/zh-hans/?variant=zh-hans', 'zh-hant'), 'variant switching removes old variant');

$rules = array('^fixed/?$' => 'index.php?pagename=fixed', '^([a-z]+)/([0-9]+)/?$' => 'index.php?id=$matches[2]', '^([a-z]+)/?$' => 'index.php?name=$matches[1]&again=$matches[1]');
$rewritten = wpcc_rewrite_rules($rules);
same('index.php?pagename=fixed&variant=$matches[1]', $rewritten['^fixed/(zh\-hans|zh\-hant|zh|zh\-reset)/?$'] ?? null, 'static routes get variant');
same('index.php?id=$matches[2]&variant=$matches[3]', $rewritten['^([a-z]+)/([0-9]+)/(zh\-hans|zh\-hant|zh|zh\-reset)/?$'] ?? null, 'unreferenced capture groups counted');
same('index.php?name=$matches[1]&again=$matches[1]&variant=$matches[2]', $rewritten['^([a-z]+)/(zh\-hans|zh\-hant|zh|zh\-reset)/?$'] ?? null, 'duplicate references counted once');
options(array('wpcc_use_permalink' => 2));
$rewritten = wpcc_rewrite_rules($rules);
same('index.php?id=$matches[3]&variant=$matches[1]', $rewritten['^(zh\-hans|zh\-hant|zh|zh\-reset)/([a-z]+)/([0-9]+)/?$'] ?? null, 'prefix route capture references shifted');
options();
same($rules, wpcc_rewrite_rules($rules), 'query mode adds no rewrite routes');

$langs = array('zh-hans', 'zh-hant');
same('zh-hant', RequestService::get_prefered_language('zh-hans;q=0,zh-hant;q=0.8', $langs), 'q=0 language ignored');
same(false, RequestService::get_prefered_language('ja,zh-hant;q=0.8', $langs), 'preferred non-Chinese language respected');
same('zh-hant', RequestService::get_prefered_language('zh-Hant-TW,zh-Hans;q=0.8', $langs), 'script and region language recognized');
same('zh-hant', RequestService::get_prefered_language('zh-TW,zh-CN;q=0.8', $langs, 1), 'regional aliases preserve quality order');
same('zh-hant', RequestService::get_prefered_language('zh-TW', $langs), 'standard browser region maps to script variant');

$options_query = new TestQuery();
foreach (array(array('s' => '汉语'), array('s' => '汉语', 'exact' => true), array('s' => '汉语 -语言'), array('s' => "汉语' OR 1=1 --"), array('s' => 'x汉y'), array('s' => '汉语', 'search_columns' => array('post_title'))) as $vars) {
	$core = $options_query->search($vars);
	$converted = $wpdb->remove_placeholder_escape(SearchService::filter_search_rule($core, $options_query));
	contains('漢', $converted, 'query expands Han characters: ' . $vars['s']);
	contains("AND (wp_posts.post_password = '')", $converted, 'password restriction retained');
	if (!empty($vars['exact'])) {
		same(false, str_contains($converted, '%'), 'exact search remains exact');
	}
	if (!empty($vars['search_columns'])) {
		same(false, str_contains($converted, 'post_content'), 'search column selection respected');
	}
	if (str_contains($vars['s'], '-语言')) {
		contains("post_title NOT LIKE '%語言%'", $converted, 'negative variants excluded');
	}
}
$core = $options_query->search(array('s' => 'English'));
same($core, SearchService::filter_search_rule($core, $options_query), 'non-Chinese search unchanged');
$wp_query->query_vars['s'] = 'English';
$core = $options_query->search(array('s' => '汉语'));
contains('漢語', SearchService::filter_search_rule($core, $options_query), 'secondary query uses own terms');

options(array('hanstip' => 'A & B'));
Context::setNoconversionUrl('https://example.test/Post/');
ob_start();
$navigation = wpcc_output_navi('echo=0');
same('', ob_get_clean(), 'echo=0 emits nothing');
contains('A &amp; B', $navigation, 'navigation label escaped once');
same(false, str_contains($navigation, '&amp;amp;'), 'navigation label not double escaped');
options(array('wpcc_used_langs' => array('zh-hant')));
ob_start();
$navigation = wpcc_output_navi('mode=wrap&echo=0');
same('', ob_get_clean(), 'wrap mode respects echo=0');
same(false, str_contains($navigation, 'zh-hans'), 'wrap mode respects enabled variants');
contains('id="wpcc_hant_link"', $navigation, 'wrap navigation keeps legacy element IDs');

options();
Context::setTargetLang('zh-hant');
$html = '<p>汉语</p><script>const title="汉语";</script><style>p:before{content:"汉语"}</style>';
same('<p>漢語</p><script>const title="汉语";</script><style>p:before{content:"汉语"}</style>', FrontendService::ob_callback($html), 'full-page conversion preserves scripts and styles');
same('<a id="汉语" href="/汉语/" data-label="汉语" title="漢語">漢語</a>', FrontendService::ob_callback('<a id="汉语" href="/汉语/" data-label="汉语" title="汉语">汉语</a>'), 'HTML conversion preserves machine attributes');
same('<title>漢語</title><textarea name="汉语">漢語</textarea>', FrontendService::ob_callback('<title>汉语</title><textarea name="汉语">汉语</textarea>'), 'raw text display elements convert');
same('<input type="submit" value="漢語"><input name="汉语" value="汉语"><meta name="description" content="漢語"><meta property="og:url" content="/汉语/">', FrontendService::ob_callback('<input type="submit" value="汉语"><input name="汉语" value="汉语"><meta name="description" content="汉语"><meta property="og:url" content="/汉语/">'), 'button labels and descriptions convert without changing field values or metadata URLs');
Context::setNoconversionUrl('https://example.test/--><script>alert(1)</script>');
$GLOBALS['test_admin'] = true;
ob_start();
wpcc_debug();
$debug = ob_get_clean();
same(1, substr_count($debug, '-->'), 'debug data cannot terminate HTML comment');
same(false, str_contains($debug, '<script>'), 'debug data cannot inject markup');

options(array('wpcc_browser_redirect' => 2));
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'zh-TW';
$_SERVER['REQUEST_URI'] = '/MixedCase/?Token=AbC';
$_COOKIE = array();
RequestService::parse_query((object) array('query_vars' => array()));
same('zh-hant', Context::targetLang(), 'browser direct conversion');
same(true, Context::directConversionFlag(), 'browser direct mode');
same('https://example.test/MixedCase/?Token=AbC', Context::noconversionUrl(), 'request URL case preserved');
RequestService::parse_query((object) array('query_vars' => array('variant' => array('zh-hant'))));
same('zh-hant', Context::targetLang(), 'malformed variant does not crash browser detection');
RequestService::parse_query((object) array('query_vars' => array('rest_route' => '/wp/v2/posts')));
same(false, Context::targetLang(), 'REST requests skip conversion');
options(array('wpcc_browser_redirect' => 1));
$_SERVER['REQUEST_METHOD'] = 'POST';
RequestService::parse_query((object) array('query_vars' => array()));
same(false, Context::redirectTo(), 'POST requests never automatically redirect');
$_SERVER['REQUEST_METHOD'] = 'GET';
options(array('wpcc_use_cookie_variant' => 2));
$_COOKIE['wpcc_variant_test'] = 'zh-hans';
RequestService::parse_query((object) array('query_vars' => array()));
same('zh-hans', Context::targetLang(), 'remembered preference selects direct variant');
RequestService::parse_query((object) array('query_vars' => array('variant' => 'zh-hant')));
same('zh-hant', Context::targetLang(), 'explicit variant overrides preference');

options(array('wpcc_use_permalink' => 1));
same('#汉语', wpcc_link_conversion('#汉语', 'zh-hant'), 'fragment-only links unchanged');
same('Relative/', wpcc_link_conversion('Relative/', 'zh-hant'), 'path-relative links unchanged');
same('https://external.test/?variant=custom', wpcc_cancel_link_conversion('https://external.test/?variant=custom'), 'external query parameters preserved');
same('https://example.test/Post/2/', wpcc_cancel_link_conversion('https://example.test/Post/zh-hant/2/'), 'paginated canonical removes embedded variant');
same('https://example.test/Post/?cpage=2&variant=zh-hant#comment-5', wpcc_fix_link_conversion('https://example.test/Post/zh-hant/?cpage=2#comment-5'), 'comment query and fragment preserved');
foreach (array(1, 2) as $mode) {
	options(array('wpcc_use_permalink' => $mode));
	same('https://example.test/zh-hant/', wpcc_link_conversion('https://example.test/', 'zh-hant'), 'homepage always has trailing slash');
	$wp_rewrite->use_trailing_slashes = false;
	$url = $mode === 1 ? 'https://example.test/Post/zh-hant' : 'https://example.test/zh-hant/Post';
	same($url, wpcc_link_conversion('https://example.test/Post', 'zh-hant'), 'no trailing slash permalinks');
	$wp_rewrite->use_trailing_slashes = true;
}
same(0, Config::normalize(array('wpcc_use_fullpage_conversion' => false))['wpcc_use_fullpage_conversion'], 'false boolean remains disabled');
RequestService::parse_query((object) array('query_vars' => array('variant' => 'zh-hant', 'sitemap' => 'posts')));
same(false, Context::targetLang(), 'sitemap requests skip conversion');

options();
Context::setTargetLang('zh-hant');
Context::setNoconversionUrl('https://example.test/</script>');
do_action('init');
FrontendService::enqueue_scripts();
same(true, wp_script_is('wpcc-search-js', 'enqueued'), 'search script enqueued');
same(1, wp_scripts()->get_data('wpcc-search-js', 'group'), 'search script prints in footer');
$inline = wp_scripts()->get_inline_script_data('wpcc-search-js', 'before');
same(false, str_contains($inline, '</script>'), 'inline data cannot close script tag');
contains('window.wpcc_target_lang="zh-hant"', $inline, 'inline variant available before search script');

$wp_query->is_feed = true;
$level = ob_get_level();
\Wpcc\FrontendService::do_conversion();
same($level, ob_get_level(), 'feeds never start HTML output buffer');
same('<a href="/汉语/">漢語</a>', apply_filters('the_content', '<a href="/汉语/">汉语</a>'), 'content-only conversion preserves URLs');
$registered = count($wp_filter['the_content']->callbacks[20]);
\Wpcc\FrontendService::do_conversion();
same($registered, count($wp_filter['the_content']->callbacks[20]), 'conversion pipeline starts once');

if ($failures) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}
echo "$checks checks passed.\n";
