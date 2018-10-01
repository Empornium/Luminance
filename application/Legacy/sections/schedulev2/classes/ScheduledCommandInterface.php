<?php


/**
 * Scheduled Command Interface
 * Assure us all commands have what they need to run properly.
 */
interface ScheduledCommandInterface
{
    /**
     * A ScheduledCommand constructor.
     *
     * @param \Luminance\Core\Master $master
     * @param SchedulerLog $logger
     */
    public function __construct(\Luminance\Core\Master $master, SchedulerLog $logger);

    /**
     * Initialize the command's dependencies.
     */
    public function initialize();

    /**
     * Command's description
     */
    public function describe();

    /**
     * Start the process
     */
    public function run();
}