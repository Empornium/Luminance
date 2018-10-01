<?php
if (!check_force_anon($_GET['userid'])) {
    // then you dont get to see any torrents for any uploader!
     error(403);
}

if (!empty($_GET['userid']) && is_number($_GET['userid'])) {
    $UserID = $_GET['userid'];
} else {
    error(0);
}

if ($UserID != $LoggedUser['ID'] && !check_perms('site_zip_downloader')) {
    error(403);
}
if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$User = user_info($UserID);
$Perms = get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];


if (empty($_GET['type'])) {
    error(0);
} else {

    switch ($_GET['type']) {
        case 'uploads':
            if (!check_paranoia('uploads', $User['Paranoia'], $UserClass, $UserID)) error(PARANOIA_MSG);
            $SQL = "WHERE t.UserID='$UserID'";
            $Month = "t.Time";
            break;
        case 'snatches':
            if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $UserID)) error(PARANOIA_MSG);
            $SQL = "JOIN xbt_snatched AS x ON t.ID=x.fid WHERE x.uid='$UserID'";
            $Month = "FROM_UNIXTIME(x.tstamp)";
            break;
        case 'seeding':
            if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $UserID)) error(PARANOIA_MSG);
            $SQL = "JOIN xbt_files_users AS xfu ON t.ID = xfu.fid WHERE xfu.uid='$UserID' AND xfu.remaining = 0";
            $Month = "FROM_UNIXTIME(xfu.mtime)";
            break;
        case 'grabbed':
            if (!check_paranoia('grabbed', $User['Paranoia'], $UserClass, $UserID)) error(PARANOIA_MSG);
            $SQL = "JOIN users_downloads AS ud ON t.ID = ud.TorrentID WHERE ud.UserID='$UserID'";
            $Month = "t.Time";
            break;
        default:
            error(0);
    }
}

if ($UserID!=$LoggedUser['ID'] && !check_perms('users_view_anon_uploaders')) {
    $SQL .= " AND t.Anonymous='0'";
}

$DB->query(
    "SELECT DATE_FORMAT(".$Month.",'%b \'%y') AS Month,
            t.GroupID,
            t.ID,
            tg.Name,
            t.Size
      FROM torrents as t
      JOIN torrents_group AS tg ON t.GroupID=tg.ID
      ".$SQL."
  GROUP BY t.ID"
);

$Downloads = $DB->to_array(false, MYSQLI_NUM, false);

list($UserID, $Username) = array_values(user_info($UserID));
$Zip = new ZipStream\ZipStream($Username.'\'s '.ucfirst($_GET['type'].'.zip'));

foreach ($Downloads as $Download) {
    // Timeout of 60 seconds per torrent
    set_time_limit(60);
    list($Month, $GroupID, $TorrentID, $Name, $Size, $Contents) = $Download;
    $Tor = getTorrentFile($GroupID, $TorrentID, $LoggedUser['torrent_pass']);
    $TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
    $FileName = trim(file_string($TorrentName));
    $FileName = cut_string($FileName, 192, true, false);
    $FileName .= '.torrent';

    $Zip->addFile(file_string($Month).'/'.$FileName, $Tor->enc());
    // Delete the torrent from memory so GC can collect it later if needed
    unset($Tor);
    // Flush chunk to browser
    ob_flush();
    flush();
}
$Zip->finish();
die();
