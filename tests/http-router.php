<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
	exit;
}
require __DIR__ . '/bootstrap.php';

do_action('init');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = (object) array('query_vars' => array('variant' => 'zh-hant'));
do_action('parse_request', $query);
do_action('template_redirect');
if ($path === '/json') {
	header('Content-Type: application/json; charset=UTF-8');
	echo '{"value":"汉语"}';
} elseif ($path === '/xml') {
	header('Content-Type: application/xml; charset=UTF-8');
	echo '<value>汉语</value>';
} elseif ($path === '/length') {
	$body = '<p>&#x6c49;语</p>';
	header('Content-Type: text/html; charset=UTF-8');
	header('Content-Length: ' . strlen($body));
	echo $body;
} else {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<a href="/汉语/">汉语</a><script>const value="汉语";</script>';
}
