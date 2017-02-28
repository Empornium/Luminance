<?php
namespace Luminance\Core;

use Luminance\Core\Master;
use Luminance\Errors\InternalError;
use Luminance\Errors\NotFoundError;

use Luminance\Responses\Response;
use Luminance\Responses\Rendered;

abstract class Plugin extends Controller {

    public static function register(Master $master) {
        return null;
    }

}
