<?php

namespace Luminance\Responses;

/**
 * Rendered Represents a page to be rendered using a TWIG template.
 */
class XML extends Response {

    public $content;
    public $status;
    public static $contentType = 'application/xml; charset=UTF-8';

    /**
     * __construct object
     * @param string|null  $content Raw content to be presented to user or null for none.
     * @param integer      $status  HTTP status code.
     */
    public function __construct($content, $status = 200) {
        $this->status = $status;
    }
}
