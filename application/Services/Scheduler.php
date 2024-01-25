<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\SchedulerError;

use Luminance\Services\Scheduler\Log;
use Luminance\Services\Scheduler\Watch;
use Luminance\Services\Scheduler\Task;
use Luminance\Services\Scheduler\Registry;

class Scheduler extends Service {
    private $db;
    private $logger;

    private $tasks = [];
    private $quarter;

    /**
     * Scheduler constructor.
     * @param \Luminance\Core\Master $master
     * @param Log $logger
     */
    public function __construct(Master $master) {
        parent::__construct($master);
        $this->master = $master;
        $this->db = $this->master->db;
        $this->log = new Log();
    }

    public function start() {
        $this->initialize();
        $this->lockProcess();
        $this->resolve();
        $this->run();
    }

    private function initialize() {
        set_time_limit(840);
        ob_end_flush();
        gc_enable();
    }

    /**
     * Get lock process, abort if found
     * @throws SchedulerError
     */
    private function lockProcess() {
        $siteName   = $this->master->settings->main->site_name;
        $lockStatus = (int) $this->db->rawQuery("SELECT GET_LOCK('{$siteName}:scheduler', 3)")->fetchColumn();

        if (!($lockStatus === 1)) {
            throw new SchedulerError('Scheduler failed to aquire lock (another scheduler process is running!)');
        }
    }

    /**
     * Resolve which tasks should be running at that time
     */
    private function resolve() {
        $watch    = new Watch($this->db);
        $registry = new Registry();

        // Get tasks that are always running
        $this->addTaks($registry->get('always'));

        // Get tasks for this specific quarter
        $this->logger->log("Schedule quarter: {$watch->quarter}");
        $this->addTaks($registry->get("every-{$watch->quarter}-minutes"));

        // TODO: Get every hour tasks, etc...
    }

    private function run() {
        foreach ($this->tasks as $schedulerTask) {
            // Create the command instance
            $task = new $schedulerTask($this->master, $this->logger);

            if (!$task instanceof Task) {
                throw new SchedulerError('Tasks must always extend the ScheduledTask class');
            }

            // Describe what we're starting
            $this->logger->log($this->describeTask($task));

            try {
                // Start the command
                $task->initialize();
                $task->run();

                // Describe what we've finished
                $this->logger->log($this->endTask($task));
            } catch (\Exception $e) {
                $this->logger->log('ERROR: '.$e->getMessage());
            }
        }
    }

    private function describeTask($task) {
        // TODO: remove namespace from get_class
        return "----------\Task: ".get_class($task).' - '.$task->describe();
    }

    private function endTask($task) {
        // TODO: remove namespace from get_class
        return 'End of task: '.get_class($task)."\n----------";
    }

    private function addTaks($tasks) {
        $this->tasks = array_merge($this->tasks, $tasks);
    }
}
