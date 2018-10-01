<?php
namespace Luminance\Core;

use Luminance\Core\Master;
use Luminance\Errors\InternalError;
use Luminance\Errors\NotFoundError;

use Luminance\Responses\Response;
use Luminance\Responses\Rendered;

abstract class Plugin extends Controller {

    protected static $defaultOptions = [];

    public function __construct(Master $master) {
        parent::__construct($master);
    }

    public static function register(Master $master) {
        parent::registerOptions($master);
        return null;
    }
}
