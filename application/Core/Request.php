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
    public $uri;
    public $url;
    public $query;
    public $agent;
    public $cli;
    public $ssl;
    public $port;
    public $urlParts;
    public $path;
    public $rawPath;
    public $referer;

    public $reference;
    public $user = null;
    public $authLevel = null;
    public $session = null;
    public $client = null;

    public $TLSVersion = null;
    public $HTTPVersion = null;

    private $master;

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
                !($master->server['HTTPS'] === 'off')
            );

            $this->uri = $master->server['REQUEST_URI'] ?? '/';
            $this->query = $master->server['QUERY_STRING'] ?? '';
            $this->agent = $master->server['HTTP_USER_AGENT'] ?? 'Unknown/masked';

            $this->url = $this->host . $this->uri;

            # Parse URI parts
            if (!$this->urlParts = parse_url($master->server['REQUEST_URI'])) {
                $this->urlParts = [];
            }

            if (!array_key_exists('path', $this->urlParts)) $this->urlParts['path'] = '/';
            $this->rawPath = ltrim($this->urlParts['path'], '/');
            $this->path = ($this->rawPath === '') ? [] : explode('/', $this->rawPath);

            if (array_key_exists('SERVER_PROTOCOL', $_SERVER)) {
                $this->HTTPVersion = $_SERVER['SERVER_PROTOCOL'];
            }

            if (array_key_exists('SSL_PROTOCOL', $_SERVER)) {
                $this->TLSVersion = $_SERVER['SSL_PROTOCOL'];
            }
        }
        $this->cookie = $master->superglobals['cookie'];
        $error = $this->getErrorCookie();
        $this->get = array_merge($master->superglobals['get'], $error['get']);
        $this->post = array_merge($master->superglobals['post'], $error['post']);
        $this->values = array_merge($this->get, $this->post);
    }

    private function handleGetString($collection, $name, $default, $valid) {
        if (array_key_exists($name, $collection)) {
            $value = strval($collection[$name]);
            if (is_array($valid)) {
                if (in_array($value, $valid)) {
                    return $value;
                } else {
                    return $default;
                }
            } else {
                return $value;
            }
        } else {
            return $default;
        }
    }

    public function getString($name, $default = '', $valid = null) {
        if ($this->method === 'GET') {
            return $this->getGetString($name, $default);
        } elseif ($this->method === 'POST') {
            return $this->getPostString($name, $default);
        } else {
            return $default;
        }
    }

    public function getGetString($name, $default = '', $valid = null) {
        return $this->handleGetString($this->get, $name, $default, $valid);
    }

    public function getPostString($name, $default = '', $valid = null) {
        return $this->handleGetString($this->post, $name, $default, $valid);
    }

    public function getRequestString($name, $default = '', $valid = null) {
        return $this->handleGetString($this->values, $name, $default, $valid);
    }

    private function handleGetInt($collection, $name, $default) {
        if (array_key_exists($name, $collection)) {
            return intval($collection[$name]);
        } else {
            return $default;
        }
    }

    public function getInt($name, $default = 0) {
        if ($this->method === 'GET') {
            return $this->getGetInt($name, $default);
        } elseif ($this->method === 'POST') {
            return $this->getPostInt($name, $default);
        } else {
            return $default;
        }
    }

    public function getGetInt($name, $default = 0) {
        return $this->handleGetInt($this->get, $name, $default);
    }

    public function getPostInt($name, $default = 0) {
        return $this->handleGetInt($this->post, $name, $default);
    }

    public function getRequestInt($name, $default = 0) {
        return $this->handleGetInt($this->values, $name, $default);
    }

    private function handleGetBool($collection, $name, $default) {
        if (array_key_exists($name, $collection)) {
            return boolval(intval($collection[$name]));
        } else {
            return $default;
        }
    }

    public function getBool($name, $default = false) {
        if ($this->method === 'GET') {
            return $this->getGetBool($name, $default);
        } elseif ($this->method === 'POST') {
            return $this->getPostBool($name, $default);
        } else {
            return $default;
        }
    }

    public function getGetBool($name, $default = false) {
        return $this->handleGetBool($this->get, $name, $default);
    }

    public function getPostBool($name, $default = false) {
        return $this->handleGetBool($this->post, $name, $default);
    }

    public function getRequestBool($name, $default = false) {
        return $this->handleGetBool($this->values, $name, $default);
    }

    private function handleGetArray($collection, $name, $default) {
        if (array_key_exists($name, $collection)) {
            return (array) $collection[$name];
        } else {
            return $default;
        }
    }

    public function getArray($name, $default = []) {
        if ($this->method === 'GET') {
            return $this->getGetArray($name, $default);
        } elseif ($this->method === 'POST') {
            return $this->getPostArray($name, $default);
        } else {
            return $default;
        }
    }

    public function getGetArray($name, $default = []) {
        return $this->handleGetArray($this->get, $name, $default);
    }

    public function getPostArray($name, $default = []) {
        return $this->handleGetArray($this->post, $name, $default);
    }

    public function getRequestArray($name, $default = []) {
        return $this->handleGetArray($this->values, $name, $default);
    }

    public function getCookie($name, $default = null) {
        if (array_key_exists($name, $this->cookie)) {
            return $this->cookie[$name];
        } else {
            return $default;
        }
    }

    public function setCookie($name, $value, $expire = 0, $httponly = false) {
        #$result = setcookie($name, $value, $expire, '/', '', $this->ssl, $httponly);
        $result = Cookie::setcookie($name, $value, $expire, '/', '', $this->ssl, $httponly, 'Lax');
        if ($result === true) {
            # Ensure the cookie is available within the current request
            $this->cookie[$name] = $value;
        } else {
            # "If output exists prior to calling this function, setcookie() will fail and return FALSE" (setcookie docs)
            # This shouldn't happen, so it's an InternalError.
            throw new InternalError();
        }
    }

    public function deleteCookie($name) {
        $this->setCookie($name, '', time() - 86400, $this->ssl);
    }

    /**
     * Save the intended route of a request in a cookie
     *
     * @return void
     */
    public function saveIntendedRoute() {
        # Do not save anything else than GETs,
        # as redirects only use the GET method
        if ($this->method === 'GET') {
            $encrypted = $this->master->crypto->encrypt($this->uri, 'intendedRoute');
            $this->setCookie('intendedRoute', $encrypted);
        }
    }

    /**
     * Retrieve the previous intended route from cookies
     *
     * @return string|null
     */
    public function getIntendedRoute() {
        $encrypted = $this->getCookie('intendedRoute');

        if ($encrypted === null) {
            return null;
        }

        $intendedRoute = $this->master->crypto->decrypt($encrypted, 'intendedRoute');
        $this->deleteCookie('intendedRoute');

        return $intendedRoute;
    }

    /**
     * Redirect to a saved route (Cookie), the HTTP Referer (if allowed) or the fallback URL
     *
     * @return Redirect
     */
    public function back($fallback = '/', $referer = true, $status = 302) {
        # Try to get a saved route first
        if ($sessionRoute = $this->getIntendedRoute()) {
            # No circular redirects please
            if (!($sessionRoute === $this->uri)) {
                return new Redirect($sessionRoute, null, $status);
            }
        }

        # If allowed, we redirect to the HTTP Referer
        # Note: this will fail the purpose on multiple redirect hops (e.g. 2FA-step)
        if ($referer && $this->checkReferer()) {
            return new Redirect($this->referer, null, $status);
        }

        return new Redirect($fallback, null, $status);
    }

    /**
     * Make sure the referer points to our site, avoiding open external redirects
     *
     * @return bool
     */
    public function checkReferer() {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];

            #TODO: wrap this in a global function
            if (!preg_match(';^https?://'.$this->master->settings->main->site_url.'/;i', $referer)) {
                return false;
            }

            $this->referer = $referer;
            return true;
        }
        return false;
    }

    /**
     * Save the request data in a cookie so we can repopulate the page
     *
     * @return void
     */
    public function setErrorCookie() {
        # Stash both get and post data in a cookie
        $data = [
            'get'   => $this->get,
            'post'  => $this->post,
        ];
        $cookie = json_encode($data);
        $encryptedCookie = $this->master->crypto->encrypt($cookie, 'error');
        $this->setCookie('error', $encryptedCookie);
    }

    /**
     * Retrieve the request data from the error cookie
     *
     * @return string
     */
    public function getErrorCookie() {
        $encryptedCookie = $this->getCookie('error');
        $error = null;
        if (!empty($encryptedCookie)) {
            $cookie = $this->master->crypto->decrypt($encryptedCookie, 'error');
            if (!empty($cookie)) {
                $error = json_decode($cookie, true);
            }
            if (!is_array($error)) {
                $error = ['get' => [], 'post' => []];
            }
            $this->deleteCookie('error');
        }
        if (empty($error)) {
            $error = ['get' => [], 'post' => []];
        }

        return $error;
    }


    /**
     * Set the HTTP headers for this request's response
     */
    public function setHttpHeaders() {
        $headers = headers_list();

        if (empty(preg_grep('/^Content-Security-Policy/', $headers)) === true) {
            # For now, we only have CSP
            $this->setContentSecurityPolicy();
        }
    }

    /**
     * Set the Content Security Policy for this request's response
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy
     */
    public function setContentSecurityPolicy() {
        $imagehosts = $this->master->cache->getValue('imagehost_csp');
        if ($imagehosts === false) {
            $imagehosts = $this->master->db->rawQuery("SELECT Imagehost FROM imagehost_whitelist")->fetchAll();
            $imagehosts = implode(' ', array_column($imagehosts, 'Imagehost'));
            $this->master->cache->cacheValue('imagehost_csp', $imagehosts, 0);
        }

        $defaultSrc = ["'self'"];

        $imgSrc = [
            "'self'",
            "data:",
            "$imagehosts"
        ];

        $childSrc = [
            "'self'",
            $imagehosts
        ];

        $scriptSrc = [
            "'self'",
            "'unsafe-inline'",
            "'unsafe-eval'",
        ];

        $connectSrc = ["'self'"];

        if ($this->master->options->HaveIBeenPwned) {
            $connectSrc[] = "https://api.pwnedpasswords.com";
        }

        $styleSrc = [
            "'self'",
            "'unsafe-inline'",
        ];

        $fontSrc = [
            "'self'",
            "data:"
        ];

        # Gather all directives
        $csp = [
            "default-src" => $defaultSrc,
            "img-src"     => $imgSrc,
            "child-src"   => $childSrc,
            "script-src"  => $scriptSrc,
            "connect-src" => $connectSrc,
            "style-src"   => $styleSrc,
            "font-src"    => $fontSrc,
        ];

        # Format the CSP into a string
        $header = 'Content-Security-Policy: ';
        foreach ($csp as $directive => $rules) {
            $header .= $directive.'  '.implode(' ', $rules).'; ';
        };

        # Send the header
        header(trim($header));
    }
}
