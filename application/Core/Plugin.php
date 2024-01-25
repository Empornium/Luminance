<?php
namespace Luminance\Core;

abstract class Plugin extends Controller {

    public function __construct(Master $master) {
        parent::__construct($master);
    }

    public static function register(Master $master) {
        parent::registerOptions($master);
        parent::registerTools($master);
        return null;
    }
}
