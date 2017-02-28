<?php
/*
 * This is the page that gets the values of whether to delete/disable upload/warning duration
 * every time you change the resolve type on one of the two reports pages.
 */

if (!check_perms('admin_reports')) {
    error(403);
}

if (is_number($_GET['id'])) {
    $ReportID = $_GET['id'];
} else {
    echo 'HAX on report ID';
    die();
}

if (!isset($_GET['type'])) {
    error(404);
} elseif (array_key_exists($_GET['type'], $Types)) {
    $ReportType = $Types[$_GET['type']];
} else {
    //There was a type but it wasn't an option!
    echo 'HAX on section type';
    die();
}

$Array = array();
$Array[0] = $ReportType['resolve_options']['delete'];
$Array[1] = $ReportType['resolve_options']['upload'];
$Array[2] = $ReportType['resolve_options']['warn'];
$Array[3] = $ReportType['resolve_options']['bounty'];
$Array[4] = $ReportType['resolve_options']['pm'];

echo json_encode($Array);
