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
            if (!check_paranoia('uploads', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
            $SQL = "WHERE t.UserID='$UserID'";
            $Month = "t.Time";
            break;
        case 'snatches':
            if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
            $SQL = "JOIN xbt_snatched AS x ON t.ID=x.fid WHERE x.uid='$UserID'";
            $Month = "FROM_UNIXTIME(x.tstamp)";
            break;
        case 'seeding':
            if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
            $SQL = "JOIN xbt_files_users AS xfu ON t.ID = xfu.fid WHERE xfu.uid='$UserID' AND xfu.remaining = 0";
            $Month = "FROM_UNIXTIME(xfu.mtime)";
            break;
        case 'grabbed':
            if (!check_paranoia('grabbed', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
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

Luminance\Legacy\Zip::unlimit();

$DB->query("SELECT
    DATE_FORMAT(".$Month.",'%b \'%y') AS Month,
    t.GroupID,
    tg.Name,
    t.Size,
    f.File
    FROM torrents as t
    JOIN torrents_group AS tg ON t.GroupID=tg.ID
    LEFT JOIN torrents_files AS f ON t.ID=f.TorrentID
    ".$SQL."
    GROUP BY t.ID");
$Downloads = $DB->to_array(false,MYSQLI_NUM,false);

list($UserID, $Username) = array_values(user_info($UserID));
$Zip = new Luminance\Legacy\Zip($Username.'\'s '.ucfirst($_GET['type']));
foreach ($Downloads as $Download) {
    list($Month, $GroupID, $Name, $Size, $Contents) = $Download;
    $Contents = unserialize(base64_decode($Contents));
    $Tor = new Luminance\Legacy\Torrent($Contents, true);
    $Tor->set_announce_url(ANNOUNCE_URL.'/'.$LoggedUser['torrent_pass'].'/announce');
    $Tor->set_comment('http'.($master->request->ssl ? 's' : '').'://'. SITE_URL."/torrents.php?id=$GroupID");

    if ($master->settings->main->announce_urls) {
        $announce_urls = [];
        foreach (explode('|', $master->settings->main->announce_urls) as $u) {
           $announce_urls[] = $u.'/'.$LoggedUser['torrent_pass'].'/announce';
        }
        $Tor->set_multi_announce($announce_urls);
    } else {
        unset($Tor->Val['announce-list']);
    }

    $TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
    $FileName = trim(file_string($TorrentName));
    $FileName = cut_string($FileName, 192, true, false);
    $FileName .= '.torrent';
    $Zip->add_file($Tor->enc(), file_string($Month).'/'.$FileName);
}
$Zip->close_stream();
