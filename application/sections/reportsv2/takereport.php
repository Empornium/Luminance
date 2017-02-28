<?php
/*
 * This page handles the backend from when a user submits a report.
 * It checks for (in order):
 * 1. The usual POST injections, then checks that things.
 * 2. Things that are required by the report type are filled
 * 	('1' in the report_fields array).
 * 3. Things that are filled are filled with correct things.
 * 4. That the torrent you're reporting still exists.
 *
 * Then it just inserts the report to the DB and increments the counter.
 */

authorize();

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;


if (!is_number($_POST['torrentid'])) {
    error(0);
} else {
    $TorrentID = $_POST['torrentid'];
}


if (!isset($_POST['type'])) {
    error(404);
} elseif (array_key_exists($_POST['type'], $Types)) {
    $Type = $_POST['type'];
    $ReportType = $Types[$Type];
} else {
    //There was a type but it wasn't an option!
    error(403);
}

foreach ($ReportType['report_fields'] as $Field => $Value) {
    if ($Value == '1') {
        if (empty($_POST[$Field])) {
            error("You are missing a required field (".$Field.") for a ".$ReportType['title']." report.");
        }
    }
}

if (!empty($_POST['sitelink'])) {
    if (preg_match_all('/((https?:\/\/)?([a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*\.)?'.SITE_URL.'\/torrents.php\?(id=[0-9]+\&)?id=([0-9]+))/is', $_POST['sitelink'], $Matches)) {
        $ExtraIDs = $Matches[6];
        //$ExtraIDs = implode(' ', $Matches[6]);
        if (in_array($TorrentID, $Matches[6])) {
            error("The extra torrent links you gave included the link to the torrent you're reporting!");
        }
    } else {
        error("Extra Torrent link was incorrect, should look like http://".SITE_URL."/torrents.php?id=12345");
    }
} else {
    $ExtraIDs = "";
}

if (!empty($_POST['link'])) {
    //resource_type://domain:port/filepathname?query_string#anchor
    //					http://		www			.foo.com								/bar
    if (preg_match_all('/(https?:\/\/)?[a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*(:[0-9]{2,5})?(\/(\S)+)?/is', $_POST['link'], $Matches)) {
        $Links = implode(' ', $Matches[0]);
    } else {
        error("The extra links you provided weren't links...");
    }
} else {
    $Links = "";
}

if (!empty($_POST['image'])) {
    if (preg_match("/^(".IMAGE_REGEX.")( ".IMAGE_REGEX.")*$/is", trim($_POST['image']), $Matches)) {
        $Images = $Matches[0];
    } else {
        error("The extra image links you provided weren't links to images...");
    }
} else {
    $Images = "";
}

if (!empty($_POST['track'])) {
    if (preg_match('/([0-9]+( [0-9]+)*)|All/is', $_POST['track'], $Matches)) {
        $Tracks = $Matches[0];
    } else {
        error("Tracks should be given in a space seperated list of numbers (No other characters)");
    }
} else {
    $Tracks = "";
}

if (!empty($_POST['extra'])) {
    $Extra = db_string($_POST['extra']);
} else {
    error("As useful as blank reports are, could you be a tiny bit more helpful? (Leave a comment)");
}

$DB->query("SELECT ID FROM torrents WHERE ID=".$TorrentID);
if ($DB->record_count() < 1) {
    error("A torrent with that ID doesn't exist!");
}

if ($Type=='dupe') {
    if(!$ExtraIDs) error("You must include a link to a duped torrent!");
    foreach ($ExtraIDs as $DupeID) {
        if(!is_number($DupeID)) error("Cannot parse your links for duped torrents!"); // should be weeded out by regex above but keep here in case of changes etc
        $DB->query("SELECT Time FROM torrents WHERE ID=".$DupeID);
        if($DB->record_count() < 1) error("The duped torrent with ID=$DupeID doesn't exist!");
        list($TimeStamp) = $DB->next_record();
        if (time_ago($TimeStamp) > 24*3600*EXCLUDE_DUPES_AFTER_DAYS) {
            $PeerInfo = get_peers($DupeID);
            if ($PeerInfo['Seeders']< EXCLUDE_DUPES_SEEDS) {
                error($Text->full_format("Because the duped torrent [torrent]{$DupeID}[/torrent] has less than ".EXCLUDE_DUPES_SEEDS." seeders and is over "
                . time_diff(time()+ (EXCLUDE_DUPES_AFTER_DAYS*24*3600),1,false,false,0)." old it is okay to dupe it![br]Thanks for the thought though :smile1:"));
            }
        }
    }
}
if($ExtraIDs) $ExtraIDs = implode(' ', $ExtraIDs);

if (!empty($Err)) {
    error($Err);
}

$DB->query("SELECT ID FROM reportsv2 WHERE TorrentID=".$TorrentID." AND ReporterID=".db_string($LoggedUser['ID'])." AND ReportedTime > '".time_minus(3)."'");
if ($DB->record_count() > 0) {
    header('Location: torrents.php?torrentid='.$TorrentID);
    die();
}

$DB->query("INSERT INTO reportsv2
            (ReporterID, TorrentID, Type, UserComment, Status, ReportedTime, Track, Image, ExtraID, Link)
            VALUES
            (".db_string($LoggedUser['ID']).", $TorrentID, '".db_string($Type)."', '$Extra', 'New', '".sqltime()."', '".db_string($Tracks)."', '".db_string($Images)."', '".db_string($ExtraIDs)."', '".db_string($Links)."')");

$ReportID = $DB->inserted_id();

$Cache->delete_value('reports_torrent_'.$TorrentID);
$Cache->increment('num_torrent_reportsv2');

// Find the group id for the torrent and delete the cached torrent.
$DB->query("SELECT GroupID FROM torrents WHERE ID='$TorrentID'");
list($GroupID) = $DB->next_record();
$Cache->delete_value('torrent_group_'.$GroupID);

header('Location: torrents.php?torrentid='.$TorrentID);
