<?php
namespace Luminance\Core;

abstract class Service extends Slave {

    public function __construct(Master $master) {
        parent::__construct($master);

        # Only happens when the Service is instanciated
        parent::registerOptions($master);
    }
}
