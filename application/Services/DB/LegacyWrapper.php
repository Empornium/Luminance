<?php
namespace Luminance\Services\DB;

class LegacyWrapper {

    public $stmt;
    public $cached_results = [];
    public $cached_done = false;
    public $cached_index = 0;

    public function __construct(\PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function fetch($type = null) {
        if (is_null($type)) {
            $type = MYSQLI_BOTH;
        }
        while (!array_key_exists($this->cached_index, $this->cached_results)) {
            if ($this->cached_done) {
                return false;
            } else {
                $row = $this->stmt->fetch(\PDO::FETCH_BOTH);
                if ($row) {
                    $this->cached_results[] = $row;
                } else {
                    $this->cached_done = true;
                    return false;
                }
            }

        }
        $row = $this->cached_results[$this->cached_index];
        $this->cached_index++;
        $result = $this->filter_row($row, $type);
        return $result;
    }

    protected function fill() {
        if (count($this->cached_results) == 0) {
            $this->cached_results = $this->stmt->fetchAll(\PDO::FETCH_BOTH);
            $this->cached_done = true;
        } else {
            while (!$this->cached_done) {
                $row = $this->stmt->fetch(\PDO::FETCH_BOTH);
                if ($row) {
                    $this->cached_results[] = $row;
                } else {
                    $this->cached_done = true;
                }
            }
        }
        $this->cached_index = count($this->cached_results);
    }

    public function fetchAll($type = null) {
        if (is_null($type)) {
            $type = MYSQLI_BOTH;
        }
        if (!$this->cached_done) {
            $this->fill();
        }
        $results = $this->filter_rows($this->cached_results, $type);
        return $results;
    }

    public function record_count() {
        if (!$this->cached_done) {
            $this->fill();
        }
        $count = count($this->cached_results);
        $this->rewind();
        return $count;
    }

    public function rewind() {
        $this->cached_index = 0;
    }

    protected function filter_rows($rows, $type) {
        $filtered_rows = [];
        foreach ($rows as $row) {
            $filtered_rows[] = $this->filter_row($row, $type);
        }
        return $filtered_rows;
    }

    protected function filter_row($row, $type) {
        switch ($type) {
            case MYSQLI_BOTH:
                return $row;
            case MYSQLI_ASSOC:
            case MYSQL_ASSOC:
                $filtered_row = [];
                foreach ($row as $key => $value) {
                    if (!is_int($key)) {
                        $filtered_row[$key] = $value;
                    }
                }
                return $filtered_row;
            case MYSQLI_NUM:
            case MYSQL_NUM:
                $filtered_row = [];
                foreach ($row as $key => $value) {
                    if (is_int($key)) {
                        $filtered_row[$key] = $value;
                    }
                }
                return $filtered_row;
            default:
                error_log("Unable to filter row for type {$type}");
        }
    }

}
