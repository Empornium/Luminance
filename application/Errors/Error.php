<?php
namespace Luminance\Errors;

class Error extends \Exception {

    public $http_status = 500;
    public $public_message = "Internal Server Error";
    public $public_description = null;
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

    public function get_template_vars() {
        return [
            'http_status'=>$this->http_status,
            'message'=>$this->public_message,
            'description'=>$this->public_description
        ];
    }
}
