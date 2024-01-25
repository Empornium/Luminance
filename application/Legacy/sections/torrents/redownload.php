<?php
if (!check_force_anon($_GET['userid'])) {
    // then you dont get to see any torrents for any uploader!
     error(403);
}

if (!empty($_GET['userid']) && is_integer_string($_GET['userid'])) {
    $userID = $_GET['userid'];
} else {
    error(0);
}

if ($userID != $activeUser['ID'] && !check_perms('site_zip_downloader')) {
    error(403);
}
if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$User = user_info($userID);
$Perms = get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];


if (empty($_GET['type'])) {
    error(0);
} else {

    switch ($_GET['type']) {
        case 'uploads':
            if (!check_paranoia('uploads', $User['Paranoia'], $UserClass, $userID)) error(PARANOIA_MSG);
            $SQL = "WHERE t.UserID = ?";
            $Month = "t.Time";
            break;
        case 'snatches':
            if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $userID)) error(PARANOIA_MSG);
            $SQL = "JOIN xbt_snatched AS x ON t.ID=x.fid WHERE x.uid = ?";
            $Month = "FROM_UNIXTIME(x.tstamp)";
            break;
        case 'seeding':
            if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $userID)) error(PARANOIA_MSG);
            $SQL = "JOIN xbt_files_users AS xfu ON t.ID = xfu.fid WHERE xfu.uid = ? AND xfu.remaining = 0";
            $Month = "FROM_UNIXTIME(xfu.mtime)";
            break;
        case 'grabbed':
            if (!check_paranoia('grabbed', $User['Paranoia'], $UserClass, $userID)) error(PARANOIA_MSG);
            $SQL = "JOIN users_downloads AS ud ON t.ID = ud.TorrentID WHERE ud.UserID = ?";
            $Month = "t.Time";
            break;
        default:
            error(0);
    }
}

if ($userID!=$activeUser['ID'] && !check_perms('users_view_anon_uploaders')) {
    $SQL .= " AND t.Anonymous='0'";
}

$Downloads = $master->db->rawQuery(
    "SELECT DATE_FORMAT({$Month}, '%b \'%y') AS Month,
            t.GroupID,
            t.ID,
            tg.Name
       FROM torrents as t
       JOIN torrents_group AS tg ON t.GroupID=tg.ID
       {$SQL}
   GROUP BY t.ID",
    [$userID]
)->fetchAll(\PDO::FETCH_NUM);

list($userID, $Username) = array_values(user_info($userID));

$stats = $master->db->rawQuery(
  "SELECT COUNT(*) AS Downloaded,
          SUM(t.Size) AS TotalSize
     FROM torrents as t
     JOIN torrents_group AS tg ON t.GroupID=tg.ID
    ".$SQL,
    [$userID]
)->fetch(\PDO::FETCH_OBJ);
$torrentCount = $stats->Downloaded;
$totalContentSize = $stats->TotalSize;
$date = date('M d Y, H:i');
$type = ucfirst($_GET['type']);

$zipFileName = file_string($Username.'\'s '.ucfirst($_GET['type'].'.zip'));
$summary = "{$type} Archive Summary - {$master->settings->main->site_name}\r\n\r\n".
           "Date:\t\t{$date}\r\n\r\n".
           "User:\t\t{$activeUser['Username']}\r\n".
           "Passkey:\t{$activeUser['torrent_pass']}\r\n\r\n".
           "Torrents Downloaded:\t\t{$torrentCount}\r\n\r\n".
           "Total Size of Torrents (Ratio Hit): ".get_size($totalContentSize)."\r\n";

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

foreach ($Downloads as $Download) {
    // Timeout of 60 seconds per torrent
    set_time_limit(60);
    list($Month, $GroupID, $torrentID, $Name) = $Download;
    $Tor = getTorrentFile($torrentID, $activeUser['torrent_pass']);
    $TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
    $FileName = trim(file_string($TorrentName));
    $FileName = cut_string($FileName, 192, true, false);
    $FileName .= '.torrent';

    $zipFile->addFile(file_string($Month).'/'.$FileName, $Tor->enc());
    // Delete the torrent from memory so GC can collect it later if needed
    unset($Tor);
    // Flush chunk to browser
    ob_flush();
    flush();
}
$zipFile->finish();
die();
