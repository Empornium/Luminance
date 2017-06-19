<?php
/*
 * This is the page that gets the values of whether to delete/disable upload/warning duration
 * every time you change the resolve type on one of the two reports pages.
 */

if (!check_perms('admin_reports')) {
    error(403, true);
}

if (is_number($_GET['id'])) {
    $ReportID = $_GET['id'];
} else {
    error('HAX on report ID', true);
}
if (is_number($_GET['torrentid'])) {
    $TorrentID = $_GET['torrentid'];
} else {
    error('HAX on report TorrentID', true);
}

if (!isset($_GET['type'])) {
    error(404, true);
} elseif (array_key_exists($_GET['type'], $Types)) {
    $ReportType = $Types[$_GET['type']];
} else {
    //There was a type but it wasn't an option!
    error('HAX on section type', true);
}

$ufl = getTorrentUFL($TorrentID);

if ($ufl['Cost']>0) {
    $RefundUFL = [($ReportType['resolve_options']['refundufl'] ? '1' : '0'), $ufl['Cost']];
} else {
    $RefundUFL = ['-1', '0'];
}


$Array = array();
$Array[0] = $ReportType['resolve_options']['delete'];
$Array[1] = $ReportType['resolve_options']['upload'];
$Array[2] = $ReportType['resolve_options']['warn'];
$Array[3] = $ReportType['resolve_options']['bounty'];
$Array[4] = $ReportType['resolve_options']['pm'];
$Array[5] = $RefundUFL;

echo json_encode($Array);
