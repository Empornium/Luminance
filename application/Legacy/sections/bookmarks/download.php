<?php

if (
    !isset($_REQUEST['userid']) ||
    !isset($_REQUEST['preference']) ||
    !is_integer_string($_REQUEST['preference']) ||
    !is_integer_string($_REQUEST['userid']))
{ error(0); }

if (!check_perms('site_zip_downloader')) { error(403); }

// Only owner of the bookmarks or staff can download them
if ($_REQUEST['userid'] != $activeUser['ID'] && !check_perms('users_override_paranoia')) {
    error(403);
}

if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$Preferences = ['', "WHERE t.Seeders >= '1'", "WHERE t.Seeders >= '5'"];

$userID = $_REQUEST['userid'];
$Preference = $Preferences[$_REQUEST['preference']];

$SQL = "SELECT t.GroupID,
               t.ID,
               tg.Name
          FROM torrents AS t
    INNER JOIN bookmarks_torrents AS bt ON t.GroupID = bt.GroupID AND bt.UserID = ?
    INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
    {$Preference}
      ORDER BY t.GroupID ASC";

$downloads = $master->db->rawQuery($SQL, [$userID])->fetchAll(\PDO::FETCH_OBJ);
$stats = $master->db->rawQuery(
    "SELECT COUNT(*) AS Downloaded,
            SUM(t.Size) AS TotalSize
       FROM torrents AS t
 INNER JOIN bookmarks_torrents AS bt ON t.GroupID = bt.GroupID AND bt.UserID = ?
 INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
 {$Preference}",
 [$userID]
)->fetch(\PDO::FETCH_OBJ);

$date = date('M d Y, H:i');

# Might be downloading their own, might not be
list($userID, $Username) = array_values(user_info($userID));

$zipFileName = file_string($Username.'\'s '.ucfirst('bookmarks').'.zip');
$summary = "Bookmark Archive Summary - {$master->settings->main->site_name}\r\n\r\n".
           "Date:\t\t{$date}\r\n\r\n".
           "User:\t\t{$activeUser['Username']}\r\n".
           "Passkey:\t{$activeUser['torrent_pass']}\r\n\r\n".
           "Torrents Downloaded:\t\t{$stats->Downloaded}\r\n\r\n".
           "Total Size of Torrents (Ratio Hit): ".get_size($stats->TotalSize)."\r\n";

# Turn off *ALL* output buffering
for ($i=0; $i <= ob_get_level(); $i++) {
    ob_end_clean();
}

# Detect Zipstream >= 1.0.0 by presence of the Option classes
if (class_exists('ZipStream\Option\Archive')) {
    # enable output of HTTP headers
    $options = new ZipStream\Option\Archive();
    $options->setSendHttpHeaders(true);
    $options->setComment($summary);
    $zipFile = new ZipStream\ZipStream($zipFileName, $options);
} else {
    $zipFile = new ZipStream\ZipStream($zipFileName);
}

foreach ($downloads as $download) {
    # Timeout of 60 seconds per torrent
    set_time_limit(60);
    $Tor = getTorrentFile($download->ID, $activeUser['torrent_pass']);

    $TorrentName = '['.SITE_NAME.']'.((!empty($download->Name)) ? $download->Name : 'No Name');
    $FileName = trim(file_string($TorrentName));
    $FileName = cut_string($FileName, 192, true, false);
    $FileName .= '.torrent';

    $zipFile->addFile($FileName, $Tor->enc());
    # Delete the torrent from memory so GC can collect it later if needed
    unset($Tor);
    # Flush chunk to browser
    ob_flush();
    flush();
}

$zipFile->addFile('Summary.txt', $summary);
$zipFile->finish();


die();
