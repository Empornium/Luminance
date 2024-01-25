<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\SystemError;

use Luminance\Services\Debug;

class Cache extends Service {
    public $hits  = [];
    public $hot = [];
    public $times = [];
    private $casTokens = [];
    private static $enabled = true;
    protected $apcuHotcache = false;
    protected $apcuPrefix = null;
    public $time = 0;
    private $persistenKeys = [
        'stats_*',
        'percentiles_*',
        'top10tor_*'
    ];

    private $casToken = null;
    private $memcached = null;

    public function __construct(Master $master) {
        parent::__construct($master);
        $host = $master->settings->memcached->host;
        $port = $master->settings->memcached->port;

        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->apcuPrefix = $master->settings->keys->apcu_prefix;
            if (!empty($this->apcuPrefix)) {
                $this->apcuHotcache = true;
            }
        }

        if (is_null($this->memcached)) {
            # Detect memcached presence
            if (class_exists('\memcached')) {
                if (substr($host, 0, 7) === "unix://") {
                    $host = str_replace('unix://', '', $host);
                    $port = 0;
                } else {
                    $port = (int)$port;
                }

                $this->memcached = new \Memcached("{$host}:{$port}_Luminance");
                $servers = $this->memcached->getServerList();
                if (empty($servers)) {
                    $options = [
                        #\Memcached::OPT_BINARY_PROTOCOL => true, # More trouble than it's worth
                        \Memcached::OPT_TCP_NODELAY     => true,
                        \Memcached::OPT_VERIFY_KEY      => true,
                    ];

                    if (method_exists($this->memcached, 'flushBuffers')) {
                        #$options[\Memcached::OPT_BUFFER_WRITES] = true; # Breaks on PHP 8.2, need to debug more
                        $options[\Memcached::OPT_NO_BLOCK     ] = true;
                    }

                    if (extension_loaded('igbinary')) {
                        if ($this->memcached->getOption(\Memcached::HAVE_IGBINARY)) {
                            $options[\Memcached::OPT_SERIALIZER] = \Memcached::SERIALIZER_IGBINARY;
                        }
                    }
                    $this->memcached->setOptions($options);
                    $this->memcached->addServer($host, $port);
                }
            } else {
                self::$enabled = false;
            }
        }
    }

    public function __destruct() {
        $this->flushBuffers();
    }

    public function getOptions() {
        $options['binary protocol'] = $this->memcached->getOption(\Memcached::OPT_BINARY_PROTOCOL);
        $options['no block']        = $this->memcached->getOption(\Memcached::OPT_NO_BLOCK);
        $options['tcp no delay']    = $this->memcached->getOption(\Memcached::OPT_TCP_NODELAY);
        $options['serializer']      = $this->memcached->getOption(\Memcached::OPT_SERIALIZER);
        $options['buffer writes']   = $this->memcached->getOption(\Memcached::OPT_BUFFER_WRITES);
        return $options;
    }

    public static function disable() {
        self::$enabled = false;
    }

    public static function enable() {
        self::$enabled = true;
    }

    private function apcuKey($key) {
        return "{$this->apcuPrefix}_{$key}";
    }

    private function checkKey(string $key) {
        if (!mb_check_encoding($key, 'ASCII')) {
            throw new SystemError('Invalid characters in cache key (non-ASCII)');
        }

        if (!preg_match('/[0-9a-zA-Z_]/', $key)) {
            throw new SystemError('Invalid characters in cache key (Control/Whitespace)');
        }

        if (empty($key)) {
            throw new SystemError('Invalid cache key (Empty)');
        }
    }

    public function flush() {
        $this->memcached->flush();

        if ($this->apcuHotcache) {
            apcu_clear_cache();
        }
    }

    public function getStats($args = null) {
        $stats = $this->memcached->getStats($args);
        $servers = array_keys($stats);
        $stats = $stats[$servers[0]];
        return $stats;
    }

    #---------- Caching functions ----------#

    # Allows us to set an expiration on otherwise perminantly cache'd values
    # Useful for disabled users, locked threads, basically reducing ram usage
    public function expireValue($key, $duration = 2592000) {
        if (!self::$enabled) return;
        $startTime=microtime(true);

        $this->checkKey($key);

        # Remove from hotcache
        if ($this->apcuHotcache) {
            $apcuKey = $this->apcuKey($key);
            apcu_delete($apcuKey);
        }

        $this->memcached->touch($key, $duration);
        $this->time+=(microtime(true)-$startTime)*1000;
    }

    # Wrapper for Memcache::set, with the zlib option removed and default duration of 30 days
    public function cacheValue($key, $value, $duration = 2592000, &$casToken = null) {
        if (!self::$enabled) return;
        $startTime=microtime(true);

        $this->checkKey($key);

        if (is_null($casToken)) {
            $this->memcached->set($key, $value, (int)$duration);
            $success = $this->memcached->getResultCode();
        } else {
            $this->memcached->cas($casToken, $key, $value, $duration);
            $success = $this->memcached->getResultCode();

            # Check if out CAS token is out of date
            if ($success === \Memcached::RES_DATA_EXISTS) {
                # Someone else beat us to this key, clear it
                # to force a reload from the DB.
                $this->deleteValue($key);
            } else {
                # Update the CAS token
                $update = $this->memcached->get($key, null, \Memcached::GET_EXTENDED);
                $success = $this->memcached->getResultCode();
                if ($success === \Memcached::RES_SUCCESS) {
                    $casToken = $update['cas'];
                }
            }
        }

        if ($success === \Memcached::RES_SUCCESS || $success === \Memcached::RES_BUFFERED) {
            if ($this->apcuHotcache) {
                $apcuKey = $this->apcuKey($key);
                apcu_store($apcuKey, [$casToken, $value], $duration);
            }
        } else {
            //trigger_error("Cache insert failed for key {$key}");
        }

        $this->time+=(microtime(true)-$startTime)*1000;
    }

    public function replaceValue($key, $value, $duration = 2592000) {
        if (!self::$enabled) return;
        $startTime=microtime(true);

        $this->checkKey($key);

        $this->memcached->replace($key, $value, $duration);
        $success = $this->memcached->getResultCode();
        $this->hits[$key] = $value;
        unset($this->casTokens[$key]);

        if ($success === \Memcached::RES_SUCCESS && $this->apcuHotcache) {
            $apcuKey = $this->apcuKey($key);
            apcu_store($apcuKey, [null, $value], $duration);
        }

        $this->time+=(microtime(true)-$startTime)*1000;
    }

    public function getValue($key, $noCache = false, &$casToken = null) {
        if (!self::$enabled) {
            # Must return false otherwise logic will accept "null" as the value
            return false;
        }

        $this->checkKey($key);

        $startTime=microtime(true);

        if (empty($key)) {
            //trigger_error("Cache retrieval failed for empty key");
            return false;
        }

        # If a key's already loaded grab the existing pointer
        if ($noCache === false) {
            if (array_key_exists($key, $this->hits)) {
                $this->time+=(microtime(true)-$startTime)*1000;
                if (array_key_exists($key, $this->casTokens)) {
                    $casToken = $this->casTokens[$key];
                    $value = $this->hits[$key];
                    return $value;
                }
            }
        }

        # Otherwise, if the hotcache is enabled grab it from there
        if ($this->apcuHotcache === true && $noCache === false) {
            $apcuKey = $this->apcuKey($key);
            $success = false;
            list($casToken, $value) = apcu_fetch($apcuKey, $success);
            if ($success === true) {
                $this->hits[$key] = $value;
                $this->hot[$key] = true;
                $this->casTokens[$key] = $casToken;
                $endTime = microtime(true);
                $this->times[$key] = ($endTime-$startTime)*1000;
                return $value;
            }
        }

        # Finally, try to fetch from memcached
        $result = $this->memcached->get($key, null, \Memcached::GET_EXTENDED);
        $success = $this->memcached->getResultCode();

        $endTime = microtime(true);

        if ($success === \Memcached::RES_SUCCESS) {
            $casToken = $result['cas'];
            $value = $result['value'];

            # Store in hotcache
            if ($this->apcuHotcache) {
                $apcuKey = $this->apcuKey($key);
                apcu_store($apcuKey, [$casToken, $value], 900); # auto-hotcache for 15 mins
            }

            # Store in local cache
            if ($noCache === false) {
                $this->hits[$key] = $value;
                $this->hot[$key] = false;
                $this->casTokens[$key] = $casToken;
                if (Debug::getEnabled()) {
                    $this->times[$key] = ($endTime-$startTime)*1000;
                    $this->time+=($endTime-$startTime)*1000;
                }
            }
            return $result['value'];
        }

        # Default is return false (cache miss)
        return false;
    }

    # Wrapper for Memcache::delete. For a reason, see above.
    public function deleteValue($key) {
        if (!self::$enabled) return;
        $startTime=microtime(true);

        $this->checkKey($key);

        # Remove from hotcache
        if ($this->apcuHotcache) {
            $apcuKey = $this->apcuKey($key);
            apcu_delete($apcuKey);
        }

        # Remove from memcached
        $this->memcached->delete($key);
        $this->time+=(microtime(true)-$startTime)*1000;
    }

    public function incrementValue(string $key, int $offset = 1) {
        if (!self::$enabled) return;
        $this->checkKey($key);

        $this->memcached->increment($key, $offset);
        $success = $this->memcached->getResultCode();

        if ($success === \Memcached::RES_SUCCESS || $success === \Memcached::RES_BUFFERED) {
            # Remove from hotcache
            if ($this->apcuHotcache) {
                $apcuKey = $this->apcuKey($key);
                apcu_delete($apcuKey);
            }
        } else {
            //trigger_error("Failed to increment {$key}");
        }
    }

    public function decrementValue(string $key, int $offset = 1) {
        if (!self::$enabled) return;
        $this->checkKey($key);

        $this->memcached->decrement($key, $offset);
        $success = $this->memcached->getResultCode();

        if ($success === \Memcached::RES_SUCCESS || $success === \Memcached::RES_BUFFERED) {
            # Remove from hotcache
            if ($this->apcuHotcache) {
                $apcuKey = $this->apcuKey($key);
                apcu_delete($apcuKey);
            }
        } else {
            //trigger_error("Failed to decrement {$key}");
        }
    }

    public function flushBuffers() {
        if (method_exists($this->memcached, 'flushBuffers')) {
            $this->memcached->flushBuffers();
        }
    }
}
