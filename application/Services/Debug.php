<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

define('MAX_TIME', 20000); #Maximum execution time in ms
define('MAX_ERRORS', 0); #Maxmimum errors, warnings, notices we will allow in a page
define('MAX_MEMORY', 80*1024*1024); #Maximum memory used per pageload
define('MAX_QUERIES', 30); #Maxmimum queries

class Debug extends Service {

    protected static $useServices = [
        'auth'      => 'Auth',
        'cache'     => 'Cache',
        'db'        => 'DB',
        'irker'     => 'Irker',
        'search'    => 'Search',
        'settings'  => 'Settings',
    ];

    public $errors = [];
    public $gitCommit = [];
    public $loggedVars = [];

    public static $flags  = [];
    public static $startTime = 0;

    private static $enabled = true;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    public function start() {
        self::$startTime = microtime(true);
        $this->commit  = $this->getGitCommit();
    }

    public function profile($automatic = '') {
        $reason = [];

        if (!empty($automatic)) {
            $reason[] = $automatic;
        }

        $milli = (microtime(true)-self::$startTime)/1000;
        if ($milli > MAX_TIME && !defined('TIME_EXCEPTION')) {
            $reason[] = number_format($milli, 3).' ms';
        }

        $RAM = memory_get_usage(true);
        if ($RAM > MAX_MEMORY && !defined('MEMORY_EXCEPTION')) {
            $reason[] = get_size($RAM).' Ram Used';
        }

        if (isset($_REQUEST['profile'])) {
            $reason[] = 'Requested by '.$this->master->request->user->Username;
        }

        if (isset($reason[0])) {
            $this->analysis(implode(', ', $reason));

            return true;
        }

        return false;
    }

    public function analysis($message, $report = '', $time = 43200) {
        if (empty($report)) {
            $report = $message;
        }
        $identifier = make_secret(5);
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $this->cache->cacheValue(
            'analysis_'.display_str($identifier),
            [
                'url'      => $url,
                'message'  => $report,
                'queries'  => $this->db->queries,
                'flags'    => self::$flags,
                'cache'    => $this->cache->cacheHits,
                'vars'     => $this->loggedVars,
            ],
            $time
        );
        $this->irker->announceLab($message."  http://{$this->settings->main->site_url}/tools.php?action=analysis&case={$identifier} http://{$this->settings->main->site_url}{$url}");
    }

    public function logVar($var, $varName = false) {
        $backTrace = debug_backtrace();
        $ID = uniqid();
        if ($varName === false) {
            $varName = $ID;
        }
        $file = ['path' => substr($backTrace[0]['file'], strlen($this->master->applicationPath)), 'line' => $backTrace[0]['line']];
        $this->loggedVars[$ID] = [$varName => ['bt' => $file, 'data' => $var]];
    }

    public static function setFlag($event) {
        if (!self::$enabled) {
            return;
        }
        self::$flags[] = [
            'event'     => $event,
            'microtime' => (microtime(true)-self::$startTime)*1000,
            'memory'    => memory_get_usage(true),
        ];
    }

    public function getFlags() {
        return self::$flags;
    }

    # This isn't in the constructor because $this is not available, and the function cannot be made static
    public function handleErrors() {
        if ($this->settings->site->debug_mode) {
            error_reporting(E_ALL);
        } else {
            error_reporting(E_WARNING | E_ERROR | E_PARSE);
        }
    }

    protected function formatArgs($array) {
        $lastKey = -1;
        $return = [];
        foreach ($array as $key => $val) {
            $return[$key] = '';
            if (!is_int($key) || !($key === $lastKey+1)) {
                $return[$key] .= "'$key' => ";
            }
            if ($val === true) {
                $return[$key] .= "true";
            } elseif ($val === false) {
                $return[$key] .= "false";
            } elseif (is_string($val)) {
                $return[$key] .= "'$val'";
            } elseif (is_int($val)) {
                $return[$key] .= $val;
            } elseif (is_object($val)) {
                $return[$key] .= get_class($val);
            } elseif (is_array($val)) {
                $return[$key] .= 'array('.$this->formatArgs($val).')';
            }
            $lastKey = $key;
        }

        return implode(', ', $return);
    }

