<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Core\Entity;

class Cache extends Service {
    public $CacheHits  = array();
    public $CacheTimes = array();
    public $MemcacheDBArray = array();
    public $MemcacheDBKey = '';
    protected $InTransaction = false;
    protected $enable = true;
    protected $enable_debug = true;
    public $Time = 0;
    private $PersistentKeys = array(
        'stats_*',
        'percentiles_*',
        'top10tor_*'
    );

    private $Memcached = null;

    public $CanClear = false;

    public function __construct(Master $master) {
        parent::__construct($master);
        $host = $master->settings->memcached->host;
        $port = $master->settings->memcached->port;

        if (is_null($this->Memcached)) {
            // Compatibility, prefer Memcached
            if (class_exists('\Memcached')) {
                if (substr($host, 0, 7) === "unix://") {
                    $host = str_replace('unix://', '', $host);
                    $port = 0;
                } else {
                    $port = (int)$port;
                }

                $this->Memcached = new \Memcached("{$host}:{$port}_Luminance");
                $servers = $this->Memcached->getServerList();
                if (empty($servers)) {
                    $this->Memcached->addServer($host, $port);
                }

            // Compatibility, fallback to Memcache
            } elseif (class_exists('\Memcache')) {
                $this->Memcached = new \Memcache();
                @$this->Memcached->pconnect($host, $port);
            } else {
                $this->enable = false;
            }
        }
    }

    public function __call($func, $params) {
        if (method_exists($this->Memcached, $func)) {
            return call_user_func_array([$this->Memcached, $func], (array)$params);
        } else {
            return call_user_func_array([$this, $func], (array)$params);
        }
    }

    public function disable() {
        $this->enable = false;
    }

    public function enable() {
        if (!is_null($this->Memcached)) {
            $this->enable = true;
        }
    }

    public function enable_debug() {
        $this->enable_debug = true;
    }

    public function disable_debug() {
        $this->enable_debug = false;
    }

    public function getStats($args = null) {
        $stats = $this->Memcached->getStats($args);
        if (is_subclass_of($this, '\Memcached')) {
            $servers = array_keys($stats);
            $stats = $stats[$servers[0]];
        }
        return $stats;
    }

    //---------- Caching functions ----------//

    // Allows us to set an expiration on otherwise perminantly cache'd values
    // Useful for disabled users, locked threads, basically reducing ram usage
    public function expire_value($Key, $Duration = 2592000) {
        $StartTime=microtime(true);
        $this->Memcached->set($Key, @$this->Memcached->get($Key), $Duration);
        $this->Time+=(microtime(true)-$StartTime)*1000;
    }

    // Wrapper for Memcache::set, with the zlib option removed and default duration of 30 days
    public function cache_value($Key, $Value, $Duration = 2592000) {
        if (!$this->enable) return;
        $StartTime=microtime(true);
        if (empty($Key)) {
            //trigger_error("Cache insert failed for empty key");
        }

        // Default parameters for Memcache set function
        if ($this->Memcached instanceof \Memcache) {
            $success = $this->Memcached->set($Key, $Value, 0, $Duration);
        } else {
            $success = $this->Memcached->set($Key, $Value, $Duration);
        }

        if (!$success) {
            //trigger_error("Cache insert failed for key $Key");
        }

        $this->Time+=(microtime(true)-$StartTime)*1000;
    }

    public function replace_value($Key, $Value, $Duration = 2592000) {
        if (!$this->enable) return;
        $StartTime=microtime(true);

        // Memcached uses only 3 parameters (no flag)
        if ($this->Memcached instanceof \Memcache) {
            $this->Memcached->replace($Key, $Value, false, $Duration);
        } else {
            $this->Memcached->replace($Key, $Value, $Duration);
        }

        $this->Time+=(microtime(true)-$StartTime)*1000;
    }

    public function get_value($Key, $NoCache = false) {
        if (!$this->enable) return;
        $StartTime=microtime(true);
        if (empty($Key)) {
            trigger_error("Cache retrieval failed for empty key");
        }

        if (isset($_GET['clearcache']) && $this->CanClear && !in_array_partial($Key, $this->PersistentKeys)) {
            if ($_GET['clearcache'] == 1) {
                //Because check_perms isn't true until loggeduser is pulled from the cache, we have to remove the entries loaded before the loggeduser data
                //Because of this, not user cache data will require a secondary pageload following the clearcache to update
                if (count($this->CacheHits) > 0) {
                    foreach (array_keys($this->CacheHits) as $HitKey) {
                        if (!in_array_partial($HitKey, $this->PersistentKeys)) {
                            $this->Memcached->delete($HitKey);
                            unset($this->CacheHits[$HitKey]);
                            unset($this->CacheTimes[$HitKey]);
                        }
                    }
                }
                $this->Memcached->delete($Key);
                $this->Time+=(microtime(true)-$StartTime)*1000;

                return false;
            } elseif ($_GET['clearcache'] == $Key) {
                $this->Memcached->delete($Key);
                $this->Time+=(microtime(true)-$StartTime)*1000;

                return false;
            } elseif (in_array($_GET['clearcache'], $this->CacheHits)) {
                unset($this->CacheHits[$_GET['clearcache']]);
                unset($this->CacheTimes[$_GET['clearcache']]);
                $this->Memcached->delete($_GET['clearcache']);
            }
        }

        //For cases like the forums, if a keys already loaded grab the existing pointer
        if (isset($this->CacheHits[$Key]) && !$NoCache) {
            $this->Time+=(microtime(true)-$StartTime)*1000;
            return $this->CacheHits[$Key];
        }

        $Return = @$this->Memcached->get($Key);
        $EndTime = microtime(true);
        if ($Return !== false && !$NoCache && $this->enable_debug) {
            if (count($this->CacheHits) < 200) {
                $this->CacheHits[$Key]  = $Return;
                $this->CacheTimes[$Key] = ($EndTime-$StartTime)*1000;
            }
            $this->Time+=($EndTime-$StartTime)*1000;
        }

        return $Return;
    }

