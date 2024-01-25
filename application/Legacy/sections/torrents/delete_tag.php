<?php
header('Content-Type: application/json; charset=utf-8');


$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::TAGGING);

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

$TagID = $_POST['tagid'];
$GroupID = $_POST['groupid'];

if (!is_integer_string($TagID) || !is_integer_string($GroupID)) {
    error(0, true);
}

list($TagName, $TagType) = $master->db->rawQuery(
    "SELECT Name,
            TagType
       FROM tags
      WHERE ID = ?",
    [$TagID]
)->fetch(\PDO::FETCH_NUM);
if (!$TagName) error(0, true);

if (!check_perms('site_delete_tag')) {
    //only need to check this if not already permitted
    list($AuthorID, $OwnerID) = $master->db->rawQuery(
        "SELECT t.UserID,
                tt.UserID
           FROM torrents AS t
      LEFT JOIN torrents_tags AS tt
             ON t.GroupID = tt.GroupID
            AND tt.TagID = ?
          WHERE t.GroupID = ?",
        [$TagID, $GroupID]
    )->fetch(\PDO::FETCH_NUM);
    // must be both torrent owner and tag owner to delete
    if ($AuthorID!=$OwnerID || $AuthorID!=$activeUser['ID']) error(403, true);
}

$master->db->rawQuery(
    "INSERT INTO group_log (GroupID, UserID, Time, Info)
          VALUES (?, ?, ?, ?)",
    [$GroupID, $activeUser['ID'], sqltime(), "Tag {$TagName} removed from group"]
);

$master->db->rawQuery(
    "DELETE
       FROM torrents_tags_votes
      WHERE GroupID = ?
        AND TagID = ?",
    [$GroupID, $TagID]
);

$master->db->rawQuery(
    "DELETE
       FROM torrents_tags
      WHERE GroupID = ?
        AND TagID = ?",
    [$GroupID, $TagID]
);

$master->cache->deleteValue('torrents_details_'.$GroupID); // Delete torrent group cache
update_hash($GroupID);

// Decrease the tag count, if it's not in use any longer and not an official tag, delete it from the list.
$count = $master->db->rawQuery(
    "SELECT COUNT(GroupID)
       FROM torrents_tags
      WHERE TagID = ?",
    [$TagID]
)->fetchColumn();

if ($TagType == 'genre' || $count > 0) {
    $count = $count > 0 ? $count : 0;
    $master->db->rawQuery(
        "UPDATE tags
            SET Uses = Uses - 1
          WHERE ID = ?",
        [$TagID]
    );
} else {
    $master->db->rawQuery(
        "DELETE
           FROM tags
          WHERE ID = ?
            AND TagType='other'",
        [$TagID]
    );

    // Delete tag cache entry
    $master->cache->deleteValue('tag_id_'.$TagName);
}

$Result = [1, "Deleted tag $TagName"];
echo json_encode([[$Result], get_taglist_html($GroupID, $_POST['tagsort'])]);