    public function phpErrorHandler($level, $error, $file, $line) {
        # shortcut out this function (for now)
        return true;
        $steps = 1; # Steps to go up in backtrace, default one
        $call = '';
        $args = '';
        $tracer = debug_backtrace();

        # This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (isset($tracer[$steps]['function'], $tracer[$steps]['class']) && $tracer[$steps]['function'] === 'phpErrorHandler' && $tracer[$steps]['class'] === 'Debug') {
            return true;
        }

        # If this error was thrown, we return the function which threw it
        if (isset($tracer[$steps]['function']) && $tracer[$steps]['function'] === 'trigger_error') {
            $steps++;
            $file = $tracer[$steps]['file'];
            $line = $tracer[$steps]['line'];
        }

        # At this time ONLY Array strict typing is fully supported.
        # Allow us to abuse strict typing (IE: function test(Array))
        if (preg_match('/^Argument (\d+) passed to \S+ must be an (array) , (array|string|integer|double|object) given, called in (\S+) on line (\d+) and defined$/', $error, $matches)) {
            $error = 'Type hinting failed on arg '.$matches[1]. ', expected '.$matches[2].' but found '.$matches[3];
            $file = $matches[4];
            $line = $matches[5];
        }

        # Lets not be repetative
        if (($tracer[$steps]['function'] === 'include' || $tracer[$steps]['function'] === 'require' ) && isset($tracer[$steps]['args'][0]) && $tracer[$steps]['args'][0] === $file) {
            unset($tracer[$steps]['args']);
        }

        # Class
        if (isset($tracer[$steps]['class'])) {
            $call .= $tracer[$steps]['class'].'::';
        }

        # Function & args
        if (isset($tracer[$steps]['function'])) {
            $call .= $tracer[$steps]['function'];
            if (isset($tracer[$steps]['args'][0])) {
                $args = $this->formatArgs($tracer[$steps]['args']);
            }
        }

        # Shorten the path & we're done
        $file = str_replace($this->master->applicationPath, '', $file);
        $error = str_replace($this->master->applicationPath, '', $error);

