<?php
//******************************************************************************//
//--------------- Take unfill request ------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!is_integer_string($RequestID)) {
    error(0);
}

$nextRecord = $master->db->rawQuery(
    "SELECT r.UserID,
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
      WHERE r.ID = ?",
    [$RequestID]
)->fetch(\PDO::FETCH_NUM);
list($userID, $FillerID, $UploaderID, $Title, $UploaderUploaded, $FillerUploaded, $torrentID, $GroupID) = $nextRecord;

if ((($activeUser['ID'] != $userID && $activeUser['ID'] != $FillerID) && !check_perms('site_moderate_requests')) || $FillerID == 0) {
        error(403);
}

// Unfill
$master->db->rawQuery(
    "UPDATE requests
        SET TorrentID = 0,
            FillerID = 0,
            UploaderID = 0,
            TimeFilled = '0000-00-00 00:00:00',
            Visible = 1
      WHERE ID = ?",
    [$RequestID]
);

$FullName = $Title;

$Reason = $_POST['reason'];

$RequestVotes = get_votes_array($RequestID);
if (!empty($Reason)) {
    $Reason = "\nReason: ".$Reason;
} else {
    $Reason = '';
}
if ( $FillerID == $UploaderID || $UploaderID == 0) {
    if ($RequestVotes['TotalBounty'] > $FillerUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = 0
              WHERE ID = ?",
            [$FillerID]
        );
        $master->db->rawQuery(
            "UPDATE users_main
                SET Downloaded = Downloaded + (? - ?)
              WHERE ID = ?",
            [$RequestVotes['TotalBounty'], $FillerUploaded, $FillerID]
        );

        write_user_log($FillerID, "Removed ". get_size($FillerUploaded). " from Upload AND added ". get_size(($RequestVotes['TotalBounty']-$FillerUploaded)). " to Download because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, "A request you filled has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size(($RequestVotes['TotalBounty']-$FillerUploaded))." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($FillerUploaded)." was removed from your upload and the remaining bounty of ".get_size(($RequestVotes['TotalBounty']-$FillerUploaded))." has been added to your download stats.");
    } else {
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = Uploaded - ?
              WHERE ID = ?",
            [$RequestVotes['TotalBounty'], $FillerID]
        );

        write_user_log($FillerID, "Removed -". get_size($RequestVotes['TotalBounty']). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, "A request you filled has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size($RequestVotes['TotalBounty'])." has been removed from your upload stats.");
    }
    $master->cache->deleteValue('user_stats_'.$FillerID);
} else {
    $FillerBounty = $RequestVotes['TotalBounty']*($master->options->BountySplit/100);
    $UploaderBounty =  $RequestVotes['TotalBounty'] - $FillerBounty;
    // Remove from filler
    if ($FillerBounty > $FillerUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = 0
              WHERE ID = ?",
            [$FillerID]
        );
        $master->db->rawQuery(
            "UPDATE users_main
                SET Downloaded = Downloaded + (? - ?)
              WHERE ID = ?",
            [$FillerBounty, $FillerUploaded, $FillerID]
        );

        write_user_log($FillerID, "Removed -". get_size($FillerUploaded). " from Download AND added +". get_size($FillerBounty - $FillerUploaded). " to Upload because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, "A request you filled has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size(($FillerBounty - $FillerUploaded))." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($FillerUploaded)." was removed from your upload and the remaining bounty of ".get_size(($FillerBounty - $FillerUploaded))." has been added to your download stats.");
    } else {
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = Uploaded - ?
              WHERE ID = ?",
            [$FillerBounty, $FillerID]
        );

        write_user_log($FillerID, "Removed -". get_size($FillerBounty). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($FillerID, 0, "A request you filled has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size($FillerBounty)." has been removed from your upload stats.");
    }

    // Remove from uploader
    if ($UploaderBounty > $UploaderUploaded) {
        // If we can't take it all out of upload, zero that out and add whatever is left as download.
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = 0
              WHERE ID = ?",
            [$UploaderID]
        );
        $master->db->rawQuery(
            "UPDATE users_main
                SET Downloaded = Downloaded + (? - ?)
              WHERE ID = ?",
            [$UploaderBounty, $UploaderUploaded, $UploaderID]
        );

        write_user_log($UploaderID, "Removed -". get_size($UploaderUploaded). " from Download AND added +". get_size($UploaderBounty - $UploaderUploaded). " to Upload because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($UploaderID, 0, "A request which was filled with one of your torrents has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size($UploaderBounty - $UploaderUploaded)." has been removed from your upload stats.\nYour account lacked sufficient upload for the full bounty to be removed, ".get_size($UploaderUploaded)." was removed from your upload and the remaining bounty of ".get_size($UploaderBounty - $UploaderUploaded)." has been added to your download stats.");
    } else {
        $master->db->rawQuery(
            "UPDATE users_main
                SET Uploaded = Uploaded - ?
              WHERE ID = ?",
            [$UploaderBounty, $UploaderID]
        );

        write_user_log($UploaderID, "Removed -". get_size($UploaderBounty). " because request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url] was unfilled.".$Reason);
        send_pm($UploaderID, 0, "A request which was filled with one of your torrents has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}\nThe bounty of ".get_size($UploaderBounty)." has been removed from your upload stats.");
    }

    $master->cache->deleteValue('user_stats_'.$FillerID);
    $master->cache->deleteValue('user_stats_'.$UploaderID);
}

send_pm($userID, 0, "A request you created has been unfilled", "The request '[url=/requests.php?action=view&id={$RequestID}]{$FullName}[/url]' was unfilled by [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url].{$Reason}");
write_log("Request $RequestID ($FullName), with a ".get_size($RequestVotes['TotalBounty'])." bounty, was un-filled by ".$activeUser['Username']." for the reason: ".$_POST['reason']);

$master->cache->deleteValue('request_'.$RequestID);
$master->cache->deleteValue('requests_torrent_'.$torrentID);
if ($GroupID) {
    $master->cache->deleteValue('requests_group_'.$GroupID);
}

update_sphinx_requests($RequestID);

header('Location: requests.php?action=view&id='.$RequestID);
