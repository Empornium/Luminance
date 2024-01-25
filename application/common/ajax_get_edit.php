<?php
if (!check_perms('forum_moderate')) {
    error(403, true);
}

if (empty($_GET['postid']) || !is_integer_string($_GET['postid'])) error(0, true);
if (!isset($_GET['depth']) || !is_integer_string($_GET['depth'])) error(0, true);
if (empty($_GET['type']) || !in_array($_GET['type'], ['requests', 'torrents', 'staffpm', 'descriptions'])) error(0, true);

$PostID = $_GET['postid'];
$Depth  = $_GET['depth'];
$Type   = $_GET['type'];

$Edits = $master->cache->getValue("{$Type}_edits_{$PostID}");
if (!is_array($Edits)) {
    $Edits = $master->db->rawQuery(
        "SELECT ce.EditUser,
                u.Username,
                ce.EditTime,
                ce.Body
           FROM comments_edits AS ce
           JOIN users AS u ON u.ID=ce.EditUser
          WHERE PostID = ?
            AND Page   = ?
       ORDER BY ce.EditTime DESC",
        [$PostID, $Type]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $master->cache->cacheValue("{$Type}_edits_{$PostID}", $Edits, 0);
}

if ($Depth != 0) {
    $Body = $Edits[$Depth - 1]['Body'];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
        case 'requests':
        case 'torrents':
            $Body = $master->db->rawQuery(
                "SELECT Body FROM {$Type}_comments WHERE ID = ?",
                [$PostID]
            )->fetchColumn();
            break;

        case 'staffpm':
            $Body = $master->db->rawQuery(
                "SELECT Message FROM staff_pm_messages WHERE ID = ?",
                [$PostID]
            )->fetchColumn();
            break;

        case 'descriptions':
            $Body = $master->db->rawQuery(
                "SELECT Body FROM torrents_group WHERE ID = ?",
                [$PostID]
            )->fetchColumn();
            break;
    }
}
echo $master->render->template(
    '@Legacy/snippets/ajax_get_edit.html.twig',
    [
        'body'   => $Body,
        'depth'  => $Depth,
        'edits'  => $Edits,
        'type'   => $Type,
        'postID' => $PostID,
    ]
);
