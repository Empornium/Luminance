<?php
/**
 * This page is for creating a report using AJAX.
 * It should have the following posted fields:
 * 	[auth]      => AUTH_KEY
 *	[torrentid] => TORRENT_ID
 *	[type]      => TYPE
 *	[otherid]   => OTHER_ID
 * It should not be used on site as is, except in its current use (Switch) as it is lacking for any purpose but this.
 **/

// Validation
authorize(true);
if (!check_perms('admin_reports'))
    error(403, true);
if (!is_integer_string($_POST['torrentid']))
    error(0, true);
if (!isset($_POST['type']) || !array_key_exists($_POST['type'], $types))
    error('Missing or invalid type', true);

// Default init
$ReporterID = $activeUser['ID'];
$ExtraID    = $_POST['otherid'];
$torrentID  = $_POST['torrentid'];
$Type       = $_POST['type'];
$ReportType = $types[$Type];
$Extra      = '';

// Overriding
if (!empty($_POST['usercomment']))
    $Extra = $_POST['usercomment'];
if (!empty($_POST['extra']))
    $Extra = $_POST['extra'];
if (!empty($_POST['reporterid']) && is_integer_string($_POST['reporterid']))
    $ReporterID = (int) $_POST['reporterid'];

// Check if the torrent actually exists
$Query = $master->db->rawQuery(
    "SELECT tg.NewCategoryID
       FROM torrents_group AS tg
       JOIN torrents AS t ON t.GroupID = tg.ID
       WHERE t.ID = ?",
       [$torrentID]
  );
if ($Query->rowCount() < 1)
    error(404, true);

// Check if the user did not report the same torrent in the last few seconds
$Query = $master->db->rawQuery(
    "SELECT ID
       FROM reportsv2
      WHERE TorrentID = ?
        AND ReporterID = ?
        AND ReportedTime > ?",
     [$torrentID, $ReporterID, time_minus(3)]
);
if ($Query->rowCount() > 0)
    error('You just reported this torrent.', true);

// Insert the new report
$master->db->rawQuery(
    "INSERT INTO reportsv2 (ReporterID, TorrentID, Type, UserComment, Status, ReportedTime, ExtraID)
          VALUES (?, ?, ?, ?, 'New', ?, ?)",
    [
        $ReporterID,
        $torrentID,
        $Type,
        $Extra,
        sqltime(),
        $ExtraID
    ]
);
$ReportID = $master->db->lastInsertID();

// Update cache
$master->cache->deleteValue('reports_torrent_'.$torrentID);
$master->cache->incrementValue('num_torrent_reportsv2');

echo $ReportID;
