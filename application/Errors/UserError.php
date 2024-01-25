<?php
namespace Luminance\Errors;

/**
 * UserError Error which is thrown when a user does something stupid.
 */
class UserError extends Error {

    public $httpStatus = 400;
    public $publicMessage = "Bad Request";


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
        global $master;

        if ($master->request->user) {
            $master->irker->userErrorIrker();
        }
    }
}
