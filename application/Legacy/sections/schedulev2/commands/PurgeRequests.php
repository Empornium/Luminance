<?php


class PurgeRequests extends ScheduledCommand
{
    private $olddb;
    private $cache;
    private $max_time_added;
    private $sqltime;

    /**
     * Initialize the command's dependencies
     */
    public function initialize()
    {
        $this->olddb = $this->master->olddb;
        $this->cache = $this->master->cache;
        $this->max_time_added = time_minus(7862400); // 3600 * 24 * 91
        $this->sqltime = sqltime();
    }

    /**
     * Command's description
     */
    public function describe()
    {
        return 'Returns bounties and removes expired requests';
    }

    /**
     * Start the process
     */
    public function run()
    {
        // Return bounties for each voter
        $this->logger->log('Find bounties to return...');

        $this->olddb->query("SELECT r.ID, r.Title, v.UserID, v.Bounty
                  FROM requests as r JOIN requests_votes as v ON v.RequestID=r.ID
                 WHERE TorrentID='0' AND TimeAdded < '{$this->max_time_added}'");

        $RemoveBounties = $this->olddb->to_array();
        $RemoveRequestIDs = array();

        $this->logger->log('Do the actual bounty returning per user...');

        foreach ($RemoveBounties as $BountyInfo) {
            list($RequestID, $Title, $UserID, $Bounty) = $BountyInfo;
            // collect unique request ID's the old fashioned way
            if (!in_array($RequestID, $RemoveRequestIDs)) $RemoveRequestIDs[] = $RequestID;
            // return bounty and log in staff notes
            $Title = db_string($Title);
            $this->olddb->query("UPDATE users_info AS ui JOIN users_main AS um ON um.ID = ui.UserID
                       SET um.Uploaded=(um.Uploaded+'$Bounty'),
                           ui.AdminComment = CONCAT('".$this->sqltime." - Bounty of " . get_size($Bounty). " returned from expired Request $RequestID ($Title).\n', ui.AdminComment)
                     WHERE ui.UserID = '$UserID'");

            // Send users who got bounty returned a PM
            send_pm($UserID, 0, 'Bounty returned from expired request', "Your bounty of " . get_size($Bounty). " has been returned from the expired Request $RequestID ($Title).");
        }

        $this->logger->log('Remove requests...');

        if (count($RemoveRequestIDs) > 0) {
            // log and update sphinx for each request
            $this->olddb->query("SELECT r.ID, r.Title, Count(v.UserID) AS NumUsers, SUM( v.Bounty) AS Bounty, r.GroupID, r.Description, r.UserID
                      FROM requests as r JOIN requests_votes as v ON v.RequestID=r.ID
                     WHERE r.ID IN(".implode(",", $RemoveRequestIDs).")
                     GROUP BY r.ID" );

            $RemoveRequests = $this->olddb->to_array();

            // delete the requests
            $this->olddb->query("DELETE r, v, t, c
                      FROM requests as r
                 LEFT JOIN requests_votes as v ON r.ID=v.RequestID
                 LEFT JOIN requests_tags AS t ON r.ID=t.RequestID
                 LEFT JOIN requests_comments AS c ON r.ID=c.RequestID
                     WHERE r.ID IN(".implode(",", $RemoveRequestIDs).")");

            //log and update sphinx (sphinx call must be done after requests are deleted)
            foreach ($RemoveRequests as $Request) {
                //list($RequestID, $Title, $NumUsers, $Bounty, $GroupID) = $Request;

                send_pm($Request['UserID'], 0, "Your request has expired", db_string("Your request (".$Request['Title'].") has now expired.\n\nPlease feel free to start a new request with the same [spoiler=details][code]".$Request['Description']."[/code][/spoiler]\n\nThanks, Staff."));

                write_log("Request ".$Request['ID']." (".$Request['Title'].") expired - returned total of ". get_size($Request['Bounty'])." to ".$Request['NumUsers']." users");

                $this->cache->delete_value('request_votes_'.$Request['ID']);
                if ($Request['GroupID']) {
                    $this->cache->delete_value('requests_group_'.$Request['GroupID']);
                }
                update_sphinx_requests($Request['ID']);
            }
        }
    }
}