<?php
//******************************************************************************//
//--------------- Delete request -----------------------------------------------//

authorize();

if (!check_perms('site_moderate_requests')) {
    error(403);
}

$requestID = $_POST['id'];
if (!is_integer_string($requestID)) {
    error(0);
}
$returnBounty = (isset($_POST['returnvotes']))? true : false;

list($uploaderID, $title, $groupID, $torrentID, $totalBounty) = $master->db->rawQuery(
    "SELECT r.UserID,
            r.Title,
            r.GroupID,
            r.TorrentID,
            Sum(rv.Bounty) AS TotalBounty
      FROM requests AS r
 LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID
     WHERE r.ID = ?
  GROUP BY r.ID",
    [$requestID]
)->fetch(\PDO::FETCH_NUM);

// format number
$totalBounty = get_size($totalBounty);

if ($returnBounty===true) {

    $votes = $master->db->rawQuery(
        "SELECT Bounty,
                UserID
           FROM requests_votes
          WHERE RequestID = ?",
        [$requestID]
    )->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($votes as $vote) {
        $master->db->rawQuery(
            "UPDATE users_main
                SET UPLOADED = (UPLOADED + ?)
              WHERE ID = ?",
            [$vote['Bounty'], $vote['UserID']]
        );

        write_user_log($vote['UserID'],
                       "Added +". get_size($vote['Bounty']). " for deleted vote on request [url=/requests.php?action=view&id={$requestID}]{$title}[/url] by {$activeUser['Username']}\nReason: ".$_POST['reason']);

        // send users who got bounty returned a PM
        $SubjectDelete = "Bounty returned from deleted request";
        $ReqDelete = "Your bounty of " . get_size($vote['Bounty']) . " has been returned from the deleted request $requestID ($title)";
        $ReqDelete .= "\nReason for deletion: " . $_POST['reason'];
        send_pm($vote['UserID'], 0, $SubjectDelete, $ReqDelete);

        $master->cache->deleteValue('user_stats_'.$vote['UserID']);
        $master->cache->deleteValue('_entity_User_legacy_'.$vote['UserID']);
    }
    $returntext = ' '.count($votes)." votes were returned.";
} else {
    $returntext = " No votes were returned.";
}

// Delete request, comments, votes and tags
$master->db->rawQuery(
    "DELETE r, rc, rt, rv
       FROM requests AS r
  LEFT JOIN requests_comments AS rc ON r.ID=rc.RequestID
  LEFT JOIN requests_tags AS rt ON r.ID=rt.RequestID
  LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID
      WHERE r.ID = ?",
    [$requestID]
);

if ($uploaderID != $activeUser['ID']) {
    $Subject = "A request you created has been deleted";
    $Message = "Your request $requestID ($title) was deleted.";
    $Message .= "\n Reason: " . $_POST['reason'];
    send_pm($uploaderID, 0, $Subject, $Message);
}

write_log("Request ($requestID) '$title' ($totalBounty) was deleted by ".$activeUser['Username'].".$returntext For the reason: ".$_POST['reason']);

// only write the reason to userlog if it has not just been written already
if (!$returnBounty) $returntext = "$returntext\nReason: $_POST[reason]";
write_user_log($uploaderID, "Request [url=/requests.php?action=view&id={$requestID}]{$title}[/url] ($totalBounty) was deleted by {$activeUser['Username']}.$returntext");

$master->cache->deleteValue('request_'.$requestID);
$master->cache->deleteValue('request_votes_'.$requestID);
if ($torrentID) {
    $master->cache->deleteValue('requests_torrent_'.$torrentID);
}
if ($groupID) {
    $master->cache->deleteValue('requests_group_'.$groupID);
}
update_sphinx_requests($requestID);

header('Location: requests.php');
