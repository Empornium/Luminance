<?php
authorize();

/*********************************************************************\
//--------------revert edit------------------------------------------//

The page that handles the backend of the 'revert edit' function.

$_GET['action'] must be "revert" for this page to work.

It will be accompanied with:
    $_POST['post'] - the ID of the post


'revert edit' works by deleting the last edit - ie. replace current post body
with the prev edit content
\*********************************************************************/

if (!check_perms('torrent_review')) {
    error('You lack the permission to revert an edit.',true);
}


$bbCode = new \Luminance\Legacy\Text;

// Quick SQL injection check
if (!is_integer_string($_POST['post'])) {
    error(0,true);
}
// End injection check

// Variables for database input
$groupID = $_POST['post'];

$description = $master->db->rawQuery(
    "SELECT Body
       FROM torrents_group
      WHERE ID = ?",
    [$groupID]
)->fetchColumn();

if (!$description) error(404, true);

$edits = $master->cache->getValue("descriptions_edits_{$groupID}");
if (!is_array($edits)) {
    $edits = $master->db->rawQuery(
        "SELECT ce.EditUser,
                u.Username,
                ce.EditTime,
                ce.Body
           FROM comments_edits AS ce
           JOIN users AS u ON u.ID = ce.EditUser
          WHERE PostID = ?
            AND Page = 'descriptions'
       ORDER BY ce.EditTime DESC",
        [$groupID]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $master->cache->cacheValue("descriptions_edits_{$groupID}", $edits, 0);
}


if (count($edits) == 0) {
    // nothing to revert to
    error(404, true);
} else if (count($edits) == 1) {
    // removing the only edit so revert to original post
    $editUserID   = null;
    $editTime     = null;
} else {
    // get info for (what will be) the new last edit
    $editUserID   = $edits[1]['EditUser'];
    $editTime     = $edits[1]['EditTime'];
}

$preview = $bbCode->full_format($edits[0]['Body'],  get_permissions_advtags($edits[0]['EditUser']), true);


// delete the last added edit
$master->db->rawQuery(
    "DELETE
       FROM comments_edits
      WHERE PostID = ?
        AND Page = 'descriptions'
   ORDER BY EditTime DESC
      LIMIT 1",
    [$groupID]
);

$master->db->rawQuery(
    "UPDATE torrents_group
        SET Body = ?,
            EditedUserID = ?,
            EditedTime = ?
      WHERE ID = ?",
    [$edits[0]['Body'], $editUserID, $editTime, $groupID]
);

$master->cache->deleteValue("descriptions_edits_{$groupID}");
$master->cache->deleteValue("torrents_details_{$groupID}");
$master->repos->torrentgroups->uncache($groupID);

update_hash($groupID);

?>
<div class="post_content">
    <?=$preview; ?>
</div>
<div class="post_footer">
<?php if (count($edits)>1) { ?>
    <a href="#content<?=$groupID?>" onclick="LoadEdit(<?=$groupID?>, 1); return false;">&laquo;</a>
    <span class="editedby"><?=((count($edits)>2) ? 'Last edited by' : 'Edited by')?> <?=format_username($editUserID) ?> <?=time_diff($editTime,2,true,true)?></span>
    &nbsp;&nbsp;<a href="#content<?=$groupID?>" onclick="RevertEdit(<?=$groupID?>); return false;" title="remove last edit">&reg;</a>
<?php } else { ?>
    <em>Original Post</em>
<?php }        ?>
</div>
