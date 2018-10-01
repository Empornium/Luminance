<?php
namespace Luminance\Core;

use Luminance\Errors\InternalError;

use Delight\Cookie\Cookie;
use Luminance\Errors\SystemError;
use Luminance\Responses\Redirect;

class Request {

    public $method;
    public $get;
    public $host;
    public $post;
    public $values;
    public $cookie;
    public $cli;
    public $ssl;
    public $port;
    public $url_parts;
    public $path;
    public $referer;

    public $user = null;
    public $authLevel = null;
    public $session = null;

    public function __construct(Master $master) {
        $this->master = $master;
        $this->reference = bin2hex(pack('Nnn', time(), mt_rand(0, 65535), mt_rand(0, 65535)));
        $this->cli = (php_sapi_name() === "cli");
        if ($this->cli) {
            $this->method = 'CLI';
            $this->path = array_slice($master->server['argv'], 1);
        } else {
            $this->method = $master->server['REQUEST_METHOD'];
            if (!in_array($this->method, ['GET', 'HEAD', 'POST', 'PUT'])) {
                throw new SystemError("Unknown request method: {$this->method}");
            }
            $this->port = $master->server['SERVER_PORT'];
            $this->host = $master->server['HTTP_HOST'] ?? null;
            $this->ssl = (
                array_key_exists('HTTPS', $master->server) &&
                !empty($master->server['HTTPS']) &&
                $master->server['HTTPS'] != 'off'
            );

            $this->uri = $master->server['REQUEST_URI'];

            // Parse URI parts
            if (!$this->url_parts = parse_url($master->server['REQUEST_URI'])) {
                $this->url_parts = [];
            }

            if (!array_key_exists('path', $this->url_parts)) $this->url_parts['path'] = '/';
            $this->raw_path = ltrim($this->url_parts['path'], '/');
            $this->path = ($this->raw_path == '') ? [] : explode('/', $this->raw_path);
            $this->ip = $master->repos->ips->get_or_new($master->server['REMOTE_ADDR']);
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
        //$result = setcookie($name, $value, $expire, '/', '', $this->ssl, $httponly);
        $result = Cookie::setcookie($name, $value, $expire, '/', '', $this->ssl, $httponly, 'Lax');

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

    /**
     * Save the intended route of a request in a cookie
     *
     * @return void
     */
    public function saveIntendedRoute() {
        $encrypted = $this->master->crypto->encrypt($this->uri, 'intendedRoute');
        $this->set_cookie('intendedRoute', $encrypted);
    }

    /**
     * Retrieve the previous intended route from cookies
     *
     * @return string|null
     */
    public function getIntendedRoute() {
        $encrypted = $this->get_cookie('intendedRoute');

        if ($encrypted === null) {
            return null;
        }

        $intendedRoute = $this->master->crypto->decrypt($encrypted, 'intendedRoute');
        $this->delete_cookie('intendedRoute');

        return $intendedRoute;
    }

    /**
     * Redirect to a saved route (Cookie), the HTTP Referer (if allowed) or the fallback URL
     *
     * @return Redirect
     */
    public function back($fallback = '/', $referer = true) {
        // Try to get a saved route first
        if ($sessionRoute = $this->getIntendedRoute()) {
            return new Redirect($sessionRoute);
        }

        // If allowed, we redirect to the HTTP Referer
        // Note: this will fail the purpose on multiple redirect hops (e.g. 2FA-step)
        if ($referer && $this->checkReferer()) {
            return new Redirect($this->referer);
        }

        return new Redirect($fallback);
    }

    /**
     * Make sure the referer points to our site, avoiding open external redirects
     *
     * @return bool
     */
    public function checkReferer() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];

            // TODO: wrap this in a global function
            if (!preg_match(';^https?://'.$this->master->settings->main->site_url.'/;i', $referer)) {
                return false;
            }

            $this->referer = $referer;
            return true;
        }
        return false;
    }


    /**
     * Set the HTTP headers for this request's reponse
     */
    public function setHttpHeaders() {
        // For now, we only have CSP
        $this->setContentSecurityPolicy();
    }

    /**
     * Set the Content Security Policy for this request's reponse
     * Note: Google is required for the charts api, we need to kill that off soon
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy
     */
    public function setContentSecurityPolicy() {
        $imagehosts = $this->master->db->raw_query("SELECT Imagehost FROM imagehost_whitelist")->fetchAll();
        $imagehosts = implode(' ', array_column($imagehosts, 'Imagehost'));

        $default_src = ["'self'"];

        $img_src = [
            "'self'",
            "data: $imagehosts",
            "http://chart.apis.google.com"
        ];

        $child_src = [
            "'self'",
            $imagehosts
        ];

        $script_src = [
            "'self'",
            "'unsafe-inline'",
            "'unsafe-eval'",
            "https://www.google.com"
        ];

        $connect_src = ["'self'"];

        if ($this->master->options->HaveIBeenPwned) {
            $connect_src[] = "https://api.pwnedpasswords.com";
        }

        $style_src = [
            "'self'",
            "'unsafe-inline'",
            "https://www.google.com",
            "https://ajax.googleapis.com"
        ];

        // Gather all directives
        $csp = [
            "default-src" => $default_src,
            "img-src"     => $img_src,
            "child-src"   => $child_src,
            "script-src"  => $script_src,
            "connect-src" => $connect_src,
            "style-src"   => $style_src,
        ];

        // Format the CSP into a string
        $header = 'Content-Security-Policy: ';
        foreach ($csp as $directive => $rules) {
            $header .= $directive.'  '.implode(' ', $rules).'; ';
        };

        // Send the header
        header(trim($header));
    }
}
