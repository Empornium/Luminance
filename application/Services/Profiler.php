<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Errors\InternalError;

class Profiler extends Service {

    public $startTime = null;
    public $profileLog = [];
    public $states = [];
    protected $disabled = false;


    public function __construct(Master $master) {
        parent::__construct($master);
        $this->master = $master;
        $realStartTime = $master->startTime;
        if (is_null($realStartTime)) {
            $this->startTime = microtime(true);
        } else {
            $this->startTime = $realStartTime;
            $this->addLog(0.0, 'application started');
        }
        $this->enterState('profiler_running');
    }

    public function getTimestamp() {
        $timestamp = microtime(true) - $this->startTime;
        return $timestamp;
    }

    public function disable() {
        # Once disabled, we can't enable again, as that could lead to an inconsistent set of states
        $this->disabled = true;
    }

    protected function addLog($timestamp, $stateText) {
        $this->profileLog[] = [$timestamp, $stateText];
    }

    public function enterState($stateName) {
        if ($this->disabled) {
            return;
        }
        if (array_key_exists($stateName, $this->states)) {
            $state = $this->states[$stateName];
        } else {
            $state = new \stdClass();
            $state->active = false;
            $state->count = 0;
            $state->totalTime = 0.0;
            $state->startTime = null;
            $this->states[$stateName] = $state;
        }
        if ($state->active) {
            throw new InternalError("Profiler state {$stateName} already active!");
        }
        $state->active = true;
        $state->startTime = $this->getTimestamp();
        $this->info("+{$stateName}");
    }

    public function leaveState($stateName) {
        if ($this->disabled) {
            return;
        }
        if (!array_key_exists($stateName, $this->states) || !$this->states[$stateName]->active) {
            throw new InternalError("Profiler state {$stateName} not active!");
        }
        $timestamp = $this->getTimestamp();
        $state = $this->states[$stateName];
        $state->active = false;
        $state->count++;
        $state->totalTime += $timestamp - $state->startTime;
        $state->startTime = null;
        $this->info("-{$state}");
    }

    public function info($message) {
        if ($this->disabled) {
            return;
        }
        $timestamp = $this->getTimestamp();
        $this->addLog($timestamp, $message);
    }

    public function finishAndLog() {
        if ($this->disabled) {
            return;
        }
        $this->leaveState('profiler_running');
        error_log(print_r($this->profileLog, true));
    }
}
