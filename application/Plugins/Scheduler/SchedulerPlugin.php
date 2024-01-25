<?php
namespace Luminance\Plugins\Scheduler;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

class SchedulerPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'CLI', '*', Auth::AUTH_NONE, 'autoRun' ],
    ];

    protected static $useServices = [
        'scheduler' => 'Scheduler',
    ];

    public static function register(Master $master) {
        parent::register($master);
        # This registers the plugin and has nothing to do with account creation!
        $master->prependRoute([ 'CLI', 'schedulerv2', Auth::AUTH_NONE, 'plugin', 'Scheduler' ]);
    }

    public function autoRun() {
        $this->scheduler->start();
    }
}
