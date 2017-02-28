<?php
namespace Luminance;
$load = sys_getloadavg();
if ($load[0] > 30) {
    header('HTTP/1.1 503 Too busy, try again later');
    die('Server too busy. Please try again later.');
}


require __DIR__ . '/../vendor/autoload.php';

$startTime = microtime(true);

date_default_timezone_set('UTC');

define('SERVER_ROOT', __DIR__);

# Inject superglobals right at the start so we can avoid globals in the rest of the code
$superglobals = [
    'server' => $_SERVER,
    'get' => $_GET,
    'post' => $_POST,
    'files' => $_FILES,
    'cookie' => $_COOKIE,
    'request' => $_REQUEST,
    'env' => $_ENV
];

global $master;
$master = new \Luminance\Core\Master(__DIR__, $superglobals, $startTime);
$master->run();
