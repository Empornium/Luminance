<?php

require_once(__DIR__ . '/../application/common/main_functions.php');
set_error_handler('composer_unfound');
require __DIR__ . '/../vendor/autoload.php';
restore_error_handler();

date_default_timezone_set('UTC');
