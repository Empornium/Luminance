<?php

$groupID = $_GET['groupid'];
if (!is_integer_string($groupID) || !$groupID) {
    error(0);
}

$SQL = "SELECT tg.NewCategoryID AS Category,
               tg.Name AS Title,
               REPLACE(tg.TagList, '_', '.') AS TagList,
               tg.Image AS Image,
               tg.Body AS GroupDescription,
               t.Anonymous AS Anonymous
          FROM torrents_group AS tg
          JOIN torrents AS t ON t.GroupID=tg.ID
         WHERE tg.ID = ?";
$queryParams = [$groupID];

# Staff can see anything, users should only see their own.
if (!check_perms('torrent_edit')) {
    $SQL .= " AND t.UserID = ?";
    $queryParams[] = $activeUser['ID'];
}

$Properties = $master->db->rawQuery($SQL, $queryParams)->fetch(\PDO::FETCH_ASSOC);

include(SERVER_ROOT.'/Legacy/sections/upload/upload.php');
