<?php
authorize();

if (!can_bookmark($_GET['type'])) { error(404); }

$Type = $_GET['type'];

list($Table, $Col) = bookmark_schema($Type);

if (!is_integer_string($_GET['id'])) {
    error(0);
}

$master->db->rawQuery(
    "DELETE FROM `{$Table}` WHERE UserID = ? AND {$Col} = ?",
    [$activeUser['ID'], $_GET['id']]
);
$master->cache->deleteValue('bookmarks_'.$Type.'_'.$userID);
if ($Type == 'torrent') {
    $master->cache->deleteValue('bookmarks_info_'.$activeUser['ID']);
} elseif ($Type == 'request') {
    $Bookmarkers = $master->db->rawQuery(
        "SELECT UserID
           FROM {$Table}
          WHERE {$Col} = ?",
        [$_GET['id']]
    )->fetchAll(\PDO::FETCH_COLUMN);
    $search->updateAttributes('requests requests_delta', ['bookmarker'], [$_GET['id'] => [$Bookmarkers]], true);
}
