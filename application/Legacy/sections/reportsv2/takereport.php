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

$bbCode = new \Luminance\Legacy\Text;


if (!is_integer_string($_POST['torrentid'])) {
    error(0);
} else {
    $torrentID = $_POST['torrentid'];
}


if (!isset($_POST['type'])) {
    error(404);
} elseif (array_key_exists($_POST['type'], $types)) {
    $Type = $_POST['type'];
    $ReportType = $types[$Type];
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
    if (preg_match_all("/".TORRENT_REGEX."/is", $_POST['sitelink'], $matches)) {
        $ExtraIDs = $matches[2];
        if (in_array($torrentID, $ExtraIDs)) {
            error("The extra torrent links you gave included the link to the torrent you're reporting!");
        }
    } else {
        error("Extra Torrent link was incorrect, should look like https://".SITE_URL."/torrents.php?id=12345");
    }
} else {
    $ExtraIDs = "";
}

if (!empty($_POST['link'])) {
    //resource_type://domain:port/filepathname?query_string#anchor
    //                    https://          www          .foo.com                       /bar
    if (preg_match_all('/(https?:\/\/)?[a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*(:[0-9]{2,5})?(\/(\S)+)?/is', $_POST['link'], $matches)) {
        $Links = implode(' ', $matches[0]);
    } else {
        error("The extra links you provided weren't links...");
    }
} else {
    $Links = "";
}

if (!empty($_POST['image'])) {
    if (preg_match("/^(".IMAGE_REGEX.")( ".IMAGE_REGEX.")*$/is", trim($_POST['image']), $matches)) {
        $Images = $matches[0];
    } else {
        error("The extra image links you provided weren't links to images...");
    }
} else {
    $Images = "";
}

if (!empty($_POST['track'])) {
    if (preg_match('/([0-9]+( [0-9]+)*)|All/is', $_POST['track'], $matches)) {
        $Tracks = $matches[0];
    } else {
        error("Tracks should be given in a space separated list of numbers (No other characters)");
    }
} else {
    $Tracks = "";
}

if (!empty($_POST['extra'])) {
    $Extra = $_POST['extra'];
} else {
    error("As useful as blank reports are, could you be a tiny bit more helpful? (Leave a comment)");
}

$torrentCount = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM torrents
      WHERE ID = ?",
    [$torrentID]
)->fetchColumn();
if ($torrentCount < 1) {
    error("A torrent with that ID doesn't exist!");
}

if ($Type=='dupe') {
    if (!$ExtraIDs) error("You must include a link to a duped torrent!");
    foreach ($ExtraIDs as $DupeID) {
        if (!is_integer_string($DupeID)) error("Cannot parse your links for duped torrents!"); // should be weeded out by regex above but keep here in case of changes etc
        $timeStamp = $master->db->rawQuery(
            "SELECT Time
               FROM torrents
              WHERE ID = ?",
            [$DupeID]
        )->fetchColumn();
        if ($master->db->foundRows() < 1) error("The duped torrent with ID={$DupeID} doesn't exist!");
        if (time_ago($timeStamp) > 24*3600*EXCLUDE_DUPES_AFTER_DAYS) {
            $PeerInfo = get_peers($DupeID);
            if ($PeerInfo['Seeders']< EXCLUDE_DUPES_SEEDS) {
                error($bbCode->full_format("Because the duped torrent /torrents.php?torrentid={$DupeID} has less than ".EXCLUDE_DUPES_SEEDS." seeders and is over "
                . time_diff(time()+ (EXCLUDE_DUPES_AFTER_DAYS*24*3600),1,false,false,0)." old it is okay to dupe it![br]Thanks for the thought though :smile1:"));
            }
        }
    }
}
if ($ExtraIDs) $ExtraIDs = implode(' ', $ExtraIDs);

if (!empty($Err)) {
    error($Err);
}

$recordCount = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM reportsv2
      WHERE TorrentID = ?
        AND ReporterID = ?
        AND ReportedTime > ?",
    [$torrentID, $activeUser['ID'], time_minus(3)]
)->fetchColumn();

if ($recordCount > 0) {
    header('Location: torrents.php?id='.$torrentID);
    die();
}

$master->db->rawQuery(
    "INSERT INTO reportsv2 (ReporterID, TorrentID, Type, UserComment, Status, ReportedTime, Track, Image, ExtraID, Link)
          VALUES (?, ?, ?, ?, 'New', ?, ?, ?, ?, ?)",
    [
        $activeUser['ID'],
        $torrentID,
        $Type,
        $Extra,
        sqltime(),
        $Tracks,
        $Images,
        $ExtraIDs,
        $Links,
    ]
);

$ReportID = $master->db->lastInsertID();

$master->cache->deleteValue('reports_torrent_'.$torrentID);
$master->cache->incrementValue('num_torrent_reportsv2');

// Find the group id for the torrent and delete the cached torrent.
$GroupID = $master->db->rawQuery(
    "SELECT GroupID
       FROM torrents
      WHERE ID = ?",
    [$torrentID]
)->fetchColumn();
$master->cache->deleteValue('torrent_group_'.$GroupID);

$scheme = $master->request->ssl ? 'https' : 'http';
$message  = "[\002\00304Torrent Reported\003\002]";
$message .= " by ".$activeUser['Username'];
$message .= " - {$scheme}://{$master->settings->main->site_url}/reportsv2.php?view=report&id={$ReportID}";
$master->irker->announceReport($message);

header('Location: torrents.php?id='.$GroupID);
