<?php
namespace Luminance\Errors;

class NotFoundError extends UserError {

    public $http_status = 404;
    public $public_message = "Not Found";
    public $public_description = "The requested page or resource does not exist.";

};
