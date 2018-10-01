<?php


abstract class ScheduledCommand implements ScheduledCommandInterface
{
    protected $exec_limit = 840;
    protected $master;
    protected $logger;

    public function __construct(\Luminance\Core\Master $master, SchedulerLog $logger)
    {
        $this->master = $master;
        $this->logger = $logger;
    }
}