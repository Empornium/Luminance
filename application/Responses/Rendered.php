<?php

namespace Luminance\Responses;

class Rendered extends Response {

    public $template;
    public $variables;

    public function __construct($template, $variables = [], $status = 200) {
        $this->template = $template;
        $this->variables = $variables;
        $this->status = $status;
    }
}
