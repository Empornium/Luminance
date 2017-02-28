<?php
namespace Luminance\Errors;

class ForbiddenError extends UserError {

    public $http_status = 403;
    public $public_message = "Forbidden";
    public $public_description = "You have insufficient privileges to access the requested page or resource.";

};
