<?php
namespace Luminance\Plugins\Scheduler\Tasks;

use Luminance\Services\Scheduler\Task;

class PurgeRequests extends Task {
    private $maxTimeAdded;
    private $sqltime;

    /**
     * Initialize the task's dependencies
     */
    public function initialize() {
        $this->maxTimeAdded = time_minus(7862400); // 3600 * 24 * 91
        $this->sqltime = sqltime();
    }

    /**
     * Command's description
     */
    public function describe() {
        return 'Returns bounties and removes expired requests';
    }

    /**
     * Start the process
     */
    public function run() {
        // Return bounties for each voter
        $this->logger->log('Find bounties to return...');

        $removeBounties = $this->master->db->rawQuery(
            "SELECT r.ID,
                    r.Title,
                    v.UserID,
                    v.Bounty
               FROM requests as r
               JOIN requests_votes as v ON v.RequestID=r.ID
              WHERE TorrentID='0'
                AND TimeAdded < ?",
            [$this->maxTimeAdded]
        )->fetchAll(\PDO::FETCH_NUM);

        $removeRequestIDs = [];

        $this->logger->log('Do the actual bounty returning per user...');

        foreach ($removeBounties as $bountyInfo) {
            list($requestID, $title, $userID, $bounty) = $bountyInfo;
            // collect unique request ID's the old fashioned way
            if (!in_array($requestID, $removeRequestIDs)) $removeRequestIDs[] = $requestID;
            // return bounty and log in staff notes
            $this->master->db->rawQuery(
                "UPDATE users_info AS ui
                   JOIN users_main AS um ON um.ID = ui.UserID
                     SET um.Uploaded=(um.Uploaded+?),
                           ui.AdminComment = CONCAT_WS(CHAR(10 using utf8), '? - Bounty of ? returned from expired Request ? (?).', ui.AdminComment)
                   WHERE ui.UserID = ?",
                [$bounty, $this->sqltime, get_size($bounty), $requestID, $title, $userID]
            );

            // Send users who got bounty returned a PM
            send_pm($userID, 0, 'Bounty returned from expired request', "Your bounty of " . get_size($bounty). " has been returned from the expired Request {$requestID} ({$title}).");
        }

        $this->logger->log('Remove requests...');

        if (count($removeRequestIDs) > 0) {
            $inQuery = implode(',', array_fill(0, count($removeRequestIDs), '?'));
            // log and update sphinx for each request
            $removeRequests = $this->master->db->rawQuery(
                "SELECT r.ID,
                        r.Title,
                        Count(v.UserID) AS NumUsers,
                        SUM( v.Bounty) AS Bounty,
                        r.GroupID,
                        rt.Description,
                        r.UserID
                   FROM requests as r
                   JOIN requests_votes as v ON v.RequestID=r.ID
                  WHERE r.ID IN({$inQuery})
               GROUP BY r.ID",
                $removeRequestIDs
            )->fetchAll(\PDO::FETCH_ASSOC);

            // delete the requests
            $this->master->db->rawQuery(
                "DELETE r, v, t, c
                   FROM requests as r
              LEFT JOIN requests_votes as v ON r.ID=v.RequestID
              LEFT JOIN requests_tags AS t ON r.ID=t.RequestID
              LEFT JOIN requests_comments AS c ON r.ID=c.RequestID
                  WHERE r.ID IN({$inQuery})",
                $removeRequestIDs
            );

            //log and update sphinx (sphinx call must be done after requests are deleted)
            foreach ($removeRequests as $request) {
                //list($requestID, $title, $NumUsers, $bounty, $GroupID) = $request;

                send_pm($request['UserID'], 0, "Your request has expired", "Your request ({$request['Title']}) has now expired.\n\nPlease feel free to start a new request with the same [spoiler=details][code]{$request['Description']}[/code][/spoiler]\n\nThanks, Staff.");

                write_log("Request {$request['ID']} ({$request['Title']}) expired - returned total of ". get_size($request['Bounty'])." to ".$request['NumUsers']." users");

                $this->master->cache->deleteValue('request_votes_'.$request['ID']);
                if ($request['GroupID']) {
                    $this->master->cache->deleteValue('requests_group_'.$request['GroupID']);
                }
                update_sphinx_requests($request['ID']);
            }
        }
    }
}
