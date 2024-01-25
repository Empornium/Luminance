<?php
namespace Luminance\Errors;

/**
 * InputError Error which is thrown when invalid form data is detected.
 */
class InputError extends UserError {

    public $httpStatus = 400;
    public $publicMessage = "Bad Request";
}
