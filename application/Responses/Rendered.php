<?php

namespace Luminance\Responses;

class Rendered extends Response {

    public $template;
    public $variables;
    public $block;

    public function __construct($template, $variables = [], $status = 200, $block = null) {
        $this->template = $template;
        $this->variables = $variables;
        $this->status = $status;
        $this->block = $block;
    }
}
