<?php

if(
    !isset($_REQUEST['userid']) ||
    !isset($_REQUEST['preference']) ||
    !is_number($_REQUEST['preference']) ||
    !is_number($_REQUEST['userid']))
{ error(0); }

if (!check_perms('site_zip_downloader')) { error(403); }

// Only owner of the bookmarks or staff can download them
if ($_REQUEST['userid'] != $LoggedUser['ID'] && !check_perms('users_override_paranoia')) {
    error(403);
}

if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$Preferences = array('', "WHERE t.Seeders >= '1'", "WHERE t.Seeders >= '5'");

$UserID = $_REQUEST['userid'];
$Preference = $Preferences[$_REQUEST['preference']];

$SQL = "SELECT t.GroupID,
               t.ID,
               tg.Name,
               t.Size
          FROM torrents AS t
    INNER JOIN bookmarks_torrents AS bt ON t.GroupID=bt.GroupID AND bt.UserID='$UserID'
    INNER JOIN torrents_group AS tg ON tg.ID=t.GroupID
    $Preference
      ORDER BY t.GroupID ASC";

$DB->query($SQL);
$Downloads = $DB->to_array('1',MYSQLI_NUM,false);
$TotalSize = 0;

list($UserID, $Username) = array_values(user_info($UserID));
$Zip = new ZipStream\ZipStream(file_string($Username.'\'s '.ucfirst('bookmarks').'.zip'));

foreach ($Downloads as $Download) {
    # Timeout of 60 seconds per torrent
    set_time_limit(60);
    list($GroupID, $TorrentID, $Name, $Size) = $Download;
    $TotalSize += $Size;
    $Tor = getTorrentFile($GroupID, $TorrentID, $LoggedUser['torrent_pass']);

    $TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
    $FileName = trim(file_string($TorrentName));
    $FileName = cut_string($FileName, 192, true, false);
    $FileName .= '.torrent';

    $Zip->addFile($FileName, $Tor->enc());
    # Delete the torrent from memory so GC can collect it later if needed
    unset($Tor);
    # Flush chunk to browser
    ob_flush();
    flush();
}

$Downloaded = count($Downloads);
$Time = number_format(((microtime(true)-$master->debug->StartTime)*1000),5).' ms';
$Used = get_size(memory_get_usage(true));
$Date = date('M d Y, H:i');$Zip->addFile('Summary.txt', 'Collector Download Summary - '.SITE_NAME."\r\n\r\nUser:\t\t$LoggedUser[Username]\r\nPasskey:\t$LoggedUser[torrent_pass]\r\n\r\nTime:\t\t$Time\r\nUsed:\t\t$Used\r\nDate:\t\t$Date\r\n\r\nTorrents Downloaded:\t\t$Downloaded\r\n\r\nTotal Size of Torrents (Ratio Hit): ".get_size($TotalSize)."\r\n");
$Zip->finish();

$Settings = array(implode(':',(array)$_REQUEST['list']),$_REQUEST['preference']);

if (!isset($LoggedUser['Collector']) || $LoggedUser['Collector'] != $Settings) {
    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='$LoggedUser[ID]'");
    list($Options) = $DB->next_record(MYSQLI_NUM,false);
    $Options = unserialize($Options);
    $Options['Collector'] = $Settings;
    $DB->query("UPDATE users_info SET SiteOptions='".db_string(serialize($Options))."' WHERE UserID='$LoggedUser[ID]'");
    $master->repos->users->uncache($LoggedUser['ID']);
}

die();
