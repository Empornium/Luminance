<?php
namespace Luminance\Services\Scheduler;

use Luminance\Core\Master;

abstract class Task implements TaskInterface {
    protected $execLimit = 840;
    protected $master;
    protected $logger;

    public function __construct(Master $master, Log $logger) {
        $this->master = $master;
        $this->logger = $logger;
    }
}
