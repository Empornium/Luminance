<?php
authorize();

$groupID = $_POST['groupid'];
if (!$groupID || !is_integer_string($groupID)) { error(404); }

//check user has permission to edit
$CanEdit = check_perms('torrent_edit');

if (!$CanEdit) {
    $AuthorID = $master->db->rawQuery(
        "SELECT UserID
           FROM torrents_group
          WHERE ID = ?",
        [$groupID]
    )->fetchColumn();
    $CanEdit = check_perms('site_upload_anon') && $AuthorID == $activeUser['ID'];
}
if (!$CanEdit) { error(403); }

$IsAnon = (int) $_POST['anonymous'];
$IsAnon = ($IsAnon==1) ? '1' : '0' ;

$torrents = $master->repos->torrents->find('GroupID = ?', [$groupID]);
foreach ($torrents as $torrent) {
    $torrent->Anonymous = $IsAnon;
    $master->repos->torrents->save($torrent);
}
$master->cache->deleteValue('torrents_details_'.$groupID);
$master->cache->deleteValue('torrent_group_'.$groupID);


global $master;

// Get all torrents for this torrents group ID
$Torrents = $master->db->rawQuery(
    "SELECT DISTINCT t.UserID,
            t.ID
       FROM torrents AS t
       JOIN torrents_group AS tg ON t.GroupID = tg.ID
      WHERE tg.ID = ?",
    [$groupID]
)->fetchAll(\PDO::FETCH_ASSOC);

foreach ($Torrents as $Torrent) {
    // Uncache Recent Uploads/Downloads keys
    $master->cache->deleteValue('recent_uploads_'.$Torrent['UserID']);

    // Uncache requests that this torrent has filled
    $Requests = $master->db->rawQuery(
        "SELECT ID
           FROM requests
          WHERE TorrentID = ?",
        [$Torrent['ID']]
    )->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($Requests as $Request) {
        $master->cache->deleteValue('request_'.$Request['ID']);
    }
}

write_group_log($groupID, 0, $activeUser['ID'], "Anonymous status set to " . (($IsAnon=='1') ? 'TRUE' : 'FALSE'), 1);

header('Location: torrents.php?id='.$groupID);
