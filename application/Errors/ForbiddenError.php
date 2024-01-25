<?php
namespace Luminance\Errors;

/**
 * ForbiddenError Error which is thrown when a user attempts to perform a task they are not authorized to perform.
 */
class ForbiddenError extends UserError {

    public $httpStatus = 403;
    public $publicMessage = "Forbidden";
    public $publicDescription = "You have insufficient privileges to access the requested page or resource.";

    /**
      * __construct object
      * @param string|null $message     Message for logs.
      * @param string|null $description Message for user.
      * @param string|null $redirect    URL to redirect user to.
     */
    public function __construct($message = null, $description = null, $redirect = null) {
        parent::__construct($message, $description, $redirect);
        global $master;

        # Only send errors for logged in users
        if ($master->request->user) {
            $master->irker->forbiddenErrIrk();
        }
    }
}
