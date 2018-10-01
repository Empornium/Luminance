<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

define('MAX_TIME', 20000); //Maximum execution time in ms
define('MAX_ERRORS', 0); //Maxmimum errors, warnings, notices we will allow in a page
define('MAX_MEMORY', 80*1024*1024); //Maximum memory used per pageload
define('MAX_QUERIES', 30); //Maxmimum queries

class Debug extends Service {

    protected static $useServices = [
        'auth'     => 'Auth',
        'cache'    => 'Cache',
        'db'       => 'DB',
        'search'   => 'Search',
    ];

    public $Errors = array();
    public $Flags = array();
    public $StartTime = 0;
    private $LoggedVars = array();

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    public function start() {
        $this->StartTime = microtime(true);
    }

    public function profile($Automatic = '') {
        $Reason = array();

        if (!empty($Automatic)) {
            $Reason[] = $Automatic;
        }

        $Micro = (microtime(true)-$this->StartTime)*1000;
        if ($Micro > MAX_TIME && !defined('TIME_EXCEPTION')) {
            $Reason[] = number_format($Micro, 3).' ms';
        }

        $Errors = count($this->get_errors());
        if ($Errors > MAX_ERRORS && !defined('ERROR_EXCEPTION')) {
            $Reason[] = $Errors.' PHP Errors';
        }
        /*
        $Queries = count($this->get_queries());
        if ($Queries > MAX_QUERIES && !defined('QUERY_EXCEPTION')) {
            $Reason[] = $Queries.' Queries';
        }
        */
        $Ram = memory_get_usage(true);
        if ($Ram > MAX_MEMORY && !defined('MEMORY_EXCEPTION')) {
            $Reason[] = get_size($Ram).' Ram Used';
        }

        if (isset($_REQUEST['profile'])) {
            $Reason[] = 'Requested by '.$this->master->request->user->Username;
        }

        if (isset($Reason[0])) {
            $this->analysis(implode(', ', $Reason));

            return true;
        }

        return false;
    }

    public function analysis($Message, $Report = '', $Time = 43200) {
        if (empty($Report)) {
            $Report = $Message;
        }
        $Identifier = make_secret(5);
        $this->cache->cache_value(
            'analysis_'.display_str($Identifier),
            array(
                'url'      => $_SERVER['REQUEST_URI'],
                'message'  => $Report,
                'errors'   => $this->get_errors(true),
                'queries'  => $this->db->Queries,
                'flags'    => $this->Flags,
                'includes' => $this->get_includes(),
                'cache'    => $this->cache->CacheHits,
                'vars'     => $this->LoggedVars,
            ),
            $Time
        );
        send_irc('PRIVMSG '.LAB_CHAN.' :'.$Message.'  http://'.SITE_URL.'/tools.php?action=analysis&case='.$Identifier.' http://'.SITE_URL.$_SERVER['REQUEST_URI']);
    }

    public function log_var($Var, $VarName = false) {
        $BackTrace = debug_backtrace();
        $ID = uniqid();
        if (!$VarName) {
            $VarName = $ID;
        }
        $File = array('path' => substr($BackTrace[0]['file'], strlen($this->master->application_path)), 'line' => $BackTrace[0]['line']);
        $this->LoggedVars[$ID] = array($VarName => array('bt' => $File, 'data' => $Var));
    }

    public function set_flag($Event) {
        $this->Flags[] = array($Event,(microtime(true)-$this->StartTime)*1000,memory_get_usage(true));
    }

    //This isn't in the constructor because $this is not available, and the function cannot be made static
    public function handle_errors() {
        //error_reporting(E_ALL ^ E_STRICT | E_WARNING | E_DEPRECATED | E_ERROR | E_PARSE); //E_STRICT disabled
        error_reporting(E_WARNING | E_ERROR | E_PARSE);
        // This is very slow and fucks shit up!
        //set_error_handler(array($this, 'php_error_handler'));
    }

