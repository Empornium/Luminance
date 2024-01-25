<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\SystemError;

use Luminance\Services\Debug;

function dbIsIntegerString($str) {
    $return = true;
    if ($str < 0) {
        $return = false;
    }
    # We're converting input to a int, then string and comparing to original
    $return = ($str === strval(intval($str)) ? true : false);

    return $return;
}

function dbIsUTF8($str) {
    return preg_match('%^(?:
        [\x09\x0A\x0D\x20-\x7E]			        # ASCII
        | [\xC2-\xDF][\x80-\xBF]			      # non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]		    # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]		    # excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}	    # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}		      # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}	    # plane 16
        )*$%xs', $str);
}

function dbMakeUTF8($str) {
    if (!($str === '')) {
        if (dbIsUTF8($str)) {
            $encoding = 'UTF-8';
        }
        if (empty($encoding)) {
            $encoding = mb_detect_encoding($str, 'UTF-8, ISO-8859-1');
        }
        if (empty($encoding)) {
            $encoding = 'ISO-8859-1';
        }
        if ($encoding === 'UTF-8') {
            return $str;
        } else {
            return @mb_convert_encoding($str, 'UTF-8', $encoding);
        }
    }
}

function dbDisplayStr($str) {
    if ($str === null || $str === false || is_array($str)) {
        return '';
    }
    if (!($str === '') && !dbIsIntegerString($str)) {
        $str = dbMakeUTF8($str);
        $str = htmlspecialchars_decode(htmlentities($str));
        $str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,6};)/m", '&amp;', $str);

        $replace = [
            "'", '"', "<", ">",
            '&#128;', '&#130;', '&#131;', '&#132;', '&#133;', '&#134;', '&#135;',
            '&#136;', '&#137;', '&#138;', '&#139;', '&#140;', '&#142;', '&#145;',
            '&#146;', '&#147;', '&#148;', '&#149;', '&#150;', '&#151;', '&#152;',
            '&#153;', '&#154;', '&#155;', '&#156;', '&#158;', '&#159;'
        ];

        $with = [
            '&#39;', '&quot;', '&lt;', '&gt;',
            '&#8364;', '&#8218;', '&#402;', '&#8222;', '&#8230;', '&#8224;', '&#8225;',
            '&#710;', '&#8240;', '&#352;', '&#8249;', '&#338;', '&#381;', '&#8216;',
            '&#8217;', '&#8220;', '&#8221;', '&#8226;', '&#8211;', '&#8212;', '&#732;',
            '&#8482;', '&#353;', '&#8250;', '&#339;', '&#382;', '&#376;'
        ];

        $str = str_replace($replace, $with, $str);
    }

    return $str;
}

function dbDisplayArray($array, $escape = []) {
    foreach ($array as $key => $val) {
        if ((!is_array($escape) && $escape === true) || !in_array($key, $escape)) {
            $array[$key] = dbDisplayStr($val);
        }
    }

    return $array;
}

class DB extends Service {

    public $pdo;
    public $queries = [];
    public $time = 0.0;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->connect();
    }

    public function connect() {
        if (!($this->pdo instanceof \PDO)) {
            # Test for presence of PDO driver
            if (!(extension_loaded('mysqlnd') || extension_loaded('mysql'))) {
                throw new SystemError(null, 'MySQL/MariaDB driver not found, please install php-mysqlnd');
                die();
            }

            $dbc = $this->master->settings->database;
            $options = [
                \PDO::ATTR_PERSISTENT => $dbc->persistent_connections,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ];

            if ($dbc->strict_mode) {
                $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET sql_mode = 'NO_ENGINE_SUBSTITUTION,STRICT_TRANS_TABLES'";
            } else {
                $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET sql_mode = ''";
            }

            if (defined('\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE')) {
                $options[\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE] = $dbc->buffer_size;
                $options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            }

            # TODO: specify port & socket in case they differ from default
            $this->pdo = new \PDO("mysql:host={$dbc->host};dbname={$dbc->db};port={$dbc->port}", $dbc->username, $dbc->password, $options);
        }
    }

    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     * @return string The interpolated query
     */
    public static function interpolateQuery($query, $params) {
        $keys = [];

        # Ensure $params is an array using a blind cast
        $params = (array) $params;

        # build a regular expression for each parameter
        foreach ($params as $key => &$value) {
            if (is_null($value)) {
                $value = 'NULL';
            } else {
                $value = (string) $value;
            }
            if (is_string($key)) {
                $keys[] = (string)'/'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }
        }
        $query = preg_replace($keys, $params, $query, 1, $count);
        return $query;
    }

    public function rawQuery($sql, $parameters = []) {
        $queryStartTime=microtime(true);
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->execute($stmt, $parameters, 6);
        } catch (\PDOException $e) {
            $message = "Failed query ({$e->getMessage()}): " . PHP_EOL .
            self::interpolateQuery($sql, $parameters) . PHP_EOL;
            throw new SystemError($message);
        }
        $queryEndTime=microtime(true);
        if (Debug::getEnabled()) {
            if (count($this->queries) < 200) {
                $this->queries[] = [
                    'query'     => dbDisplayStr(self::interpolateQuery($sql, $parameters)),
                    'microtime' => ($queryEndTime-$queryStartTime)*1000,
                ];
            }
            $this->time+=($queryEndTime-$queryStartTime)*1000;
        }
        return $stmt;
    }

    public function execute(&$stmt, $parameters, int $retries = 0) {
        for ($i=0; $i<=$retries; $i++) {
            try {
                $stmt->execute($parameters);
            } catch (\PDOException $e) {
                if (!in_array($e->errorInfo[1], [1213, 1205])) {
                    throw $e;
                }
                trigger_error("Database deadlock, attempt $i");
                sleep($i*rand(2, 5)); # Wait longer as attempts increase
                continue;
            }
            break;
        }
    }

    public function lastInsertID() {
        return $this->pdo->lastInsertId();
    }

    public function foundRows() {
        $count = $this->pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
        return $count;
    }

    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    public function exec($query) {
        return $this->pdo->exec($query);
    }

    # a helper function to build a param array and param String for use in an IN ( ) clause
    public function bindParamArray($prefix, $values, &$params) {
        $str = "";
        foreach ($values as $index => $value) {
            # build named param string in form ':id0,:id1',
            $str .= ":".$prefix.$index.",";
            # $params are returned in form [':id0'=>$val[0], ':id1'=>$val[1] ]
            $params[$prefix.$index] = $value;
        }
        return rtrim($str, ",");
    }
}
