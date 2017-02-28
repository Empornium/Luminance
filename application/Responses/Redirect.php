<?php

namespace Luminance\Responses;

class Redirect extends Response {

    public $target;
    public $parameters;

    # 302 to avoid browser caching
    public function __construct($target, $parameters = null, $status = 302) {
        $this->target = $target;
        $this->parameters = $parameters;
        $this->status = $status;
    }
}
