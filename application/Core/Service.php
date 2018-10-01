<?php
namespace Luminance\Core;

use Luminance\Core\Master;
use Luminance\Core\Slave;

abstract class Service extends Slave {

    public function __construct(Master $master) {
        parent::__construct($master);

        // Only happens when the Service is instanciated
        parent::registerOptions($master);
    }
}
