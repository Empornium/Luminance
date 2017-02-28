<?php
namespace Luminance\Errors;

class UserError extends Error {

    public $http_status = 400;
    public $public_message = "Bad Request";

    public function __construct($message = null, $public_description = null) {
        parent::__construct($message, $public_description);
        if (!is_null($message)) {
            $this->public_message = $message; # UserErrors are only relevant for the user, so the message is meant for them
        }
    }

};
