<?php
namespace Luminance\Core;

use Luminance\Core\Master;
use Luminance\Services\Options;

abstract class Slave {

    protected static $useRepositories = [];
    protected static $useServices = [];

    public function __construct(Master $master) {
        $this->master = $master;
        $this->request = $this->master->request;
    }

    public static function registerOptions(Master $master) {
        if (isset(static::$defaultOptions)) {
            Options::register(static::$defaultOptions);
        }
    }

    public function link() {
        $this->prepareRepositories();
        $this->prepareServices();
    }

    protected function prepareRepositories() {
        foreach (static::$useRepositories as $localName => $repositoryName) {
            $this->{$localName} = $this->master->getRepository($repositoryName);
        }
    }

    protected function prepareServices() {
        foreach (static::$useServices as $localName => $serviceName) {
            $this->{$localName} = $this->master->getService($serviceName);
        }
    }
}
