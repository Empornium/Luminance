<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

class Flasher extends Service {

    const SEVERITY_SUCCESS  = 1;
    const SEVERITY_NOTICE   = 2;
    const SEVERITY_WARNING  = 3;
    const SEVERITY_ERROR    = 4;
    const SEVERITY_CRITICAL = 5;

    public static $decode = [
        ['flag' => self::SEVERITY_SUCCESS,  'class' => 'success' ],
        ['flag' => self::SEVERITY_NOTICE,   'class' => 'notice'  ],
        ['flag' => self::SEVERITY_WARNING,  'class' => 'warning' ],
        ['flag' => self::SEVERITY_ERROR,    'class' => 'error'   ],
        ['flag' => self::SEVERITY_CRITICAL, 'class' => 'critical'],
    ];

    private $flashes = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    public function getSeverityClass($flag) {
        $decode = array_column(self::$decode, null, 'flag');
        return $decode[$flag]['class'];
    }

    public function getFlashes() {
        $cookie = $this->request->getCookie('flashes');
        $flashes = null;
        if (!empty($encryptedCookie)) {
            if (!empty($cookie)) {
                $flashes = json_decode($cookie);
            }
            if (!is_array($flashes)) {
                $flashes = [];
                $this->request->deleteCookie('flashes');
            }
        }
        if (empty($flashes)) {
            $flashes = [];
        }
        return array_merge($this->flashes, $flashes);
    }

    public function setFlashes($flashes) {
        if (!empty($flashes)) {
            $cookie = json_encode($flashes);
            $this->flashes = array_merge($this->flashes, $flashes);
            $this->request->setCookie('flashes', $cookie);
        } else {
            $this->request->deleteCookie('flashes');
        }
    }

    public function grabFlashes() {
        # Calling this function creates an obligation to display the returned flashes to the user!
        $flashes = $this->getFlashes();
        if (!empty($flashes)) {
            $this->setFlashes([]);
        }
        return $flashes;
    }

    public function addFlash($message, $severity = 2) {
        if ($this->request->cli === true) {
            print($message.PHP_EOL);
        } else {
            $flashes = $this->getFlashes();
            $flash = new \stdClass();
            $flash->message  = $message;
            $flash->severity = $this->getSeverityClass($severity);
            $flashes[]       = $flash;
            $this->setFlashes($flashes);
        }
    }

    public function success($message) {
        $this->addFlash($message, self::SEVERITY_SUCCESS);
    }

    public function notice($message) {
        $this->addFlash($message, self::SEVERITY_NOTICE);
    }

    public function warning($message) {
        $this->addFlash($message, self::SEVERITY_WARNING);
    }

    public function error($message) {
        $this->addFlash($message, self::SEVERITY_ERROR);
    }

    public function critical($message) {
        $this->addFlash($message, self::SEVERITY_CRITICAL);
    }
}
