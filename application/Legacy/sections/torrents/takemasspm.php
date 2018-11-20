<?php
//******************************************************************************//
//--------------- Take mass pm -------------------------------------------------//
// This pages handles the backend of the 'send mass pm' function. It checks	 //
// the data, and if it all validates, it sends a pm to everyone who snatched	//
// the torrent.																 //
//******************************************************************************//

authorize();

enforce_login();

$Validate = new Luminance\Legacy\Validate;

$TorrentID = (int) $_POST['torrentid'];
$GroupID = (int) $_POST['groupid'];
$Subject = $_POST['subject'];
$Message = $_POST['message'];

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

// FIXME: Still need a better perm name
if (!check_perms('site_moderate_requests')) {
    error(403);
}

$Validate->SetFields('torrentid', '1', 'number', 'Invalid torrent ID.', array('maxlength'=>1000000000, 'minlength'=>1)); // we shouldn't have torrent IDs higher than a billion
$Validate->SetFields('groupid', '1', 'number', 'Invalid group ID.', array('maxlength'=>1000000000, 'minlength'=>1)); // we shouldn't have group IDs higher than a billion either
$Validate->SetFields('subject', '1', 'string', 'Invalid subject.', array('maxlength'=>1000, 'minlength'=>1));
$Validate->SetFields('message', '1', 'string', 'Invalid message.', array('maxlength'=>10000, 'minlength'=>1));
$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Err) {
    error($Err);
}

//******************************************************************************//
//--------------- Send PMs to users --------------------------------------------//

$DB->query("SELECT DISTINCT uid FROM xbt_snatched WHERE fid='$TorrentID'");

if ($DB->record_count()>0) {
    // Save this because send_pm uses $DB to run its own query... Oops...
    $Snatchers = $DB->to_array();
    foreach ($Snatchers as $UserID) {
        send_pm($UserID[0], 0, db_string($Subject), db_string($Message));
    }
}

write_log("Mass PM sent to snatches of torrent $TorrentID in group $GroupID by {$LoggedUser['Username']}");
write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Mass PM sent to snatches by {$LoggedUser['Username']}", 1);

header("Location: torrents.php?id=$GroupID");
