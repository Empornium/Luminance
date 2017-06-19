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
if (!is_number($_POST['torrentid']))
    error(0, true);
if (!isset($_POST['type']) || !array_key_exists($_POST['type'], $Types))
    error('Missing or invalid type', true);

// Default init
$ReporterID = $LoggedUser['ID'];
$ExtraID    = $_POST['otherid'];
$TorrentID  = $_POST['torrentid'];
$Type       = $_POST['type'];
$ReportType = $Types[$Type];
$Extra      = '';

// Overriding
if (!empty($_POST['usercomment']))
    $Extra = $_POST['usercomment'];
if (!empty($_POST['extra']))
    $Extra = $_POST['extra'];
if (!empty($_POST['reporterid']) && is_number($_POST['reporterid']))
    $ReporterID = (int) $_POST['reporterid'];

// Check if the torrent actually exists
$Query = $master->db->raw_query("SELECT tg.NewCategoryID
                                 FROM torrents_group AS tg
                                 JOIN torrents AS t ON t.GroupID = tg.ID
                                 WHERE t.ID = ?",
                                 [$TorrentID]);
if ($Query->rowCount() < 1)
    error(404, true);

// Check if the user did not report the same torrent in the last few seconds
$Query = $master->db->raw_query("SELECT ID
                                 FROM reportsv2
                                 WHERE TorrentID = :TorrentID AND ReporterID = :ReporterID AND ReportedTime > :ReportedTime",
                                 [
                                     ':TorrentID'    => $TorrentID,
                                     ':ReporterID'   => $ReporterID,
                                     ':ReportedTime' => time_minus(3)
                                 ]);
if ($Query->rowCount() > 0)
    error('You just reported this torrent.', true);

// Insert the new report
$master->db->raw_query("INSERT INTO reportsv2
                        (ReporterID, TorrentID, Type, UserComment, Status, ReportedTime, ExtraID)
                        VALUES (:ReporterID, :TorrentID, :Type, :UserComment, :Status, :ReportedTime, :ExtraID)",
                        [
                            ':ReporterID'   => $ReporterID,
                            ':TorrentID'    => $TorrentID,
                            ':Type'         => $Type,
                            ':UserComment'  => $Extra,
                            ':Status'       => 'New',
                            ':ReportedTime' => sqltime(),
                            ':ExtraID'      => $ExtraID
                        ]);
$ReportID = $master->db->last_insert_id();

// Update cache
$Cache->delete_value('reports_torrent_'.$TorrentID);
$Cache->increment('num_torrent_reportsv2');

echo $ReportID;
