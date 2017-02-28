<?php
namespace Luminance\Errors;

class InputError extends UserError {

    public $http_status = 400;
    public $public_message = "Bad Request";

};
