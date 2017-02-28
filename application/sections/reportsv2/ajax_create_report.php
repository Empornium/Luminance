<?php
/*
 * This page is for creating a report using AJAX.
 * It should have the following posted fields:
 * 	[auth] => AUTH_KEY
 *	[torrentid] => TORRENT_ID
 *	[type] => TYPE
 *	[otherid] => OTHER_ID
 *
 * It should not be used on site as is, except in its current use (Switch) as it is lacking for any purpose but this.
 */

if (!check_perms('admin_reports')) {
    error(403);
}

authorize();

if (!is_number($_POST['torrentid'])) {
    echo 'No Torrent ID';
    die();
} else {
    $TorrentID = $_POST['torrentid'];
}

$DB->query("SELECT tg.NewCategoryID FROM torrents_group AS tg JOIN torrents AS t ON t.GroupID=tg.ID WHERE t.ID = ".$TorrentID);
if ($DB->record_count() < 1) {
    $Err = "No torrent with that ID exists!";
}

if (!isset($_POST['type'])) {
    echo 'Missing Type';
    die();
} elseif (array_key_exists($_POST['type'], $Types)) {
    $Type = $_POST['type'];
    $ReportType = $Types[$Type];
} else {
    //There was a type but it wasn't an option!
    echo 'Wrong type';
    die();
}

$ExtraID = $_POST['otherid'];

if (!empty($_POST['usercomment'])) {
    $Extra = db_string(urldecode($_POST['usercomment']));
} elseif (!empty($_POST['extra'])) {
    $Extra = db_string($_POST['extra']);
} else {
    $Extra = "";
}
if (!empty($_POST['reporterid']) && is_number($_POST['reporterid'])) {
    $ReporterID = (int) $_POST['reporterid'];
} else {
    $ReporterID = $LoggedUser['ID'];
}

if (!empty($Err)) {
    echo $Err;
    die();
}

$DB->query("SELECT ID FROM reportsv2 WHERE TorrentID=$TorrentID AND ReporterID=$ReporterID AND ReportedTime > '".time_minus(3)."'");
if ($DB->record_count() > 0) {
    die();
}

$DB->query("INSERT INTO reportsv2
            (ReporterID, TorrentID, Type, UserComment, Status, ReportedTime, ExtraID)
            VALUES
            ($ReporterID, $TorrentID, '$Type', '$Extra', 'New', '".sqltime()."', '$ExtraID')");

$ReportID = $DB->inserted_id();

$Cache->delete_value('reports_torrent_'.$TorrentID);
$Cache->increment('num_torrent_reportsv2');

echo $ReportID;
