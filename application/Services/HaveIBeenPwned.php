<?php


namespace Luminance\Services;

use GuzzleHttp\Client;
use Luminance\Core\Service;

class HaveIBeenPwned extends Service
{
    protected static $useServices = [
        'cache' => 'Cache',
    ];

    const API = 'https://api.pwnedpasswords.com/range/';
    const LEN = 5;

    const EXPOSED     = 1;
    const NOT_EXPOSED = 2;
    const UNKNOWN     = 3;

    public $password;

    public $hash;
    public $prefix;
    public $suffix;

    /**
     * @param $password
     * @return int
     */
    public function check($password) {
        $this->init($password);

        // Avoid any API call if it's cached
        if ($cache = $this->checkCache()) {
            return $cache;
        }

        $response = $this->api();

        // If for some reason, the API call failed
        if ($response === false) {
            return self::UNKNOWN;
        }

        $pwned = $this->processResponse($response) ? self::EXPOSED : self::NOT_EXPOSED;

        $this->cache($pwned);
        return $pwned;
    }

    /**
     * @param $password
     */
    private function init($password) {
        $this->password = $password;
        $this->hash();
    }

    /**
     * @param $pwned
     * @return bool
     */
    private function cache($pwned) {
        if (!$this->cache) {
            return false;
        }

        // The results are cached for a month
        $this->cache->cache_value($this->cacheKey(), $pwned);
        return true;
    }

    /**
     * @return int
     */
    private function checkCache() {
        if (!$this->cache) {
            return 0;
        }

        return (int) $this->cache->get_value($this->cacheKey());
    }

    /**
     * Returns the cache key we'll use
     * @return string
     */
    private function cacheKey() {
        return "hibp_{$this->hash}";
    }

    private function hash() {
        $this->hash   = sha1($this->password);
        $this->prefix = substr($this->hash, 0, self::LEN);
        $this->suffix = substr($this->hash, self::LEN);
    }

    /**
     * @return bool|string
     */
    private function api() {
        $http = new Client();

        try {
            $response = (string) $http->get($this->createUri())->getBody();
        } catch (\Exception $e) {
            $response = false;
            error_log("HTTP call against HaveIBeenPwned API failed: ".$e->getMessage());
        }

        return $response;
    }

    /**
     * @return string
     */
    private function createUri() {
        return self::API.$this->prefix;
    }

    /**
     * @param $response
     * @return bool
     */
    private function processResponse($response) {
        return (bool) preg_match("/{$this->suffix}:\d+/i", $response);
    }
}