        return true;
    }

    /* Data wrappers */
    public function getConstants() {
        return get_defined_constants(true);
    }

    public function getClasses() {
        foreach (get_declared_classes() as $class) {
            $classes[$class]['Vars'] = get_class_vars($class);
            $classes[$class]['Functions'] = get_class_methods($class);
        }

        return $classes;
    }

    public function getExtensions() {
        foreach (get_loaded_extensions() as $extension) {
            $extensions[$extension]['Functions'] = get_extension_funcs($extension);
        }

        return $extensions;
    }

    public function getIncludes() {
        return get_included_files();
    }

    /* Output Formatting */

    public function includeTable($includes = false) {
        if (!is_array($includes)) {
            $includes = $this->getIncludes();
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_include').toggle();return false;">(View)</a> <?=number_format(count($includes))?> Includes:</strong></td>
        </tr>
    </table>
    <table id="debug_include" class="debug_table hidden" width="100%">
        <?php
        foreach ($includes as $file) {
            ?>
<tr valign="top">
    <td><?=$file?></td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function classTable($classes = false) {
        if (!is_array($classes)) {
            $classes = $this->getClasses();
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_classes').toggle();return false;">(View)</a> Classes:</strong></td>
        </tr>
    </table>
    <table id="debug_classes" class="debug_table hidden" width="100%">
        <tr>
            <td align="left">
                <pre><?php print_r($classes) ?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    public function extensionTable() {
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_extensions').toggle();return false;">(View)</a> Extensions:</strong></td>
        </tr>
    </table>
    <table id="debug_extensions" class="debug_table hidden" width="100%">
        <tr>
            <td align="left">
                <pre><?php print_r($this->getExtensions()) ?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    # Borrowed from git source code
    protected function unpackGitPackHeader($buf, &$type, &$size) {
        $used = 0;

        $c = $buf[1+$used++];
        $type = ($c >> 4) & 7;
        $size = $c & 15;
        $shift = 4;
        while ($c & 0x80) {
            # Header can't be full object/buffer
            # Can't shift beyond the integer boundry
            if (64 <= $used || 32 <= $shift) {
                return false;
            }
            $c = $buf[1+$used++];
            $size += ($c & 0x7f) << $shift;
            $shift += 7;
        }
        return $used;
    }

    public function getGitCommit() {
        # Read the HEAD file, it should look like
        # ref: <reference path>
        $gitHead=@file_get_contents("../.git/HEAD");
        if ($gitHead === false) return;

        # Git hashes are exactly 40 chars... plus null term char
        if (strlen($gitHead) === 41) {
            # Detached head state will cause this.
            $gitRef = $gitHead;
        } else {
            $gitHead = trim(explode(' ', $gitHead)[1]);

            # Fetch the commit hash from the reference and
            # split it into an object reference
            $gitRef=@file_get_contents("../.git/".$gitHead);

            # Shit, it's been packed!
            if ($gitRef === false) {
                $packedRefs=@file("../.git/packed-refs");
                foreach ($packedRefs as $gitRef) {
                    $parts = explode(' ', $gitRef);
                    if (trim($parts[1]) === $gitHead) {
                        $gitRef = $parts[0];
                        break;
                    }
                }
            }
        }

        $gitRef=trim($gitRef);
        # Check the cache first!
        $gitCommit = $this->cache->getValue("commit_{$gitRef}");

        # Shit, not cached, go fetch it from disk
        if ($gitCommit === false) {
            $gitRefBase=substr($gitRef, 0, 2);
            $gitRefObject=substr($gitRef, 2);

            # Fetch and uncompress the commit object
            $rawCommit=@file_get_contents("../.git/objects/".$gitRefBase.'/'.$gitRefObject);

            if ($rawCommit === false) {
                # Fuck, it must be a packed object. :-(
                # Seach the index files for the reference.
                foreach (glob($this->master->applicationPath."/../.git/objects/pack/*.idx") as $gitIndexFile) {
                    $rawIndex = file_get_contents($gitIndexFile);
                    $gitIndex = unpack('N*', substr($rawIndex, 0, 1032));
                    # Magic shit at the start of the file.
                    if ($gitIndex[1] === 4285812579 && $gitIndex[2] === 2) {
                        # We got a good index file!
                        for ($index = 0; $index < $gitIndex[258]; $index++) {
                            $gitCommit = substr($rawIndex, 1032+(20*$index), 20);
                            $gitCommit = unpack('H40', $gitCommit)[1];
                            if ($gitCommit === $gitRefBase.$gitRefObject) {
                                # Found it!
                                $offset = 20*$gitIndex[258] + 4*$gitIndex[258] + 4*$index + 1032;
                                $offset = unpack('N*', substr($rawIndex, $offset, 4))[1];
                                break 2;
                            }
                        }
                    }
                }

                # Extract the commit data.
                $rawPack = file_get_contents(str_replace('.idx', '.pack', $gitIndexFile));
                $gitHead = unpack('C*', substr($rawPack, $offset, 64));
                $type = 0;
                $size = 0;
                $dataStart = $this->unpackGitPackHeader($gitHead, $type, $size);

                # Sanity Check
                if (!($type === 1)) return;
                $rawCommit = substr($rawPack, $offset+$dataStart, $size);
            }

            $rawCommit = @gzuncompress($rawCommit);
            if ($rawCommit === false) return;

            # Prepare the commit data so we can display it
            # in a pretty way
            $splitCommit=explode(PHP_EOL, $rawCommit);

            # Build a proper commit Array
            unset($gitCommit);
            $gitCommit['Commit']  = $gitRef;
            $gitCommit['Author']  = explode(' ', $splitCommit[2])[1];
            $gitCommit['Date']    = \Date("Y-m-d H:i:s", explode(' ', $splitCommit[2])[3]);
            $gitCommit['Comment'] = trim($splitCommit[5]);
            $this->cache->cacheValue("commit_{$gitRef}", $gitCommit, 0);
        }
        return $gitCommit;
    }

    public function constantTable($constants = false) {
        if (!is_array($constants)) {
            $constants = $this->getConstants();
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_constants').toggle();return false;">(View)</a> Constants:</strong></td>
        </tr>
    </table>
    <table id="debug_constants" class="debug_table hidden" width="100%">
        <tr>
            <td align="left" class="debug_data debug_constants_data">
                <pre><?=display_str(print_r($constants, true))?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    public static function enable() {
        self::$enabled = true;
    }

    public static function disable() {
        self::$enabled = false;
    }

    public static function getEnabled() {
        return self::$enabled;
    }
}
