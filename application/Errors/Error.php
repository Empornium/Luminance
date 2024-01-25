<?php
namespace Luminance\Errors;

/**
 * Error Base Luminance error class, should not be thrown directly.
 */
class Error extends \Exception {

    public $httpStatus = 500;
    public $publicMessage = "Internal Server Error";
    public $publicDescription = null;
    public $redirect = null;
    protected $returnJSON = false;

    /**
      * __construct object
      * @param string|null $message     Message for error logs.
      * @param string|null $description Message for user.
      * @param string|null $redirect    URL to redirect user to.
     */
    public function __construct($message = null, $description = null, $redirect = null) {
        parent::__construct($message);

        global $master;

        if (is_string($description) === true) {
            $this->publicDescription = $description;
        }
        if (is_string($redirect) === true) {
            $this->redirect = $redirect;
        }
    }

    /**
     * get_template_vars packages the object's data into an array for TWIG templating.
     * @return array Variables used to populate the error template.
     */
    public function getTemplateVars() {
        return [
            'http_status'  => $this->httpStatus,
            'message'      => $this->publicMessage,
            'description'  => $this->publicDescription,
            'bscripts'     => []
        ];
    }

    /**
      * returnJSON sets or gets whether the error should be presented in JSON format
      * @param boolean|null $set Whether and how to set the JSON return flag.
      * @return boolean Whether to return error in JSON format.
     */
    public function returnJSON($set = null) {
        if (!is_null($set)) {
            $this->returnJSON = $set;
        }

        return $this->returnJSON;
    }
}
