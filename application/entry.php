<?php
namespace Luminance;

if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    if ($load[0] > 30) {
        header('HTTP/1.1 503 Too busy, try again later');
        die('Server too busy. Please try again later.');
    }
}

require_once(__DIR__ . '/common/main_functions.php');
set_error_handler('composer_unfound');
require __DIR__ . '/../vendor/autoload.php';
restore_error_handler();

$startTime = microtime(true);

date_default_timezone_set('UTC');

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
