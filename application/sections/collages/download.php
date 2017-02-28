<?php
/*
This page is something of a hack so those
easily scared off by funky solutions, don't
touch it! :P

There is a central problem to this page, it's
impossible to order before grouping in SQL, and
it's slow to run sub queries, so we had to get
creative for this one.

The solution I settled on abuses the way
$DB->to_array() works. What we've done, is
backwards ordering. The results returned by the
query have the best one for each GroupID last,
and while to_array traverses the results, it
overwrites the keys and leaves us with only the
desired result. This does mean however, that
the SQL has to be done in a somewhat backwards
fashion.

Thats all you get for a disclaimer, just
remember, this page isn't for the faint of
heart. -A9

SQL template:
SELECT
    CASE
    WHEN t.Format='Ogg Vorbis' THEN 0
    WHEN t.Format='MP3' AND t.Encoding='V0 (VBR)' THEN 1
    WHEN t.Format='MP3' AND t.Encoding='V2 (VBR)' THEN 2
    ELSE 100
    END AS Rank,
    t.GroupID,
    t.Media,
    t.Format,
    t.Encoding,
    IF(t.Year=0,tg.Year,t.Year),
    tg.Name,
    a.Name,
    t.Size
FROM torrents AS t
INNER JOIN collages_torrents AS c ON t.GroupID=c.GroupID AND c.CollageID='8'
INNER JOIN torrents_group AS tg ON tg.ID=t.GroupID AND tg.CategoryID='1'
LEFT JOIN artists_group AS a ON a.ArtistID=tg.ArtistID
LEFT JOIN torrents_files AS f ON t.ID=f.TorrentID
ORDER BY t.GroupID ASC, Rank DESC, t.Seeders ASC
*/

if(
    !isset($_REQUEST['collageid']) ||
    !isset($_REQUEST['preference']) ||
    !is_number($_REQUEST['preference']) ||
    !is_number($_REQUEST['collageid']))
{ error(0); }

if (!check_perms('site_zip_downloader')) { error(403); }

$Preferences = array('', "WHERE t.Seeders >= '1'", "WHERE t.Seeders >= '5'");

$CollageID = $_REQUEST['collageid'];
$Preference = $Preferences[$_REQUEST['preference']];

$DB->query("SELECT Name FROM collages WHERE ID='$CollageID'");
list($CollageName) = $DB->next_record(MYSQLI_NUM,false);

$SQL = "SELECT
t.GroupID,
t.ID,
tg.Name,
t.Size
FROM torrents AS t
INNER JOIN collages_torrents AS c ON t.GroupID=c.GroupID AND c.CollageID='$CollageID'
INNER JOIN torrents_group AS tg ON tg.ID=t.GroupID
$Preference
ORDER BY t.GroupID ASC";

$DB->query($SQL);
$Downloads = $DB->to_array('1',MYSQLI_NUM,false);
$TotalSize = 0;

if (count($Downloads)) {
    foreach ($Downloads as $Download) {
        $TorrentIDs[] = $Download[1];
    }
    $DB->query("SELECT TorrentID, file FROM torrents_files WHERE TorrentID IN (".implode(',', $TorrentIDs).")");
    $Torrents = $DB->to_array('TorrentID',MYSQLI_ASSOC,false);
}

require(SERVER_ROOT.'/classes/class_torrent.php');
require(SERVER_ROOT.'/classes/class_zip.php');
$Zip = new ZIP(file_string($CollageName));
$Zip->unlimit(); // lets see if this solves the download problems with super large zips

foreach ($Downloads as $Download) {
    list($GroupID, $TorrentID, $Album, $Size) = $Download;
    $TotalSize += $Size;
    $Contents = unserialize(base64_decode($Torrents[$TorrentID]['file']));
    $Tor = new TORRENT($Contents, true);
    $Tor->set_announce_url(ANNOUNCE_URL.'/'.$LoggedUser['torrent_pass'].'/announce');
      $Tor->set_comment('http'.($master->request->ssl ? 's' : '').'://'. SITE_URL."/torrents.php?id=$GroupID");

# NINJA
if ($master->settings->main->announce_urls) {
    $announce_urls = [];
    foreach (explode('|', $master->settings->main->announce_urls) as $u) {
	$announce_urls[] = $u.'/'.$LoggedUser['torrent_pass'].'/announce';
    }
    $Tor->set_multi_announce($announce_urls);
} else {
    unset($Tor->Val['announce-list']);
}

    // We need this section for long file names :/
    $TorrentName='';
    $TorrentInfo='';
    $TorrentName = file_string($Album);
    $FileName = $TorrentName.$TorrentInfo;
    $FileName = cut_string($FileName, 192, true, false);

    $Zip->add_file($Tor->enc(), $FileName.'.torrent');
}

$Skipped = count($Skips);
$Downloaded =count($Downloads);
$Time = number_format(((microtime(true)-$ScriptStartTime)*1000),5).' ms';
$Used = get_size(memory_get_usage(true));
$Date = date('M d Y, H:i');
$Zip->add_file('Collector Download Summary - '.SITE_NAME."\r\n\r\nUser:\t\t$LoggedUser[Username]\r\nPasskey:\t$LoggedUser[torrent_pass]\r\n\r\nTime:\t\t$Time\r\nUsed:\t\t$Used\r\nDate:\t\t$Date\r\n\r\nTorrents Downloaded:\t\t$Downloaded\r\n\r\nTotal Size of Torrents (Ratio Hit): ".get_size($TotalSize)."\r\n", 'Summary.txt');
$Settings = array(implode(':',$_REQUEST['list']),$_REQUEST['preference']);
$Zip->close_stream();

$Settings = array(implode(':',$_REQUEST['list']),$_REQUEST['preference']);

if (!isset($LoggedUser['Collector']) || $LoggedUser['Collector'] != $Settings) {
    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='$LoggedUser[ID]'");
    list($Options) = $DB->next_record(MYSQLI_NUM,false);
    $Options = unserialize($Options);
    $Options['Collector'] = $Settings;
    $DB->query("UPDATE users_info SET SiteOptions='".db_string(serialize($Options))."' WHERE UserID='$LoggedUser[ID]'");
    $master->repos->users->uncache($LoggedUser['ID']);
}
