<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit;
}
$root = $argv[1] ?? getenv('WPCC_WP_ROOT') ?: dirname(__DIR__, 4);
putenv('WPCC_WP_ROOT=' . $root);
$socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
if (!$socket) {
	throw new RuntimeException($message);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = proc_open(array(PHP_BINARY, '-S', $address, __DIR__ . '/http-router.php'), array(array('pipe', 'r'), array('file', '/dev/null', 'w'), array('file', '/dev/null', 'w')), $pipes);
if (!is_resource($server)) {
	throw new RuntimeException('Could not start PHP server.');
}
try {
	$ready = false;
	for ($attempt = 0; $attempt < 100; $attempt++) {
		$connection = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
		if ($connection) {
			fclose($connection);
			$ready = true;
			break;
		}
		usleep(10000);
	}
	if (!$ready) {
		throw new RuntimeException('PHP server did not start.');
	}
	$expected = array(
		'/html' => '<a href="/汉语/">漢語</a><script>const value="汉语";</script>',
		'/json' => '{"value":"汉语"}',
		'/xml' => '<value>汉语</value>',
		'/length' => '<p>漢語</p>',
	);
	foreach ($expected as $path => $body) {
		$response = file_get_contents('http://' . $address . $path);
		if ($response !== $body) {
			throw new RuntimeException("Unexpected $path response: " . var_export($response, true));
		}
	}
	echo "HTTP checks passed for HTML, JSON, XML, and response lengths.\n";
} finally {
	fclose($pipes[0]);
	proc_terminate($server);
	proc_close($server);
}
