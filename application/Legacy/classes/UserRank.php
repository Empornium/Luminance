<?php
namespace Luminance\Legacy;
define('PREFIX', 'percentiles_'); // Prefix for memcache keys, to make life easier

class UserRank
{
    // Returns a 101 row array (101 percentiles - 0 - 100), with the minimum value for that percentile as the value for each row
    // BTW - ingenious
    public function build_table($MemKey, $Query)
    {
        global $master;

        $master->db->raw_query("DROP TEMPORARY TABLE IF EXISTS temp_stats");

        $master->db->raw_query("CREATE TEMPORARY TABLE temp_stats
            (ID int(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            Val bigint(20) NOT NULL);");

        $master->db->raw_query("INSERT INTO temp_stats (Val) ".$Query);

        $UserCount = $master->db->raw_query("SELECT COUNT(ID) FROM temp_stats")->fetchColumn();

        $Table = $master->db->raw_query("SELECT MIN(Val) FROM temp_stats GROUP BY CEIL(ID/(".(int) $UserCount."/100));")->fetchAll();

        // Give a little variation to the cache length, so all the tables don't expire at the same time
        $master->cache->cache_value($MemKey, $Table, 3600*24*rand(800,1000)*0.001);

        return $Table;
    }

    public function table_query($TableName)
    {
        switch ($TableName) {
            case 'uploaded':
                $Query =  "SELECT Uploaded FROM users_main WHERE Enabled='1' AND Uploaded>0 ORDER BY Uploaded;";
                break;
            case 'downloaded':
                $Query =  "SELECT Downloaded FROM users_main WHERE Enabled='1' AND Downloaded>0 ORDER BY Downloaded;";
                break;
            case 'uploads':
                $Query = "SELECT COUNT(t.ID) AS Uploads FROM users_main AS um JOIN torrents AS t ON t.UserID=um.ID WHERE um.Enabled='1' GROUP BY um.ID ORDER BY Uploads;";
                break;
            case 'requests':
                $Query = "SELECT COUNT(r.ID) AS Requests FROM users_main AS um JOIN requests AS r ON r.FillerID=um.ID WHERE um.Enabled='1' GROUP BY um.ID ORDER BY Requests;";
                break;
            case 'posts':
                $Query = "SELECT COUNT(p.ID) AS Posts FROM users_main AS um JOIN forums_posts AS p ON p.AuthorID=um.ID WHERE um.Enabled='1' GROUP BY um.ID ORDER BY Posts;";
                break;
            case 'bounty':
                //Request bunny exception
                $Query = "SELECT SUM(rv.Bounty) AS Bounty FROM users_main AS um JOIN requests_votes AS rv ON rv.UserID=um.ID WHERE um.Enabled='1' AND um.ID <> 260542 GROUP BY um.ID ORDER BY Bounty;";
                break;
        }

        return $Query;
    }

    public function get_rank($TableName, $Value)
    {
        if ($Value == 0) { return 0; }
        global $master;

        $Table = $master->cache->get_value(PREFIX.$TableName);
        if (!$Table) {
            //Cache lock!
            $Lock = $master->cache->get_value(PREFIX.$TableName."_lock");
            if ($Lock) {
                ?><script type="script/javascript">setTimeout('window.location="//<?=$master->settings->site_url?><?=$_SERVER['REQUEST_URI']?>"', 5)</script><?php
            } else {
                $master->cache->cache_value(PREFIX.$TableName."_lock", '1', 10);
                $Table = $this->build_table(PREFIX.$TableName, $this->table_query($TableName));
            }
        }
        $LastPercentile = 0;
        foreach ($Table as $Row) {
            list($CurValue) = $Row;
            if ($CurValue>=$Value) {
                return $LastPercentile;
            }
            $LastPercentile++;
        }

        return 100; // 100th percentile
    }

    public function overall_score($Rank, $Ratio)
    {
        // We can do this all in 1 line, but it's easier to read this way
        if ($Ratio>1) { $Ratio = 1; }
        $TotalScore = 0;
        $TotalScore += $Rank->uploaded*15;
        $TotalScore += $Rank->downloaded*8;
        $TotalScore += $Rank->uploads*25;
        $TotalScore += $Rank->requests*2;
        $TotalScore += $Rank->posts;
        $TotalScore += $Rank->bounty;
        $TotalScore /= (15+8+25+2+1+1);
        $TotalScore *= $Ratio;

        return $TotalScore;

    }

}
