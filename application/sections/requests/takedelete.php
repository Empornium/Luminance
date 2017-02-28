<?php
//******************************************************************************//
//--------------- Delete request -----------------------------------------------//

authorize();

if (!check_perms('site_moderate_requests')) {
    error(403);
}

$requestID = $_POST['id'];
if (!is_number($requestID)) {
    error(0);
}
$returnBounty = (isset($_POST['returnvotes']))? true : false;

list($uploaderID, $title, $groupID, $totalBounty) = $master->db->raw_query("SELECT r.UserID, r.Title, r.GroupID, Sum(rv.Bounty) AS TotalBounty
                                                                          FROM requests AS r
                                                                     LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID
                                                                         WHERE r.ID = :requestid
                                                                      GROUP BY r.ID",
                                                                              [':requestid' => $requestID])->fetch(\PDO::FETCH_NUM);
// format number
$totalBounty = get_size($totalBounty);

if ($returnBounty===true) {

    $votes = $master->db->raw_query("SELECT UserID AS VoterID, Bounty FROM requests_votes WHERE RequestID = :requestid", [':requestid' => $requestID])->fetchAll(\PDO::FETCH_ASSOC);

    foreach($votes as $vote) {
        $master->db->raw_query("UPDATE users_main SET UPLOADED = (UPLOADED + :bounty) WHERE ID = :voterid",
                                [':bounty'  => $vote['Bounty'],
                                 ':voterid' => $vote['VoterID']]);

        write_user_log($vote['VoterID'],
                       "Added +". get_size($vote['Bounty']). " for deleted vote on request [url=/requests.php?action=view&id={$requestID}]{$title}[/url] by $LoggedUser[Username]\nReason: ".$_POST['reason']);

        // send users who got bounty returned a PM
        send_pm($vote['VoterID'], 0, "Bounty returned from deleted request", "Your bounty of " . get_size($vote['Bounty']). " has been returned from the deleted Request $requestID ($title).");

        $master->cache->delete_value('user_stats_'.$vote['VoterID']);
        $master->cache->delete_value('_entity_User_legacy_'.$vote['VoterID']);
    }
    $returntext = ' '.count($votes)." votes were returned.";
} else {
    $returntext = " No votes were returned.";
}

// Delete request, votes and tags
$master->db->raw_query("DELETE FROM requests WHERE ID=:requestid", [':requestid' => $requestID]);
$master->db->raw_query("DELETE FROM requests_comments WHERE RequestID = :requestid", [':requestid' => $requestID]);
$master->db->raw_query("DELETE FROM requests_votes WHERE RequestID = :requestid", [':requestid' => $requestID]);
$master->db->raw_query("DELETE FROM requests_tags WHERE RequestID = :requestid", [':requestid' => $requestID]);

if ($uploaderID != $LoggedUser['ID']) {
    send_pm($uploaderID,
            0,
            db_string("A request you created has been deleted"),
            db_string("The request '".$title."' was deleted by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url]\n\nReason: ".$_POST['reason']));
}

write_log("Request ($requestID) '$title' ($totalBounty) was deleted by ".$LoggedUser['Username'].".$returntext For the reason: ".$_POST['reason']);

// only write the reason to userlog if it has not just been written already
if (!$returnBounty) $returntext = "$returntext\nReason: $_POST[reason]";
write_user_log($uploaderID, "Request [url=/requests.php?action=view&id={$requestID}]{$title}[/url] ($totalBounty) was deleted by $LoggedUser[Username].$returntext");

$master->cache->delete_value('request_'.$requestID);
$master->cache->delete_value('request_votes_'.$requestID);
if ($groupID) {
    $master->cache->delete_value('requests_group_'.$groupID);
}
update_sphinx_requests($requestID);

header('Location: requests.php');
