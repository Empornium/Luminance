<?php

namespace Luminance\Responses;

class Response {

    public $content;
    public $status;

    public function __construct($content, $status = 200) {
        $this->content = $content;
        $this->status = $status;
    }
}
