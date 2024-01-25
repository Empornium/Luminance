<?php
namespace Luminance\Errors;

/**
 * UnauthorizedError Error whcih is thrown when a user attempts to access the site without being authenticated.
 */
class UnauthorizedError extends UserError {

    public $httpStatus = 401;
    public $publicMessage = "Unauthorized";
    public $publicDescription = "The requested page or resource required a logged-in account with sufficient privileges.";
}
