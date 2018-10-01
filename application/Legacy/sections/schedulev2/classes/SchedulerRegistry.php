<?php


/**
 * The bookkeeper of all our commands
 */
class SchedulerRegistry
{
    /**
     * List of all the commands and when they should be running
     * @return array
     */
    private function register()
    {
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

    public function get($key)
    {
        $register = $this->register();

        if (!array_key_exists($key, $register)) {
            throw new SchedulerException('Provided array key is not found in commands register.');
        }

        return $register[$key];
    }
}