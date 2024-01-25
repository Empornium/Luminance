<?php

namespace Luminance\Responses;

/**
 * Response Represents a generic response with or without pre-rendered content.
 */
class Response {

    public $content;
    public $status;

    /**
     * __construct object
     * @param string|null  $content Raw content to be presented to user or null for none.
     * @param integer      $status  HTTP status code.
     */
    public function __construct($content, $status = 200) {
        $this->content = $content;
        $this->status = $status;
    }
}
