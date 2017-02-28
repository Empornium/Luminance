<?php
namespace Luminance\Services;

use Luminance\Errors\InternalError;

class Profiler {

    public $start_time = null;
    public $profile_log = [];
    public $states = [];
    protected $disabled = false;

    public function __construct($master) {
        $this->master = $master;
        $real_start_time = $master->start_time;
        if (is_null($real_start_time)) {
            $this->start_time = microtime(true);
        } else {
            $this->start_time = $real_start_time;
            $this->add_log(0.0, 'application started');
        }
        $this->enter_state('profiler_running');
    }

    public function get_timestamp() {
        $timestamp = microtime(true) - $this->start_time;
        return $timestamp;
    }

    public function disable() {
        # Once disabled, we can't enable again, as that could lead to an inconsistent set of states
        $this->disabled = true;
    }

    protected function add_log($timestamp, $state_text) {
        $this->profile_log[] = [$timestamp, $state_text];
    }
    
    public function enter_state($state_name) {
        if ($this->disabled) {
            return;
        }
        if (array_key_exists($state_name, $this->states)) {
            $state = $this->states[$state_name];
        } else {
            $state = new \stdClass();
            $state->active = false;
            $state->count = 0;
            $state->total_time = 0.0;
            $state->start_time = null;
            $this->states[$state_name] = $state;
        }
        if ($state->active) {
            throw new InternalError("Profiler state {$state_name} already active!");
        }
        $state->active = true;
        $state->start_time = $this->get_timestamp();
        $this->info("+{$state_name}");
    }

    public function leave_state($state_name) {
        if ($this->disabled) {
            return;
        }
        if (!array_key_exists($state_name, $this->states) || !$this->states[$state_name]->active) {
            throw new InternalError("Profiler state {$state_name} not active!");
        }
        $timestamp = $this->get_timestamp();
        $state = $this->states[$state_name];
        $state->active = false;
        $state->count++;
        $state->total_time += $timestamp - $state->start_time;
        $state->start_time = null;
        $this->info("-{$state}");
    }

    public function info($message) {
        if ($this->disabled) {
            return;
        }
        $timestamp = $this->get_timestamp();
        $this->add_log($timestamp, $message);
    }

    public function finish_and_log() {
        if ($this->disabled) {
            return;
        }
        $this->leave_state('profiler_running');
        error_log(print_r($this->profile_log, true));
    }

}
