<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Entity;
use Luminance\Errors\SystemError;
use Luminance\Services\DB\LegacyWrapper;
function db_is_number($Str)
{
    $Return = true;
    if ($Str < 0) {
        $Return = false;
    }
    // We're converting input to a int, then string and comparing to original
    $Return = ($Str == strval(intval($Str)) ? true : false);

    return $Return;
}

function db_is_utf8($Str)
{
    return preg_match('%^(?:
        [\x09\x0A\x0D\x20-\x7E]			 // ASCII
        | [\xC2-\xDF][\x80-\xBF]			// non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]		// excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} // straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]		// excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}	 // planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}		 // planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}	 // plane 16
        )*$%xs', $Str
    );
}

function db_make_utf8($Str)
{
    if ($Str != "") {
        if (db_is_utf8($Str)) {
            $Encoding = "UTF-8";
        }
        if (empty($Encoding)) {
            $Encoding = mb_detect_encoding($Str, 'UTF-8, ISO-8859-1');
        }
        if (empty($Encoding)) {
            $Encoding = "ISO-8859-1";
        }
        if ($Encoding == "UTF-8") {
            return $Str;
        } else {
            return @mb_convert_encoding($Str, "UTF-8", $Encoding);
        }
    }
}

function db_display_str($Str)
{
    if ($Str === NULL || $Str === FALSE || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !db_is_number($Str)) {
        $Str = db_make_utf8($Str);
        $Str = mb_convert_encoding($Str, "HTML-ENTITIES", "UTF-8");
        $Str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,5};)/m", "&amp;", $Str);

        $Replace = array(
            "'", '"', "<", ">",
            '&#128;', '&#130;', '&#131;', '&#132;', '&#133;', '&#134;', '&#135;', '&#136;', '&#137;', '&#138;', '&#139;', '&#140;', '&#142;', '&#145;', '&#146;', '&#147;', '&#148;', '&#149;', '&#150;', '&#151;', '&#152;', '&#153;', '&#154;', '&#155;', '&#156;', '&#158;', '&#159;'
        );

        $With = array(
            '&#39;', '&quot;', '&lt;', '&gt;',
            '&#8364;', '&#8218;', '&#402;', '&#8222;', '&#8230;', '&#8224;', '&#8225;', '&#710;', '&#8240;', '&#352;', '&#8249;', '&#338;', '&#381;', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8226;', '&#8211;', '&#8212;', '&#732;', '&#8482;', '&#353;', '&#8250;', '&#339;', '&#382;', '&#376;'
        );

        $Str = str_replace($Replace, $With, $Str);
    }

    return $Str;
}

function db_display_array($Array, $Escape = array())
{
    foreach ($Array as $Key => $Val) {
        if ((!is_array($Escape) && $Escape == true) || !in_array($Key, $Escape)) {
            $Array[$Key] = db_display_str($Val);
        }
    }

    return $Array;
}

class DB extends Service {

    public $pdo;
    public $Queries = array();

    public function connect() {
        if (is_null($this->pdo)) {
            $dbc = $this->master->settings->database;
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false
            ];

            if (defined('\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE')) {
                $options[\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE] = 16777216;
            }


            # TODO: specify port & socket in case they differ from default
            $this->pdo = new \PDO("mysql:host={$dbc->host};dbname={$dbc->db}", $dbc->username, $dbc->password, $options);
        }
    }

    public function raw_query($sql, $parameters = array()) {
        $QueryStartTime=microtime(true);
        $this->connect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        $QueryEndTime=microtime(true);
        $this->Queries[]=array(db_display_str($sql."\n".json_encode($parameters)),($QueryEndTime-$QueryStartTime)*1000);
        return $stmt;
    }

    public function legacy_query($sql) {
        $QueryStartTime=microtime(true);
        $this->connect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $QueryEndTime=microtime(true);
        $this->Queries[]=array(db_display_str($sql),($QueryEndTime-$QueryStartTime)*1000);
        $wrapper = new LegacyWrapper($stmt);
        return $wrapper;
    }

    public function last_insert_id() {
        return $this->pdo->lastInsertId();
    }

    public function found_rows() {
        $count = $this->pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
        return $count;
    }

}
