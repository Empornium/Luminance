<?php
namespace Luminance\Errors;

/**
 * BBCodeError Error which is thrown when BBCode fails validation.
 */
class BBCodeError extends Error {

    public $httpStatus = 400;
    public $publicMessage = "Malformed BBCode";


    /**
      * __construct object
      * @param string|null $message     Message for error logs.
      * @param string|null $description Message for user.
      * @param string|null $redirect    URL to redirect user to.
     */
    public function __construct($message = null, $description = null, $redirect = null) {
        parent::__construct($message, $description);
        if (!is_null($message)) {
            # UserErrors are only relevant for the user, so the message is meant for them
            $this->publicMessage = $message;
        }
        if (is_string($redirect) === true) {
            $this->redirect = $redirect;
        }
    }
}
