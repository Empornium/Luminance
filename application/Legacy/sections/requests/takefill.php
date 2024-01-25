<?php
//******************************************************************************//
//--------------- Fill a request -----------------------------------------------//

$RequestID = $_REQUEST['requestid'];
if (!is_integer_string($RequestID)) {
    error(0);
}

use Luminance\Entities\Torrent;
use Luminance\Entities\User;

authorize();

# VALIDATION
$torrent = null;
if (!empty($_GET['torrentid']) && is_integer_string($_GET['torrentid'])) {
    $torrentID = $_GET['torrentid'];
    $torrent = $master->repos->torrents->load($torrentID);
} else {
    if (empty($_POST['link'])) {
        $Err = "You forgot to supply a link to the filling torrent";
    } else {
        $Link = $_POST['link'];
        if (preg_match("/".TORRENT_REGEX."/i", $Link, $matches) < 1) {
            $Err = "Your link didn't seem to be a valid torrent link";
        } else {
            $groupID = $matches[2];
            $torrent = $master->repos->torrents->get('GroupID = ?', [$groupID]);
        }
    }

    if (!empty($Err)) {
        error($Err);
    }

    if (!$groupID || !is_integer_string($groupID)) {
        error(404);
    }
}

if (!($torrent instanceof Torrent)) {
    error('Torrent link is malformed or torrent was deleted');
}

$FillerID = $activeUser['ID'];
$FillerUsername = $activeUser['Username'];

if (!empty($_POST['user']) && check_perms('site_moderate_requests')) {
    $FillerUsername = $_POST['user'];
    $filler = $master->repos->users->getByUsername($FillerUsername);
    if (!($filler instanceof User)) {
        $Err = "No such user to fill for!";
    }
} else {
    $filler = $master->repos->users->load($FillerID);
    if (!($filler instanceof User)) {
        $Err = "No such user to fill for!";
    }
}

if (time_ago($torrent->Time) < 3600 && $torrent->uploader->ID != $filler->ID && !check_perms('site_moderate_requests')) {
    $Err = "There is a one hour grace period for new uploads, to allow the torrent's uploader to fill the request";
}

$request = $master->db->rawQuery(
    "SELECT *
       FROM requests
      WHERE ID = ?",
    [$RequestID]
)->fetch(\PDO::FETCH_OBJ);

if (!empty($request->TorrentID)) {
    $Err = "This request has already been filled";
}

// Fill request
if (!empty($Err)) {
    error($Err);
}

//We're all good! Fill!
$master->db->rawQuery(
    "UPDATE requests
        SET FillerID = ?,
            UploaderID = ?,
            TorrentID = ?,
            TimeFilled = ?
      WHERE ID = ?",
    [$filler->ID, $torrent->uploader->ID, $torrent->GroupID, sqltime(), $request->ID]
);

$voterIDs = $master->db->rawQuery(
    "SELECT UserID
       FROM requests_votes
      WHERE RequestID = ?",
    [$request->ID]
)->fetchAll(\PDO::FETCH_COLUMN);

foreach ($voterIDs as $voterID) {
    send_pm($voterID, 0, "The request '{$request->Title}' has been filled", "One of your requests - [request]{$request->ID}[/request] - has been filled.\nYou can view it at [torrent]{$torrent->GroupID}[/torrent]", '');
}

$RequestVotes = get_votes_array($request->ID);
write_log("Request {$request->ID} ({$request->Title}) was filled by ".$activeUser['Username']." with the torrent {$torrent->GroupID}, uploaded by {$torrent->uploader->Username} for a ".get_size($RequestVotes['TotalBounty'])." bounty.");

if ($torrent->UserID == $filler->ID) {
    // Give bounty to filler
    $master->db->rawQuery(
        "UPDATE users_main
            SET Uploaded = (Uploaded + ?)
          WHERE ID = ?",
        [$RequestVotes['TotalBounty'], $filler->ID]
    );
    write_user_log($filler->ID, "Added +". get_size($RequestVotes['TotalBounty']). " for filling request [request]{$request->ID}[/request]");
    send_pm($filler->ID, 0, "You filled the request '{$request->Title}'", "You filled the request - [request]{$request->ID}[/request] for a bounty of ".get_size($RequestVotes['TotalBounty'])."\nThis bounty has been added to your upload stats.", '');

} else {
    // Give bounty to filler
    $FillerBounty = $RequestVotes['TotalBounty']*($master->options->BountySplit/100);
    $UploaderBounty =  $RequestVotes['TotalBounty'] - $FillerBounty;
    $master->db->rawQuery(
        "UPDATE users_main
            SET Uploaded = (Uploaded + ?)
          WHERE ID = ?",
        [$FillerBounty, $filler->ID]
    );
    write_user_log($filler->ID, "Added +". get_size($FillerBounty). " for filling request [request]{$request->ID}[/request] ");
    send_pm($filler->ID, 0, "You filled the request '{$request->Title}'", "You filled the request - [request]{$request->ID}[/request] with torrent [torrent]{$torrent->ID}[/torrent]\n The filler's bounty of ".get_size($FillerBounty)." has been added to your upload stats.", '');

    // Give bounty to uploader
    $master->db->rawQuery(
        "UPDATE users_main
            SET Uploaded = (Uploaded + ?)
          WHERE ID = ?",
        [$UploaderBounty, $torrent->uploader->ID]
    );
    write_user_log($torrent->uploader->ID, "Added +". get_size($UploaderBounty). " for uploading torrent used to fill request [request]{$request->ID}[/request] ");
    send_pm($torrent->uploader->ID, 0, "One of your torrents was used to fill request '{$request->Title}'", "One of your torrents - [torrent]{$torrent->GroupID}[/torrent] was used to fill request - [request]{$request->ID}[/request]\nThe uploader's bounty of ".get_size($UploaderBounty)." has been added to your upload stats.", '');
}

$master->cache->deleteValue('user_stats_'.$filler->ID);
$master->cache->deleteValue('user_stats_'.$torrent->uploader->ID);
$master->cache->deleteValue('request_'.$request->ID);
$master->cache->deleteValue('requests_torrent_'.$torrent->GroupID);
if ($groupID ?? false) {
    $master->cache->deleteValue('requests_group_'.$groupID);
}

$search->updateAttributes('requests', ['torrentid', 'fillerid', 'uploaderid'], [$request->ID => [(int) $torrent->GroupID,(int) $FillerID, (int) $torrent->uploader->ID]]);
update_sphinx_requests($request->ID);

header("Location: requests.php?action=view&id={$request->ID}");
