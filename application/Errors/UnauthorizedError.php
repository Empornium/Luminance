<?php
namespace Luminance\Errors;

class UnauthorizedError extends UserError {

    public $http_status = 401;
    public $public_message = "Unauthorized";
    public $public_description = "The requested page or resource required a logged-in account with sufficient privileges.";

};
