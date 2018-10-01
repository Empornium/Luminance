<?php


/**
 * The Scheduler Watch handles the schedule's planning,
 * i.e. which hour, quarter, week it is.
 */
class SchedulerWatch
{
    private $db;

    public $minute;
    public $hour;
    public $day;
    public $biweek;
    public $quarter;

    public $next_hour;
    public $next_day;
    public $next_biweek;

    public function __construct(\Luminance\Services\DB $db)
    {
        $this->db = $db;

        $this->get_schedule();
        $this->update_schedule();
    }

    private function get_schedule()
    {
        $query = $this->db->raw_query('SELECT NextHour, NextDay, NextBiWeekly FROM schedule');
        $results = $query->fetch(PDO::FETCH_OBJ);

        $this->minute = $this->next_min();
        $this->hour   = $results->NextHour;
        $this->day    = $results->NextDay;
        $this->biweek = $results->NextBiWeekly;

        //$this->quarter = floor($this->minute / 15);
        $this->quarter = (int) floor($this->minute / 15) * 15;
    }

    private function update_schedule()
    {
        $this->next_hour   = $this->next_hour();
        $this->next_day    = $this->next_day();
        $this->next_biweek = $this->next_biweek();

        $this->db->raw_query("UPDATE schedule SET NextHour = {$this->next_hour}, NextDay = {$this->next_day}, NextBiWeekly = {$this->next_biweek}");
    }

    private function next_min()
    {
        return date('i');
    }

    private function next_hour()
    {
        return date('H');
    }

    private function next_day()
    {
        return date('d');
    }

    private function next_biweek()
    {
        $Date = date('d');
        return ($Date < 22 && $Date >=8) ? 22 : 8;
    }
}