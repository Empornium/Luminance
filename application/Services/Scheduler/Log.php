<?php
namespace Luminance\Services\Scheduler;

/**
 * The Scheduler logger provides flexibility in the way we use tasks logs.
 * It could echo right away (CLI), or save them for later (HTTP)
 *
 * Default behavior: it saves all logs in an array and wait for the out() method to be called
 */
class Log {
    public $logs = [];

    public function log($str) {
        $this->logs[] = $str;
    }

    public function out($flush = true) {
        $str = implode("\n", $this->logs)."\n";

        if ($flush === true) {
            $this->flush();
        }

        return $str;
    }

    public function flush() {
        $this->logs = [];
    }
}
