<?php
//******************************************************************************//
//--------------- Take unfill request ------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!is_number($RequestID)) {
    error(0);
}

$DB->query("SELECT
        r.UserID,
        r.FillerID,
        r.UploaderID,
        r.Title,
        u.Uploaded,
        f.Uploaded,
        r.TorrentID,
        r.GroupID
    FROM requests AS r
        LEFT JOIN users_main AS u ON u.ID=UploaderID
        LEFT JOIN users_main AS f ON f.ID=FillerID
    WHERE r.ID= ".$RequestID);
list($UserID, $FillerID, $UploaderID, $Title, $UploaderUploaded, $FillerUploaded, $TorrentID, $GroupID) = $DB->next_record();

if ((($LoggedUser['ID'] != $UserID && $LoggedUser['ID'] != $FillerID) && !check_perms('site_moderate_requests')) || $FillerID == 0) {
        error(403);
}

// Unfill
$DB->query("UPDATE requests SET
            TorrentID = 0,
            FillerID = 0,
            UploaderID = 0,
            TimeFilled = '0000-00-00 00:00:00',
            Visible = 1
            WHERE ID = ".$RequestID);

$FullName = $Title;

$Reason = $_POST['reason'];

$RequestVotes = get_votes_array($RequestID);
if (!empty($Reason)){
    $Reason = "\nReason: ".$Reason;
} else {
    $Reason = '';
}
if ( $FillerID == $UploaderID || $UploaderID == 0) {
    if ($RequestVotes['TotalBounty'] > $FillerUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $DB->query("UPDATE users_main SET Uploaded = 0 WHERE ID = ".$FillerID);
        $DB->query("UPDATE users_main SET Downloaded = Downloaded + ".($RequestVotes['TotalBounty']-$FillerUploaded)." WHERE ID = ".$FillerID);

        write_user_log($FillerID, "Removed -". get_size($FillerUploaded). " from Download AND added +". get_size(($RequestVotes['TotalBounty']-$FillerUploaded)). " to Upload because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, db_string("A request you filled has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size(($RequestVotes['TotalBounty']-$FillerUploaded))." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($FillerUploaded)." was removed from your upload and the remaining bounty of ".get_size(($RequestVotes['TotalBounty']-$FillerUploaded))." has been added to your download stats."));
    } else {
        $DB->query("UPDATE users_main SET Uploaded = Uploaded - ".$RequestVotes['TotalBounty']." WHERE ID = ".$FillerID);

        write_user_log($FillerID, "Removed -". get_size($RequestVotes['TotalBounty']). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, db_string("A request you filled has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size($RequestVotes['TotalBounty'])." has been removed from your upload stats."));
    }
    $Cache->delete_value('user_stats_'.$FillerID);
} else {
    // Remove from filler
    if (($RequestVotes['TotalBounty']/2) > $FillerUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $DB->query("UPDATE users_main SET Uploaded = 0 WHERE ID = ".$FillerID);
        $DB->query("UPDATE users_main SET Downloaded = Downloaded + ".(($RequestVotes['TotalBounty']/2)-$FillerUploaded)." WHERE ID = ".$FillerID);

        write_user_log($FillerID, "Removed -". get_size($FillerUploaded). " from Download AND added +". get_size((($RequestVotes['TotalBounty']/2)-$FillerUploaded)). " to Upload because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, db_string("A request you filled has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size((($RequestVotes['TotalBounty']/2)-$FillerUploaded))." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($FillerUploaded)." was removed from your upload and the remaining bounty of ".get_size((($RequestVotes['TotalBounty']/2)-$FillerUploaded))." has been added to your download stats."));
    } else {
        $DB->query("UPDATE users_main SET Uploaded = Uploaded - ".($RequestVotes['TotalBounty']/2)." WHERE ID = ".$FillerID);

        write_user_log($FillerID, "Removed -". get_size(($RequestVotes['TotalBounty'])/2). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, db_string("A request you filled has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size((($RequestVotes['TotalBounty']/2)))." has been removed from your upload stats."));
    }

    // Remove from uploader
    if (($RequestVotes['TotalBounty']/2) > $UploaderUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $DB->query("UPDATE users_main SET Uploaded = 0 WHERE ID = ".$UploaderID);
        $DB->query("UPDATE users_main SET Downloaded = Downloaded + ".(($RequestVotes['TotalBounty']/2)-$UploaderUploaded)." WHERE ID = ".$UploaderID);

        write_user_log($UploaderID, "Removed -". get_size($UploaderUploaded). " from Download AND added +". get_size((($RequestVotes['TotalBounty']/2)-$UploaderUploaded)). " to Upload because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($UploaderID, 0, db_string("A request which was filled with one of your torrents has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size((($RequestVotes['TotalBounty']/2)-$UploaderUploaded))." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($UploaderUploaded)." was removed from your upload and the remaining bounty of ".get_size((($RequestVotes['TotalBounty']/2)-$UploaderUploaded))." has been added to your download stats."));
    } else {
        $DB->query("UPDATE users_main SET Uploaded = Uploaded - ".($RequestVotes['TotalBounty']/2)." WHERE ID = ".$UploaderID);

        write_user_log($UploaderID, "Removed -". get_size(($RequestVotes['TotalBounty']/2)). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($UploaderID, 0, db_string("A request which was filled with one of your torrents has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason."\nThe bounty of ".get_size((($RequestVotes['TotalBounty']/2)))." has been removed from your upload stats."));
    }

    $Cache->delete_value('user_stats_'.$FillerID);
    $Cache->delete_value('user_stats_'.$UploaderID);
}

send_pm($UserID, 0, db_string("A request you created has been unfilled"), db_string("The request '[url=http://".SITE_URL."/requests.php?action=view&id=".$RequestID."]".$FullName."[/url]' was unfilled by [url=http://".SITE_URL."/user.php?id=".$LoggedUser['ID']."]".$LoggedUser['Username']."[/url].".$Reason));
write_log("Request $RequestID ($FullName), with a ".get_size($RequestVotes['TotalBounty'])." bounty, was un-filled by ".$LoggedUser['Username']." for the reason: ".$_POST['reason']);

$Cache->delete_value('request_'.$RequestID);
$Cache->delete_value('requests_torrent_'.$TorrentID);
if ($GroupID) {
    $Cache->delete_value('requests_group_'.$GroupID);
}

update_sphinx_requests($RequestID);

header('Location: requests.php?action=view&id='.$RequestID);
