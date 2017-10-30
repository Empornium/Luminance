<?php
namespace Luminance\Core;

use Luminance\Core\Master;

abstract class Slave {

    protected static $useRepositories = [];
    protected static $useServices = [];

    public function __construct(Master $master) {
        $this->master = $master;
        $this->prepareRepositories();
        $this->prepareServices();
        $this->request = $this->master->request;
    }

    public static function registerOptions(Master $master) {
        if (isset(static::$defaultOptions)) {
            $master->options->register(static::$defaultOptions);
        }
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
