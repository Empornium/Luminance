<?php

/**
 * Luminance Scheduler v2
 * This is only a code showcase and should NOT be used in production.
 */

// Make sure we don't run this in production mode
if (!$master->settings->site->debug_mode) {
    exit("Please, do not use this feature in production mode.\n");
}

require 'classes/SchedulerException.php';
require 'classes/SchedulerLog.php';
require 'classes/Scheduler.php';
require 'classes/SchedulerRegistry.php';
require 'classes/SchedulerWatch.php';
require 'classes/ScheduledCommandInterface.php';
require 'classes/ScheduledCommand.php';

// TODO: autoloading
require 'commands/PurgeRequests.php';

//function register_classes($dirs)
//{
//    if (!is_array($dirs)) {
//        $dirs = [$dirs];
//    }
//
//    foreach ($dirs as $dir) {
//        $dir = __DIR__."/{$dir}";
//
//        if (!is_dir($dir)) {
//            throw new \Exception("$dir is not a directory.");
//        }
//
//        $files = glob("{$dir}/*.php");
//        foreach ($files as $file) {
//            //require_once($file);
//        }
//    }
//}
//
//register_classes('commands');


$logger = new SchedulerLog();
$logger->log('Starting scheduler...');

try {
    $scheduler = new Scheduler($master, $logger);
    $scheduler->start();
} catch (\Exception $e) {
    $logger->log('ERROR: '.$e->getMessage());
}

echo $logger->out();