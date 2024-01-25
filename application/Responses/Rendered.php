<?php

namespace Luminance\Responses;

/**
 * Rendered Represents a page to be rendered using a TWIG template.
 */
class Rendered extends Response {

    public $template;
    public $variables;
    public $block;
    public $callback;
    public $callbackParams;

    /**
     * __construct object
     * @param string        $template       TWIG template identifier.
     * @param array         $variables      Array of variables for the template.
     * @param int           $status         HTTP status code.
     * @param string|null   $block          TWIG block identifier or null for full template.
     * @param callable|null $callback       Callback function to execute after rendering is complete.
     * @param array|null    $callbackParams Parameters to pass to the callback function.
     */
    public function __construct(
        string   $template,
        array    $variables = [],
        int      $status = 200,
        string   $block = null,
        callable $callback = null,
        array    $callbackParams = null
    ) {
        $this->template = $template;
        $this->variables = $variables;
        $this->status = $status;
        $this->block = $block;
        $this->callback = $callback;
        $this->callbackParams = $callbackParams;
    }
}