    protected function format_args($Array) {
        $LastKey = -1;
        $Return = array();
        foreach ($Array as $Key => $Val) {
            $Return[$Key] = '';
            if (!is_int($Key) || $Key != $LastKey+1) {
                $Return[$Key] .= "'$Key' => ";
            }
            if ($Val === true) {
                $Return[$Key] .= "true";
            } elseif ($Val === false) {
                $Return[$Key] .= "false";
            } elseif (is_string($Val)) {
                $Return[$Key] .= "'$Val'";
            } elseif (is_int($Val)) {
                $Return[$Key] .= $Val;
            } elseif (is_object($Val)) {
                $Return[$Key] .= get_class($Val);
            } elseif (is_array($Val)) {
                $Return[$Key] .= 'array('.$this->format_args($Val).')';
            }
            $LastKey = $Key;
        }

        return implode(', ', $Return);
    }

    public function php_error_handler($Level, $Error, $File, $Line) {
        //Who added this, it's still something to pay attention to...
        if (stripos('Undefined index', $Error) !== false) {
            //return true;
        }
        // shortcut out this function (for now)
        return true;
        $Steps = 1; //Steps to go up in backtrace, default one
        $Call = '';
        $Args = '';
        $Tracer = debug_backtrace();

        //This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (isset($Tracer[$Steps]['function'], $Tracer[$Steps]['class']) && $Tracer[$Steps]['function'] == 'php_error_handler' && $Tracer[$Steps]['class'] == 'DEBUG') {
            return true;
        }

        //If this error was thrown, we return the function which threw it
        if (isset($Tracer[$Steps]['function']) && $Tracer[$Steps]['function'] == 'trigger_error') {
            $Steps++;
            $File = $Tracer[$Steps]['file'];
            $Line = $Tracer[$Steps]['line'];
        }

        //At this time ONLY Array strict typing is fully supported.
        //Allow us to abuse strict typing (IE: function test(Array))
        if (preg_match('/^Argument (\d+) passed to \S+ must be an (array) , (array|string|integer|double|object) given, called in (\S+) on line (\d+) and defined$/', $Error, $Matches)) {
            $Error = 'Type hinting failed on arg '.$Matches[1]. ', expected '.$Matches[2].' but found '.$Matches[3];
            $File = $Matches[4];
            $Line = $Matches[5];
        }

        //Lets not be repetative
        if (($Tracer[$Steps]['function'] == 'include' || $Tracer[$Steps]['function'] == 'require' ) && isset($Tracer[$Steps]['args'][0]) && $Tracer[$Steps]['args'][0] == $File) {
            unset($Tracer[$Steps]['args']);
        }

        //Class
        if (isset($Tracer[$Steps]['class'])) {
            $Call .= $Tracer[$Steps]['class'].'::';
        }

        //Function & args
        if (isset($Tracer[$Steps]['function'])) {
            $Call .= $Tracer[$Steps]['function'];
            if (isset($Tracer[$Steps]['args'][0])) {
                $Args = $this->format_args($Tracer[$Steps]['args']);
            }
        }

        //Shorten the path & we're done
        $File = str_replace($this->master->application_path, '', $File);
        $Error = str_replace($this->master->application_path, '', $Error);

        /*
        //Hiding "session_start(): Server 10.10.0.1 (tcp 11211) failed with: No route to host (113)" errors
        if ($Call != "session_start") {
            $this->Errors[] = array($Error, $File.':'.$Line, $Call, $Args);
        }
        */

