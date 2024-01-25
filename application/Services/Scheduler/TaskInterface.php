<?php
namespace Luminance\Services\Scheduler;

use Luminance\Core\Master;

/**
 * Scheduled Task Interface
 * Assure us all commands have what they need to run properly.
 */
interface TaskInterface {
    /**
     * A ScheduledTask constructor.
     *
     * @param Master $master
     * @param Log $logger
     */
    public function __construct(Master $master, Log $logger);

    /**
     * Initialize the command's dependencies.
     */
    public function initialize();

    /**
     * Task's description
     */
    public function describe();

    /**
     * Start the process
     */
    public function run();
}
