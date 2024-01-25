<?php
authorize();

if (!check_perms('admin_reports') && !check_perms('site_project_team') && !check_perms('forum_moderate')) {
    error(403);
}

if (empty($_POST['reportid']) && !is_integer_string($_POST['reportid'])) {
    error(403);
}

$ReportID = (int) $_POST['reportid'];

list($Type, $ConvID) = $master->db->rawQuery(
    "SELECT Type,
            ConvID
       FROM reports
      WHERE ID = ?",
    [$ReportID]
)->fetch(\PDO::FETCH_NUM);
if (!check_perms('admin_reports')) {
    if (check_perms('forum_moderate')) {
        if (!in_array($Type, ['collages_comment', 'post', 'requests_comment', 'thread', 'torrents_comment'])) {
            error($Type);
        }
    } elseif (check_perms('site_project_team')) {
        if ($Type != "request_update") {
            error(403);
        }
    }
}

$Comment = sqltime()." - Resolved by {$activeUser['Username']}";
if (isset($_POST['comment'])) $Comment .= " - {$_POST['comment']}";

$master->db->rawQuery(
    "UPDATE reports
        SET Status = 'Resolved',
            ResolvedTime = ?,
            ResolverID = ?,
            Comment = CONCAT_WS(CHAR(10 using utf8), Comment, ?)
      WHERE ID = ?",
    [sqltime(), $activeUser['ID'], $Comment, $ReportID]
);

if ($ConvID && $ConvID>0) {
    $master->db->rawQuery(
        "UPDATE staff_pm_conversations
            SET Status = 'Resolved',
                ResolverID = ?
          WHERE ID = ?",
        [$activeUser['ID'], $ConvID]
    );
    $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);
    $master->cache->deleteValue('num_staff_pms_'.$activeUser['ID']);
}

$Remaining = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM reports
      WHERE Status = 'New'"
)->fetchColumn();

$master->irker->announceAdmin("Report {$ReportID} resolved by {$activeUser['Username']} (".(int) $Remaining." remaining).");

$master->cache->deleteValue('num_update_reports');
$master->cache->deleteValue('num_forum_reports');
$master->cache->deleteValue('num_other_reports');

header('Location: reports.php');