        return true;
    }

    /* Data wrappers */

    public function get_errors($Light = false) {
        //Because the cache can't take some of these variables
        if ($Light) {
            foreach ($this->Errors as $Key => $Value) {
                $this->Errors[$Key][3] = '';
            }
        }

        return $this->Errors;
    }

    public function get_constants() {
        return get_defined_constants(true);
    }

    public function get_classes() {
        foreach (get_declared_classes() as $Class) {
            $Classes[$Class]['Vars'] = get_class_vars($Class);
            $Classes[$Class]['Functions'] = get_class_methods($Class);
        }

        return $Classes;
    }

    public function get_extensions() {
        foreach (get_loaded_extensions() as $Extension) {
            $Extensions[$Extension]['Functions'] = get_extension_funcs($Extension);
        }

        return $Extensions;
    }

    public function get_includes() {
        return get_included_files();
    }

    /* Output Formatting */

    public function include_table($Includes = false) {
        if (!is_array($Includes)) {
            $Includes = $this->get_includes();
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_include').toggle();return false;">(View)</a> <?=number_format(count($Includes))?> Includes:</strong></td>
        </tr>
    </table>
    <table id="debug_include" class="debug_table hidden" width="100%">
        <?php
        foreach ($Includes as $File) {
            ?>
<tr valign="top">
    <td><?=$File?></td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function class_table($Classes = false) {
        if (!is_array($Classes)) {
            $Classes = $this->get_classes();
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
                <pre><?php print_r($Classes) ?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    public function extension_table() {
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_extensions').toggle();return false;">(View)</a> Extensions:</strong></td>
        </tr>
    </table>
    <table id="debug_extensions" class="debug_table hidden" width="100%">
        <tr>
            <td align="left">
                <pre><?php print_r($this->get_extensions()) ?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    // Borrowed from git source code
    protected function unpack_git_pack_header($buf, &$type, &$size) {
        $used = 0;

        $c = $buf[1+$used++];
        $type = ($c >> 4) & 7;
        $size = $c & 15;
        $shift = 4;
        while ($c & 0x80) {
            // Header can't be full object/buffer
            // Can't shift beyond the integer boundry
            if (64 <= $used || 32 <= $shift) {
                return false;
            }
            $c = $buf[1+$used++];
            $size += ($c & 0x7f) << $shift;
            $shift += 7;
        }
        return $used;
    }

    public function git_commit() {
        // Read the HEAD file, it should look like
        // ref: <reference path>
        $HEAD=@file_get_contents("../.git/HEAD");
        if ($HEAD === false) return;
        $HEAD = trim(explode(' ', $HEAD)[1]);

        // Fetch the commit hash from the reference and
        // split it into an object reference
        $REF=@file_get_contents("../.git/".$HEAD);

        // Shit, it's been packed!
        if ($REF === false) {
            $packedRefs=@file("../.git/packed-refs");
            foreach ($packedRefs as $ref) {
                $parts = explode(' ', $ref);
                if (trim($parts[1]) == $HEAD) {
                    $REF = $parts[0];
                    break;
                }
            }
        }

        $REF=trim($REF);
        // Check the cache first!
        $COMMIT = $this->cache->get_value("commit_{$REF}");

        // Shit, not cached, go fetch it from disk
        if ($COMMIT === false) {
            $REF_BASE=substr($REF, 0, 2);
            $REF_OBJ=substr($REF, 2);

            // Fetch and uncompress the commit object
            $RAW_COMMIT=@file_get_contents("../.git/objects/".$REF_BASE.'/'.$REF_OBJ);

            if ($RAW_COMMIT === false) {
                // Fuck, it must be a packed object. :-(
                // Seach the index files for the reference.
                foreach (glob($this->master->application_path."/../.git/objects/pack/*.idx") as $INDEX_FILE) {
                    $RAW_INDEX = file_get_contents($INDEX_FILE);
                    $INDEX = unpack('N*', substr($RAW_INDEX, 0, 1032));
                    // Magic shit at the start of the file.
                    if ($INDEX[1] == 4285812579 && $INDEX[2] == 2) {
                        // We got a good index file!
                        for ($index = 0; $index < $INDEX[258]; $index++) {
                            $COMMIT = substr($RAW_INDEX, 1032+(20*$index), 20);
                            $COMMIT = unpack('H40', $COMMIT)[1];
                            if ($COMMIT == $REF_BASE.$REF_OBJ) {
                                // Found it!
                                $OFFSET = 20*$INDEX[258] + 4*$INDEX[258] + 4*$index + 1032;
                                $OFFSET = unpack('N*', substr($RAW_INDEX, $OFFSET, 4))[1];
                                break 2;
                            }
                        }
                    }
                }

                // Extract the commit data.
                $RAW_PACK = file_get_contents(str_replace('.idx', '.pack', $INDEX_FILE));
                $HEAD = unpack('C*', substr($RAW_PACK, $OFFSET, 64));
                $type = 0;
                $size = 0;
                $dataStart = $this->unpack_git_pack_header($HEAD, $type, $size);

                // Sanity Check
                if ($type !== 1) return;
                $RAW_COMMIT = substr($RAW_PACK, $OFFSET+$dataStart, $size);
            }

            $RAW_COMMIT = @gzuncompress($RAW_COMMIT);
            if ($RAW_COMMIT === false) return;

            // Prepare the commit data so we can display it
            // in a pretty way
            $SPLIT_COMMIT=explode(PHP_EOL, $RAW_COMMIT);

            // Build a proper commit Array
            unset($COMMIT);
            $COMMIT['Commit']  = $REF;
            $COMMIT['Author']  = explode(' ', $SPLIT_COMMIT[2])[1]; //.' '.$SPLIT_COMMIT[3];
            $COMMIT['Date']    = \Date("Y-m-d H:i:s", explode(' ', $SPLIT_COMMIT[2])[3]);
            $COMMIT['Comment'] = trim($SPLIT_COMMIT[5]);
            $this->cache->cache_value("commit_{$REF}", $COMMIT, 0);
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_commit').toggle();return false;">(View)</a> Git commit:</strong></td>
  </tr>
    </table>
    <table id="debug_commit" class="debug_table hidden" width="100%">
        <?php
        foreach ($COMMIT as $key => $value) {
            ?>
    <tr><td></td><td><b><?=$key?>:</b></td><td><?=$value?></td></tr>
        <?php       } ?>
    </table>
        <?php
    }

    public function flag_table($Flags = false) {
        if (!is_array($Flags)) {
            $Flags = $this->Flags;
        }
        if (empty($Flags)) {
            return;
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_flags').toggle();return false;">(View)</a> Flags:</strong></td>
        </tr>
    </table>
    <table id="debug_flags" class="debug_table hidden" width="100%">
        <?php
        foreach ($Flags as $Flag) {
            list($Event,$MicroTime,$Memory) = $Flag;
            ?>
<tr valign="top">
    <td align="left"><?=$Event?></td>
    <td align="left"><?=$MicroTime?> ms</td>
    <td align="left"><?=get_size($Memory)?></td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function permission_table($Permissions = false) {
        if (!is_array($Permissions)) {
            $Permissions = $this->auth->usedPermissions;
        }
        if (empty($Permissions)) {
            return;
        }
        ?>
        <table class="debug_table_head" width="100%">
            <tr>
                <td align="left"><strong><a href="#" onclick="$('#debug_perms').toggle();return false;">(View)</a> Permissions:</strong></td>
            </tr>
        </table>
        <table id="debug_perms" class="debug_table hidden" width="100%">
        <?php
        foreach ($Permissions as $Permission => $Uses) {
            ?>
<tr valign="top">
    <td align="left"><?=$Permission?></td>
    <td align="left">checked <?=$Uses?> times</td>
</tr>
            <?php
        }
        ?>
        </table>
        <?php
    }

    public function constant_table($Constants = false) {
        if (!is_array($Constants)) {
            $Constants = $this->get_constants();
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
                <pre><?=display_str(print_r($Constants, true))?></pre>
            </td>
        </tr>
    </table>
        <?php
    }

    public function cache_table($CacheKeys = false) {
        $Header = 'Cache Keys';
        if (!is_array($CacheKeys)) {
            $CacheKeys  = array_keys($this->cache->CacheHits);
            $CacheTimes = $this->cache->CacheTimes;
            $Header .= ' ('.number_format($this->cache->Time, 5).' ms)';
        }
        if (empty($CacheKeys)) {
            return;
        }
        $Header = ' '.number_format(count($CacheKeys)).' '.$Header.':';
        $AuthKey = isset($this->request->user->legacy['AuthKey']) ? $this->request->user->legacy['AuthKey'] : null;
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_cache').toggle();return false;">(View)</a><?=$Header?></strong></td>
        </tr>
    </table>
    <table id="debug_cache" class="debug_table hidden" width="100%">
        <?php	foreach ($CacheKeys as $Key) { ?>
        <tr>
            <td align="left">
                <a href="#" onclick="$('#debug_cache_<?=$Key?>').toggle(); return false;"><?=display_str($Key)?></a>
            </td>
            <td align="left" class="debug_data debug_cache_data">
                <pre id="debug_cache_<?=$Key?>" class="hidden"><?=display_str(print_r($this->cache->get_value($Key, true), true))?></pre>
            </td>
            <td class="rowa" style="width:130px;" align="left"><?=number_format($CacheTimes[$Key], 5)?> ms</td>
            <td class="rowa" style="width:50px;" align="left">[<a href="/tools.php?action=clear_cache&amp;key=<?=$Key?>&amp;type=clear&amp;auth=<?=$AuthKey?>" title="clear <?=$Key?>">clear</a>]</td>
        </tr>
        <?php	} ?>
    </table>
        <?php
    }

    public function error_table($Errors = false) {
        if (!is_array($Errors)) {
            $Errors = $this->get_errors();
        }
        if (empty($Errors)) {
            return;
        }
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_error').toggle();return false;">(View)</a> <?=number_format(count($Errors))?> Errors:</strong></td>
        </tr>
    </table>
    <table id="debug_error" class="debug_table hidden" width="100%">
        <?php
        foreach ($Errors as $Error) {
            list($Error,$Location,$Call,$Args) = $Error;
            ?>
<tr valign="top">
    <td align="left"><?=display_str($Call)?>(<?=display_str($Args)?>)</td>
    <td class="debug_data debug_error_data" align="left"><?=display_str($Error)?></td>
    <td align="left"><?=display_str($Location)?></td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function query_table($Queries = false) {
        $Header = 'Queries';
        if (!is_array($Queries)) {
            $Queries = $this->db->Queries;
            $Header .= ' ('.number_format($this->db->Time, 5).' ms)';
        }
        if (empty($Queries)) {
            return;
        }
        $Header = ' '.number_format(count($Queries)).' '.$Header.':';
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
                <td align="left"><strong><a href="#" onclick="$('#debug_database').toggle();return false;">(View)</a><?=$Header?></strong></td>
        </tr>
    </table>
    <table id="debug_database" class="debug_table hidden" width="100%">
        <?php
        foreach ($Queries as $Query) {
            list($SQL,$Time) = $Query;
            ?>
<tr valign="top">
    <td class="debug_data debug_query_data"><div><?=str_replace("\t", '&nbsp;&nbsp;', nl2br(display_str($SQL)))?></div></td>
    <td class="rowa" style="width:130px;" align="left"><?=number_format($Time, 5)?> ms</td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function sphinx_table($Queries = false) {
        $Header = 'Searches';
        if (!is_array($Queries)) {
            $Queries = $this->search->Queries;
            $Header .= ' ('.number_format($this->search->Time, 5).' ms)';
        }
        if (empty($Queries)) {
            return;
        }
        $Header = ' '.number_format(count($Queries)).' '.$Header.':';
        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_sphinx').toggle();return false;">(View)</a><?=$Header?></strong></td>
        </tr>
    </table>
    <table id="debug_sphinx" class="debug_table hidden" width="100%">
        <?php
        foreach ($Queries as $Query) {
            list($Params,$Time) = $Query;
            ?>
<tr valign="top">
    <td class="debug_data debug_sphinx_data"><pre><?=str_replace("\t", '	', display_str($Params))?></pre></td>
    <td class="rowa" style="width:130px;" align="left"><?=number_format($Time, 5)?> ms</td>
</tr>
            <?php
        }
        ?>
    </table>
        <?php
    }

    public function vars_table($Vars = false) {
        $Header = 'Logged Variables';
        if (empty($Vars)) {
            if (empty($this->LoggedVars)) {
                return;
            }
            $Vars = $this->LoggedVars;
        }
        $Header = ' '.number_format(count($Vars)).' '.$Header.':';

        ?>
    <table class="debug_table_head" width="100%">
        <tr>
            <td align="left"><strong><a href="#" onclick="$('#debug_loggedvars').toggle();return false;">(View)</a><?=$Header?></strong></td>
        </tr>
    </table>
    <table id="debug_loggedvars" class="debug_table hidden" width="100%">
        <?php
        foreach ($Vars as $ID => $Var) {
            list($Key, $Data) = each($Var);
            $Size = count($Data['data']);
            ?>
<tr>
    <td align="left">
        <a href="#" onclick="$('#debug_loggedvars_<?=$ID?>').toggle(); return false;"><?=display_str($Key)?></a> (<?=$Size . ($Size == 1 ? ' element' : ' elements')?>)
        <div><?=$Data['bt']['path'].':'.$Data['bt']['line'];?></div>
    </td>
    <td class="debug_data debug_loggedvars_data" align="left">
        <pre id="debug_loggedvars_<?=$ID?>" class="hidden"><?=display_str(print_r($Data['data'], true));?></pre>
    </td>
</tr>
        <?php	} ?>
    </table>
        <?php
    }
}
