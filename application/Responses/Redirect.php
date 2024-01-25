<?php

namespace Luminance\Responses;

/**
 * Redirect Represents a 301 or 302 browser redirect
 */
class Redirect extends Response {

    public $target;
    public $parameters;

    /**
     * __construct object
     * @param string      $target     Target URL for redirect.
     * @param array|null  $parameters URL query string parameters.
     * @param integer     $status     HTTP response code, should be 301 or 302.
     */
    public function __construct(string $target, $parameters = null, $status = 302) {
        $this->target = $target;
        $this->parameters = $parameters;
        $this->status = $status;
    }
}
