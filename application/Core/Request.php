<?php
namespace Luminance\Core;

use Luminance\Errors\InternalError;

class Request {

    public $method;
    public $get;
    public $post;
    public $values;
    public $cookie;
    public $cli;
    public $ssl;
    public $port;
    public $url_parts;
    public $path;

    public $user = null;
    public $User = null;
    public $authLevel = null;
    public $session = null;

    public function __construct(Master $master) {
        $this->master = $master;
        $this->reference = bin2hex(pack('Nnn', time(), mt_rand(0, 65535), mt_rand(0, 65535)));
        $this->cli = (php_sapi_name() === "cli");
        if ($this->cli) {
            $this->method = 'CLI';
            $this->path = array_slice($master->server['argv'], 1);
        }  else {
            $this->method = $master->server['REQUEST_METHOD'];
            if (!in_array($this->method, ['GET', 'HEAD', 'POST', 'PUT'])) {
                throw new SystemException("Unknown request method: {$this->method}");
            }
            $this->port = $master->server['SERVER_PORT'];
            $this->ssl = (
                array_key_exists('HTTPS', $master->server) &&
                !empty($master->server['HTTPS']) &&
                $master->server['HTTPS'] != 'off'
            );
            $this->uri = $master->server['REQUEST_URI'];
            $this->url_parts = parse_url($master->server['REQUEST_URI']);
            $this->raw_path = ltrim($this->url_parts['path'], '/');
            $this->path = ($this->raw_path == '') ? [] : explode('/', $this->raw_path);
            $this->IP = $master->server['REMOTE_ADDR'];
        }
        $this->get = $master->superglobals['get'];
        $this->post = $master->superglobals['post'];
        $this->values = array_merge($this->get, $this->post);
        $this->cookie = $master->superglobals['cookie'];
    }

    public function getString($name, $default = '') {
        if ($this->method === 'GET') {
            return $this->getGetString($name, $default);
        } elseif ($this->method === 'POST') {
            return $this->getPostString($name, $default);
        } else {
            return $default;
        }
    }

    public function getGetString($name, $default = '') {
        if (array_key_exists($name, $this->get)) {
            return strval($this->get[$name]);
        } else {
            return $default;
        }
    }

    public function getPostString($name, $default = '') {
        if (array_key_exists($name, $this->post)) {
            return strval($this->post[$name]);
        } else {
            return $default;
        }
    }

    public function get_int($Name, $Default = 0) {
        if ($this->method === 'GET') {
            return $this->get_get_int($Name, $Default);
        } elseif ($this->method === 'POST') {
            return $this->get_post_int($Name, $Default);
        } else {
            return $Default;
        }
    }

    public function get_get_int($Name, $Default = 0) {
        if (array_key_exists($Name, $this->get)) {
            return intval($this->get[$Name]);
        } else {
            return $Default;
        }
    }

    public function get_post_int($Name, $Default = 0) {
        if (array_key_exists($Name, $this->post)) {
            return intval(intval($this->post[$Name]));
        } else {
            return $Default;
        }
    }

    public function get_bool($Name, $Default = false) {
        if ($this->method === 'GET') {
            return $this->get_get_bool($Name, $Default);
        } elseif ($this->method === 'POST') {
            return $this->get_post_bool($Name, $Default);
        } else {
            return $Default;
        }
    }

    public function get_get_bool($Name, $Default = false) {
        if (array_key_exists($Name, $this->get)) {
            return boolval(intval($this->get[$Name]));
        } else {
            return $Default;
        }
    }

    public function get_post_bool($Name, $Default = false) {
        if (array_key_exists($Name, $this->post)) {
            return boolval(intval($this->post[$Name]));
        } else {
            return $Default;
        }
    }

    public function get_cookie($name, $default = null) {
        if (array_key_exists($name, $this->cookie)) {
            return $this->cookie[$name];
        } else {
            return $default;
        }
    }

    public function set_cookie($name, $value, $expire = 0, $httponly = false) {
        $result = setcookie($name, $value, $expire, '/', '', $this->ssl, $httponly);
        # ^ last argument sets "secure" flag, meaning the cookie will only be sent back to the user over SSL
        if ($result) {
            # Ensure the cookie is available within the current request
            $this->cookie[$name] = $value;
        } else {
            # "If output exists prior to calling this function, setcookie() will fail and return FALSE" (setcookie docs)
            # This shouldn't happen, so it's an InternalError.
            throw new InternalError();
        }
    }

    public function delete_cookie($name) {
        setcookie($name, '', time() - 86400, '/', '', $this->ssl);
    }
}
