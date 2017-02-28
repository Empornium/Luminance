<?php
namespace Luminance\Services;

use Luminance\Core\Master;

# The Peon class is a useful little minion that helps tie interface/template logic together
class Flasher extends Service {

    const SEVERITY_SUCCESS  = 1;
    const SEVERITY_NOTICE   = 2;
    const SEVERITY_WARNING  = 3;
    const SEVERITY_ERROR    = 4;
    const SEVERITY_CRITICAL = 5;

    protected static $useServices = [
        'crypto' => 'Crypto',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    public function getFlashes() {
        $encryptedCookie = $this->request->get_cookie('flashes');
        $flashes = null;
        if ($encryptedCookie) {
            $cookie = $this->crypto->decrypt($encryptedCookie, 'flashes');
            if ($cookie) {
                $flashes = json_decode($cookie);
            }
            if (!is_array($flashes)) {
                $flashes = [];
                $this->request->delete_cookie('flashes');
            }
        }
        if (!$flashes) {
            $flashes = [];
        }
        return $flashes;
    }

    public function setFlashes($flashes) {
        if ($flashes) {
            $cookie = json_encode($flashes);
            $encryptedCookie = $this->crypto->encrypt($cookie, 'flashes');
            $this->request->set_cookie('flashes', $encryptedCookie);
        } else {
            $this->request->delete_cookie('flashes');
        }
    }

    public function grabFlashes() {
        # Calling this function creates an obligation to display the returned flashes to the user!
        $flashes = $this->getFlashes();
        if ($flashes) {
            $this->setFlashes([]);
        }
        return $flashes;
    }

    public function addFlash($message, $data, $severity = 2) {
        $flashes = $this->getFlashes();
        $flash = new \stdClass();
        $flash->message  = $message;
        $flash->data     = $data;
        $flash->severity = $severity;
        $flashes[]       = $flash;
        $this->setFlashes($flashes);
    }

    public function success($message, $data = []) {
        $this->addFlash($message, $data, self::SEVERITY_SUCCESS);
    }

    public function notice($message, $data = []) {
        $this->addFlash($message, $data, self::SEVERITY_NOTICE);
    }

    public function warning($message, $data = []) {
        $this->addFlash($message, $data, self::SEVERITY_WARNING);
    }

    public function error($message, $data = []) {
        $this->addFlash($message, $data, self::SEVERITY_ERROR);
    }

    public function critical($message, $data = []) {
        $this->addFlash($message, $data, self::SEVERITY_CRITICAL);
    }

}
