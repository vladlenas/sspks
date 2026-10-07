<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use SSpkS\App;
use SSpkS\Config;

// Dates in statistics follow the container's TZ (e.g. TZ=Europe/Vilnius).
$timezone = getenv('TZ');
date_default_timezone_set(is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC');

try {
    $config = Config::fromEnvironment(dirname(__DIR__) . '/config.local.php');
    (new App($config, $_SERVER, __DIR__))->handle($_GET, $_GET + $_POST);
} catch (\Throwable $e) {
    error_log('sspks: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Internal error, see the container log for details.\n";
}
