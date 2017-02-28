<?php
authorize();
if (!check_perms("site_moderate_requests")) {
    error(404);
}

$RequestID = $_POST['id'];
$VoterID = $_POST['voterid'];

if (is_number($RequestID) && is_number($VoterID)) {
    $DB->query("SELECT Bounty FROM requests_votes WHERE UserID = ".$VoterID." AND RequestID = ".$RequestID);
    if ($DB->record_count() < 1) {
        error(404);
    }
    list($Bounty)=$DB->next_record();

    $DB->query("UPDATE users_main SET UPLOADED = (UPLOADED + $Bounty) WHERE ID = ".$VoterID);
    $DB->query("SELECT Title, UserID FROM requests WHERE ID = ".$RequestID);
    list($Title, $UploaderID)=$DB->next_record();

    write_user_log($VoterID, "Added +". get_size($Bounty). " for deleted vote on request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] by $LoggedUser[Username]\nReason: ".$_POST['reason']);

    // send users who got bounty returned a PM
    send_pm($VoterID, 0, "Bounty returned from deleted request", "Your bounty of " . get_size($Bounty). " has been returned from the deleted Request $RequestID ($Title).");

    $DB->query("DELETE FROM requests_votes WHERE UserID = ".$VoterID." AND RequestID = ".$RequestID);

    $DB->query("SELECT RequestID FROM requests_votes WHERE RequestID = ".$RequestID);
    if ($DB->record_count() < 1) {
        // deleting this request as that was the last vote
        $DB->query("DELETE FROM requests WHERE ID = ".$RequestID);
        $DB->query("DELETE FROM requests_comments WHERE RequestID = ".$RequestID);
        $DB->query("DELETE FROM requests_tags WHERE RequestID = ".$RequestID);
        if ($UploaderID != $LoggedUser['ID']) {
            send_pm($UploaderID,
                    0,
                    db_string("A request you created has been deleted"),
                    db_string("The request '".$Title."' was deleted (last vote removed) by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url]\n\nReason: ".$_POST['reason']));
        }
        write_log("Request $RequestID ($Title) was deleted (last vote removed) by ".$LoggedUser['Username']." for the reason: ".$_POST['reason']);
    }
    $Cache->delete_value('user_stats_'.$LoggedUser['ID']);
    $Cache->delete_value('_entity_User_'.$LoggedUser['ID']);
    $Cache->delete_value('request_'.$RequestID);
    $Cache->delete_value('request_votes_'.$RequestID);
    update_sphinx_requests($RequestID);

    $DB->query("SELECT RequestID FROM requests_votes WHERE RequestID = ".$RequestID);
    if ($DB->record_count() < 1) {
        header("Location: requests.php");
    } else {
        header("Location: requests.php?action=view&id=".$RequestID);
    }

} else {
    error(404);
}
?>
