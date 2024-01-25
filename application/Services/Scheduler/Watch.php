<?php
namespace Luminance\Services\Scheduler;

use Luminance\Services\DB;

/**
 * The Scheduler Watch handles the schedule's planning,
 * i.e. which hour, quarter, week it is.
 */
class Watch {
    private $db;

    public $minute;
    public $hour;
    public $day;
    public $biweekly;
    public $quarter;

    public $nextHour;
    public $nextDay;
    public $nextBiweekly;

    public function __construct(DB $db) {
        $this->db = $db;

        $this->getSchedule();
        $this->updateSchedule();
    }

    private function getSchedule() {
        $query = $this->db->rawQuery('SELECT NextHour, NextDay, nextBiweeklyly FROM schedule');
        $results = $query->fetch(\PDO::FETCH_OBJ);

        $this->minute   = $this->nextMin();
        $this->hour     = $results->NextHour;
        $this->day      = $results->NextDay;
        $this->biweekly = $results->nextBiweeklyly;
        $this->quarter  = (int) floor($this->minute / 15) * 15;
    }

    private function updateSchedule() {
        $this->nextHour     = $this->nextHour();
        $this->nextDay      = $this->nextDay();
        $this->nextBiweekly = $this->nextBiweekly();

        $this->db->rawQuery(
            "UPDATE schedule
                SET NextHour = ?,
                    NextDay = ?,
                    nextBiweeklyly = ?",
            [
                $this->nextHour,
                $this->nextDay,
                $this->nextBiweekly,
            ]
        );
    }

    private function nextMin() {
        return date('i');
    }

    private function nextHour() {
        return date('H');
    }

    private function nextDay() {
        return date('d');
    }

    private function nextBiweekly() {
        $date = date('d');
        return ($date < 22 && $date >=8) ? 22 : 8;
    }
}