    // Wrapper for Memcache::delete. For a reason, see above.
    public function delete_value($Key) {
        if (!$this->enable) return;
        $StartTime=microtime(true);
        @$this->Memcached->delete($Key);
        $this->Time+=(microtime(true)-$StartTime)*1000;
    }

    //---------- memcachedb functions ----------//

    public function begin_transaction($Key) {
        if (!$this->enable) return;
        $Value = @$this->Memcached->get($Key);
        if (!is_array($Value)) {
            $this->InTransaction = false;
            $this->MemcacheDBKey = array();
            $this->MemcacheDBKey = '';

            return false;
        }
        $this->MemcacheDBArray = $Value;
        $this->MemcacheDBKey = $Key;
        $this->InTransaction = true;

        return true;
    }

    public function cancel_transaction() {
        if (!$this->enable) return;
        $this->InTransaction = false;
        $this->MemcacheDBKey = array();
        $this->MemcacheDBKey = '';
    }

    public function commit_transaction($Time = 2592000) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        $this->cache_value($this->MemcacheDBKey, $this->MemcacheDBArray, $Time);
        $this->InTransaction = false;
    }

    // Updates multiple rows in an array
    public function update_transaction($Rows, $Values) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        $Array = $this->MemcacheDBArray;
        if (is_array($Rows)) {
            $i = 0;
            $Keys = $Rows[0];
            $Property = $Rows[1];
            foreach ($Keys as $Row) {
                $Array[$Row][$Property] = $Values[$i];
                $i++;
            }
        } else {
            $Array[$Rows] = $Values;
        }
        $this->MemcacheDBArray = $Array;
    }

    // Updates multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    public function update_row($Row, $Values) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if ($Row === false) {
            $UpdateArray = $this->MemcacheDBArray;
        } else {
            $UpdateArray = $this->MemcacheDBArray[$Row];
        }
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error('Bad transaction key ('.$Key.') for cache '.$this->MemcacheDBKey);
            }
            if ($Value === '+1') {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to increment non-number ('.$Key.') for cache '.$this->MemcacheDBKey);
                }
                ++$UpdateArray[$Key]; // Increment value
            } elseif ($Value === '-1') {
                if (!is_number($UpdateArray[$Key])) {
                    trigger_error('Tried to decrement non-number ('.$Key.') for cache '.$this->MemcacheDBKey);
                }
                --$UpdateArray[$Key]; // Decrement value
            } else {
                $UpdateArray[$Key] = $Value; // Otherwise, just alter value
            }
        }
        if ($Row === false) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }

    // Increments multiple values in a single row in an array
    // $Values must be an associative array with key:value pairs like in the array we're updating
    public function increment_row($Row, $Values) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if ($Row === false) {
            $UpdateArray = $this->MemcacheDBArray;
        } else {
            $UpdateArray = $this->MemcacheDBArray[$Row];
        }
        foreach ($Values as $Key => $Value) {
            if (!array_key_exists($Key, $UpdateArray)) {
                trigger_error('Bad transaction key ('.$Key.') for cache '.$this->MemcacheDBKey);
            }
            if (!is_number($Value)) {
                trigger_error('Tried to increment with non-number ('.$Key.') for cache '.$this->MemcacheDBKey);
            }
            $UpdateArray[$Key] += $Value; // Increment value
        }
        if ($Row === false) {
            $this->MemcacheDBArray = $UpdateArray;
        } else {
            $this->MemcacheDBArray[$Row] = $UpdateArray;
        }
    }

    // Insert a value at the beginning of the array
    public function insert_front($Key, $Value) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            array_unshift($this->MemcacheDBArray, $Value);
        } else {
            $this->MemcacheDBArray = array($Key=>$Value) + $this->MemcacheDBArray;
        }
    }

    // Insert a value at the end of the array
    public function insert_back($Key, $Value) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            array_push($this->MemcacheDBArray, $Value);
        } else {
            $this->MemcacheDBArray = $this->MemcacheDBArray + array($Key=>$Value);
        }
    }

    public function insert($Key, $Value) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if ($Key === '') {
            $this->MemcacheDBArray[] = $Value;
        } else {
            $this->MemcacheDBArray[$Key] = $Value;
        }
    }

    public function delete_row($Row) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            return false;
        }
        if (!isset($this->MemcacheDBArray[$Row])) {
            trigger_error('Tried to delete non-existent row ('.$Row.') for cache '.$this->MemcacheDBKey);
        }
        unset($this->MemcacheDBArray[$Row]);
    }

    public function update($Key, $Rows, $Values, $Time = 2592000) {
        if (!$this->enable) return;
        if (!$this->InTransaction) {
            $this->begin_transaction($Key);
            $this->update_transaction($Rows, $Values);
            $this->commit_transaction($Time);
        } else {
            $this->update_transaction($Rows, $Values);
        }
    }
}
