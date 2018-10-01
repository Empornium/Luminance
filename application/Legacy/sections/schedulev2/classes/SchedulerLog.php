<?php


/**
 * The Scheduler logger provides flexibility in the way we use commands logs.
 * It could echo right away (CLI), or save them for later (HTTP)
 *
 * Default behavior: it saves all logs in an array and wait for the out() method to be called
 */
class SchedulerLog
{
    public $logs = [];

    public function log($str)
    {
        $this->logs[] = $str;
    }

    public function out($flush = true)
    {
        $str = implode("\n", $this->logs)."\n";

        if ($flush) {
            $this->flush();
        }

        return $str;
    }

    public function flush()
    {
        $this->logs = [];
    }
}