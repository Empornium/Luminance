<?php


class Scheduler
{
    private $master;
    private $db;
    private $logger;

    private $commands = [];
    private $quarter;

    /**
     * Scheduler constructor.
     * @param \Luminance\Core\Master $master
     * @param SchedulerLog $logger
     */
    public function __construct(\Luminance\Core\Master $master, SchedulerLog $logger)
    {
        $this->master = $master;
        $this->db = $this->master->db;
        $this->logger = $logger;
    }

    public function start()
    {
        $this->initialize();
        $this->check_permission();
        $this->lock_process();
        $this->resolve();
        $this->run();
    }

    private function initialize()
    {
        set_time_limit(840);
        ob_end_flush();
        gc_enable();
    }

    private function check_permission()
    {
        // TODO: used in HTTP only?
    }

    /**
     * Get lock process, abort if found
     * @throws SchedulerException
     */
    private function lock_process()
    {
        $siteName   = $this->master->settings->main->site_name;
        $lockStatus = (int) $this->db->raw_query("SELECT GET_LOCK('{$siteName}:scheduler', 3)")->fetchColumn();

        if ($lockStatus !== 1) {
            throw new SchedulerException('Scheduler failed to aquire lock (another scheduler process is running!)');
        }
    }

    /**
     * Resolve which commands should be running at that time
     */
    private function resolve()
    {
        $watch    = new SchedulerWatch($this->db);
        $registry = new SchedulerRegistry();

        // Get commands that are always running
        $this->add_commands($registry->get('always'));

        // Get commands for this specific quarter
        $this->logger->log("Schedule quarter: {$watch->quarter}");
        $this->add_commands($registry->get("every-{$watch->quarter}-minutes"));

        // TODO: Get every hour commands, etc...
    }

    private function run()
    {
        foreach ($this->commands as $schedulerCommand) {
            // Create the command instance
            $command = new $schedulerCommand($this->master, $this->logger);

            if (!$command instanceof ScheduledCommand) {
                throw new SchedulerException('Commands must always extend the ScheduledCommand class');
            }

            // Describe what we're starting
            $this->logger->log($this->describe_command($command));

            try {
                // Start the command
                $command->initialize();
                $command->run();

                // Describe what we've finished
                $this->logger->log($this->end_command($command));
            } catch (\Exception $e) {
                $this->logger->log('ERROR: '.$e->getMessage());
            }
        }
    }

    private function describe_command($command)
    {
        // TODO: remove namespace from get_class
        return "----------\nCommand: ".get_class($command).' - '.$command->describe();
    }

    private function end_command($command)
    {
        // TODO: remove namespace from get_class
        return 'End of command: '.get_class($command)."\n----------";
    }

    private function add_commands($commands)
    {
        $this->commands = array_merge($this->commands, $commands);
    }
}