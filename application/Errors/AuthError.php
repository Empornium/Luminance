<?php
namespace Luminance\Errors;

class AuthError extends UnauthorizedError {

    public $http_status = 401;
    public $public_message = "Unauthorized";
    public $public_description = "Authorization error.";
    public $redirect = null;

    public function __construct($message = null, $public_description = null, $redirect = null) {
        parent::__construct($message);
        if ($public_description) {
            $this->public_description = $public_description;
        }
        if ($redirect) {
            $this->redirect = $redirect;
        }
    }

};
