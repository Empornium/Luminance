<?php
authorize();
if (!check_perms('site_moderate_requests')) {
    error(404);
}

$RequestID = $_POST['id'];
$VoterID = $_POST['voterid'];

if (is_integer_string($RequestID) && is_integer_string($VoterID)) {
    $Bounty = $master->db->rawQuery(
        "SELECT Bounty
           FROM requests_votes
          WHERE UserID = ?
            AND RequestID = ?",
        [$VoterID, $RequestID]
    )->fetchColumn();
    if ($master->db->foundRows() < 1) {
        error(404);
    }

    $master->db->rawQuery(
        "UPDATE users_main
            SET UPLOADED = (UPLOADED + ?)
          WHERE ID = ?",
        [$Bounty, $VoterID]
    );
    list($Title, $UploaderID) = $master->db->rawQuery(
        "SELECT Title,
                UserID
           FROM requests
          WHERE ID = ?",
        [$RequestID]
    )->fetch(\PDO::FETCH_NUM);

    write_user_log($VoterID, "Added +". get_size($Bounty). " for deleted vote on request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] by {$activeUser['Username']}\nReason: ".$_POST['reason']);

    // send users who got bounty returned a PM
    send_pm($VoterID, 0, "Bounty returned from deleted request", "Your bounty of " . get_size($Bounty). " has been returned from the deleted Request {$RequestID} ({$Title}).");

    $master->db->rawQuery(
        "DELETE
           FROM requests_votes
          WHERE UserID = ?
            AND RequestID = ?",
        [$VoterID, $RequestID]
    );

    $master->db->rawQuery(
        "SELECT RequestID
           FROM requests_votes
          WHERE RequestID = ?",
        [$RequestID]
    );
    if ($master->db->foundRows() < 1) {
        // deleting this request as that was the last vote
        $master->db->rawQuery("DELETE FROM requests          WHERE ID = ?",        [$RequestID]);
        $master->db->rawQuery("DELETE FROM requests_comments WHERE RequestID = ?", [$RequestID]);
        $master->db->rawQuery("DELETE FROM requests_tags     WHERE RequestID = ?", [$RequestID]);
        if ($UploaderID != $activeUser['ID']) {
            send_pm($UploaderID,
                    0,
                    "A request you created has been deleted",
                    "The request '{$Title}' was deleted (last vote removed) by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url]\n\nReason: {$_POST['reason']}");
        }
        write_log("Request $RequestID ($Title) was deleted (last vote removed) by ".$activeUser['Username']." for the reason: ".$_POST['reason']);
    }
    $master->cache->deleteValue('user_stats_'.$activeUser['ID']);
    $master->cache->deleteValue('_entity_User_'.$activeUser['ID']);
    $master->cache->deleteValue('request_'.$RequestID);
    $master->cache->deleteValue('request_votes_'.$RequestID);
    $master->cache->deleteValue('recent_requests_'.$UploaderID);
    update_sphinx_requests($RequestID);

    $master->db->rawQuery(
        "SELECT RequestID
           FROM requests_votes
          WHERE RequestID = ?",
        [$RequestID]
    );
    if ($master->db->foundRows() < 1) {
        header("Location: requests.php");
    } else {
        header("Location: requests.php?action=view&id=".$RequestID);
    }

} else {
    error(404);
}
?>
