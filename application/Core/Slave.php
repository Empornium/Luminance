<?php
namespace Luminance\Core;

use Luminance\Services\Options;
use Luminance\Services\Render;

abstract class Slave {

    protected static $useServices = [];
    protected static $defaultOptions = [];
    protected static $userinfoTools = [];
    protected $master;
    protected $request;
    protected $services = [];

    public function __construct(Master $master) {
        $this->master  = &$master;
        $this->request = &$this->master->request;
    }

    public static function registerOptions(Master $master) {
        if (isset(static::$defaultOptions)) {
            Options::register(static::$defaultOptions);
        }
    }

    public static function registerTools(Master $master) {
        if (isset(static::$userinfoTools)) {
            Render::registerTool(static::$userinfoTools);
        }
    }

    public function link() {
        $this->prepareServices();
    }

    protected function prepareServices() {
        foreach (static::$useServices as $localName => $serviceName) {
            $this->{$localName} = $this->master->getService($serviceName);
        }
    }

    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            if (array_key_exists($name, $this->services)) {
                return $this->services[$name];
            } else {
                return null;
            }
        }
    }

    public function __isset($name) {
        if (property_exists($this, $name)) {
            return true;
        }
        if (array_key_exists($name, $this->services)) {
            return true;
        }
        return false;
    }

    public function __set($name, $value) {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } else {
            $this->services[$name] = $value;
        }
    }
}
