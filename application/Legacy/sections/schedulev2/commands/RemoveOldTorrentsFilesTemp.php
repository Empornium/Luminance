<?php

class RemoveOldTorrentsFilesTemp extends ScheduledCommand
{
    private $db;
    private $max_time;

    public function initialize()
    {
        $this->db = $this->master->db;
        $this->max_time = time_minus(86400); // 3600 * 24
    }

    public function describe()
    {
        return 'Removing old torrents_files_temp...';
    }

    public function run()
    {
        $this->db->raw_query("DELETE FROM torrents_files_temp WHERE time < '{$this->max_time}'");
    }
}