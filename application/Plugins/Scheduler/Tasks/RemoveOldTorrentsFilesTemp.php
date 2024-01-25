<?php
namespace Luminance\Plugins\Scheduler\Tasks;

use Luminance\Services\Scheduler\Task;

class RemoveOldTorrentsFilesTemp extends Task {
    private $db;
    private $maxTime;

    public function initialize() {
        $this->db = $this->master->db;
        $this->maxTime = time_minus(86400); // 3600 * 24
    }

    public function describe() {
        return 'Removing old torrents_files_temp...';
    }

    public function run() {
        $this->db->rawQuery("DELETE FROM torrents_files_temp WHERE time < ?", [$this->maxTime]);
    }
}
