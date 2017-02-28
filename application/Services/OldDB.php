<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Errors\SystemError;

class OldDB extends Service {

    public $LinkID = false;
    protected $QueryID = false;
    protected $Record = array();
    protected $Row;
    protected $Errno = 0;
    protected $Error = '';

    public $Queries = array();
    public $Time = 0.0;

    protected static $useServices = [
        'newdb' => 'DB',
    ];

    public function connect() {
        $this->newdb->connect();
        $this->LinkID = true;
    }

    public function query($Query,$AutoHandle=1)
    {
        global $Debug;
        $QueryStartTime=microtime(true);
        $this->connect();
        //In the event of a mysql deadlock, we sleep allowing mysql time to unlock then attempt again for a maximum of 5 tries
        for ($i=1; $i<6; $i++) {
            try {
                $this->QueryID = $this->newdb->legacy_query($Query);
            } catch (\PDOException $e) {
                if (!in_array($e->errorInfo[1], array(1213, 1205))) {
                    throw $e;
                }
                $Debug->analysis('Non-Fatal Deadlock:',$Query,3600*24);
                trigger_error("Database deadlock, attempt $i");
                sleep($i*rand(2, 5)); // Wait longer as attempts increase
                continue;
            }
            break;
        }
        $QueryEndTime=microtime(true);
        $this->Queries[]=array(db_display_str($Query),($QueryEndTime-$QueryStartTime)*1000);
        $this->Time+=($QueryEndTime-$QueryStartTime)*1000;

        $QueryType = substr($Query,0, 6);
        $this->Row = 0;
        if ($AutoHandle) { return $this->QueryID; }
    }

    public function query_unb($Query)
    {
        error_log("OldDB::query_unb() no longer works, sorry");
        exit;
        $this->connect();
        mysqli_real_query($this->LinkID,$Query);
    }

    public function inserted_id()
    {
        if ($this->LinkID) {
            return $this->newdb->pdo->lastInsertId();
        }
    }

    public function next_record($Type=MYSQLI_BOTH, $Escape = true) { // $Escape can be true, false, or an array of keys to not escape
        if ($this->LinkID) {
            $this->Record = $this->QueryID->fetch($Type);
            $this->Row++;
            if (!is_array($this->Record)) {
                $this->QueryID = FALSE;
            } elseif ($Escape !== FALSE) {
                $this->Record = db_display_array($this->Record, $Escape);
            }

            return $this->Record;
        }
    }

    public function close()
    {
        if ($this->LinkID) {
            $this->LinkID = FALSE;
        }
    }

    public function record_count()
    {
        if ($this->QueryID) {
            return $this->QueryID->record_count();
        }
    }

    public function affected_rows()
    {
        if ($this->QueryID) {
            return $this->QueryID->stmt->rowCount();
        }
    }

    public function info()
    {
        return mysqli_get_host_info($this->LinkID);
    }

    // You should use db_string() instead.
    public function escape_str($Str)
    {
        $this->connect();
        if (is_array($Str)) {
            trigger_error('Attempted to escape array.');

            return '';
        }
        $escaped = $this->newdb->pdo->quote($Str);
        return substr($escaped, 1, -1);
    }

    // Creates an array from a result set
    // If $Key is set, use the $Key column in the result set as the array key
    // Otherwise, use an integer
    public function to_array($Key = false, $Type = MYSQLI_BOTH, $Escape = true, $KeepKeys = true)
    {
        $Return = array();
        while ($Row = $this->QueryID->fetch($Type)) {
            if ($Escape!==FALSE) {
                $Row = db_display_array($Row, $Escape);
            }
            if ($KeepKeys == FALSE) {
                $Row = array_values($Row);
            }
            if ($Key !== false) {
                $Return[$Row[$Key]] = $Row;
            } else {
                $Return[]=$Row;
            }
        }
        $this->QueryID->rewind();

        return $Return;
    }

    //  Loops through the result set, collecting the $Key column into an array
    public function collect($Key, $Escape = true)
    {
        $Return = array();
        while ($Row = $this->QueryID->fetch()) {
            $Return[] = $Escape ? db_display_str($Row[$Key]) : $Row[$Key];
        }
        $this->QueryID->rewind();

        return $Return;
    }

    public function set_query_id(&$ResultSet)
    {
        $this->QueryID = $ResultSet;
        $this->Row = 0;
    }

    public function beginning()
    {
        $this->QueryID->rewind();
    }

}
