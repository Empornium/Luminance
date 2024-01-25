<?php
//******************************************************************************//
//--------------- Take mass pm -------------------------------------------------//
// This pages handles the backend of the 'send mass pm' function. It checks	 //
// the data, and if it all validates, it sends a pm to everyone who snatched	//
// the torrent.																 //
//******************************************************************************//

authorize();

enforce_login();

$Validate = new \Luminance\Legacy\Validate;

$torrentID = (int) $_POST['torrentid'];
$GroupID = (int) $_POST['groupid'];
$Subject = $_POST['subject'];
$Message = $_POST['message'];

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

// FIXME: Still need a better perm name
if (!check_perms('site_moderate_requests')) {
    error(403);
}

$Validate->SetFields('torrentid', '1', 'number', 'Invalid torrent ID.', ['maxlength'=>1000000000, 'minlength'=>1]); // we shouldn't have torrent IDs higher than a billion
$Validate->SetFields('groupid',   '1', 'number', 'Invalid group ID.',   ['maxlength'=>1000000000, 'minlength'=>1]); // we shouldn't have group IDs higher than a billion either
$Validate->SetFields('subject',   '1', 'string', 'Invalid subject.',    ['maxlength'=>1000,       'minlength'=>1]);
$Validate->SetFields('message',   '1', 'string', 'Invalid message.',    ['maxlength'=>10000,      'minlength'=>1]);
$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Err) {
    error($Err);
}

//******************************************************************************//
//--------------- Send PMs to users --------------------------------------------//

$Snatchers = $master->db->rawQuery(
    "SELECT DISTINCT uid
       FROM xbt_snatched
      WHERE fid = ?",
    [$torrentID]
)->fetchAll(\PDO::FETCH_COLUMN);

if ($master->db->foundRows() > 0) {
    foreach ($Snatchers as $userID) {
        send_pm($userID, 0, $Subject, $Message);
    }
}

write_log("Mass PM sent to snatchers of torrent {$torrentID} by {$activeUser['Username']}");
write_group_log($GroupID, $torrentID, $activeUser['ID'], "Mass PM sent to snatchers by {$activeUser['Username']}", 1);

header("Location: torrents.php?id={$GroupID}");
