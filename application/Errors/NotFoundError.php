<?php
namespace Luminance\Errors;

/**
 * NotFoundError Error ehcih is thrown when a URL could not be resolved.
 */
class NotFoundError extends UserError {

    public $httpStatus = 404;
    public $publicMessage = "Not Found";
    public $publicDescription = "The requested page or resource does not exist.";
}
