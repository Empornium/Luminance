<?php
namespace Luminance\Errors;

/**
 * AuthError Error which is thrown during unsucessful authentication events.
 */
class AuthError extends UnauthorizedError {

    public $httpStatus = 401;
    public $publicMessage = "Unauthorized";
    public $publicDescription = "Authorization error.";
    public $redirect = null;

    /**
     * __construct object
     * @param string|null $message     Message for error logs.
     * @param string|null $description Message for user.
     * @param string|null $redirect    URL to redirect user to.
     */
    public function __construct($message = null, $description = null, $redirect = null) {
        parent::__construct($message);
        if (is_string($description) === true) {
            $this->publicDescription = $description;
        }
        if (is_string($redirect) === true) {
            $this->redirect = $redirect;
        }
    }
}
