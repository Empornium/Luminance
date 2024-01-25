<?php
namespace Luminance\Services\Scheduler;

use Luminance\Errors\SchedulerError;

use Luminance\Plugins\Scheduler\Tasks\PurgeRequests;

/**
 * The bookkeeper of all our tasks
 */
class Registry {
    /**
     * List of all the tasks and when they should be running
     * @return array
     */
    private function register() {
        return [
            'always' => [
                PurgeRequests::class
            ],
            'every-day' => [],
            'every-hour' => [],
            'every-45-minutes' => [],
            'every-30-minutes' => [],
            'every-15-minutes' => [],
        ];
    }

    public function get($key) {
        $register = $this->register();

        if (!array_key_exists($key, $register)) {
            throw new SchedulerError('Provided array key is not found in tasks register.');
        }

        return $register[$key];
    }
}
