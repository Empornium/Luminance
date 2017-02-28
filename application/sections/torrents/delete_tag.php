<?php
header('Content-Type: application/json; charset=utf-8');

if (!empty($LoggedUser['DisableTagging'])) {
    error(403,true);
}

include(SERVER_ROOT . '/sections/torrents/functions.php');

$TagID = db_string($_POST['tagid']);
$GroupID = db_string($_POST['groupid']);

if (!is_number($TagID) || !is_number($GroupID)) {
    error(0, true);
}

$DB->query("SELECT Name, TagType FROM tags WHERE ID='$TagID'");
list($TagName, $TagType) = $DB->next_record();
if (!$TagName) error(0, true);

if (!check_perms('site_delete_tag')) {
    //only need to check this if not already permitted
    $DB->query("SELECT t.UserID, tt.UserID
                  FROM torrents AS t
             LEFT JOIN torrents_tags AS tt
                    ON t.GroupID=tt.GroupID
                   AND tt.TagID='$TagID'
                 WHERE t.GroupID='$GroupID'");
    list($AuthorID,$OwnerID) = $DB->next_record();
    // must be both torrent owner and tag owner to delete
    if ($AuthorID!=$OwnerID || $AuthorID!=$LoggedUser['ID']) error(403, true);
}

$DB->query("INSERT INTO group_log (GroupID, UserID, Time, Info)
                VALUES ('$GroupID',".$LoggedUser['ID'].",'".sqltime()."','".db_string('Tag "'.$TagName.'" removed from group')."')");
$DB->query("DELETE FROM torrents_tags_votes WHERE GroupID='$GroupID' AND TagID='$TagID'");
$DB->query("DELETE FROM torrents_tags WHERE GroupID='$GroupID' AND TagID='$TagID'");

$Cache->delete_value('torrents_details_'.$GroupID); // Delete torrent group cache
update_hash($GroupID);

// Decrease the tag count, if it's not in use any longer and not an official tag, delete it from the list.
$DB->query("SELECT COUNT(GroupID) FROM torrents_tags WHERE TagID=".$TagID);
list($Count) = $DB->next_record();
if ($TagType == 'genre' || $Count > 0) {
    $Count = $Count > 0 ? $Count : 0;
    $DB->query("UPDATE tags SET Uses=$Count WHERE ID=$TagID");
} else {
    $DB->query("DELETE FROM tags WHERE ID=".$TagID." AND TagType='other'");

    // Delete tag cache entry
    $Cache->delete_value('tag_id_'.$TagName);
}

$Result = array(1, "Deleted tag $TagName");
echo json_encode(array(array($Result), get_taglist_html($GroupID, $_POST['tagsort'])));
