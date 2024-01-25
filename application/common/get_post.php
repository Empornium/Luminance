<?php
//TODO: make this use the cache version of the thread, save the db query
/*********************************************************************\
//--------------Get Post--------------------------------------------//

This gets the raw BBCode of a post. It's used for editing and
quoting posts.

It gets called if $_GET['action'] == 'get_post'. It requires
$_GET['post'], which is the ID of the post.

\*********************************************************************/

// Quick SQL injection check
if (!$_GET['post'] || !is_integer_string($_GET['post'])) {
    error(0, true);
}

// Variables for database input
$PostID = (int) $_GET['post'];

if (!$master->request->user) error(403);

// Mainly
switch ($_REQUEST['section']) {
    case 'requests':
        // Request comments have no restrictions
        $Body = $master->db->rawQuery(
            "SELECT Body
               FROM requests_comments
              WHERE ID=?",
            [$PostID]
        )->fetchColumn();
        break;

    case 'torrents':
        // Torrent comments have no restrictions
        $Body = $master->db->rawQuery(
            "SELECT Body
               FROM torrents_comments
              WHERE ID=?",
            [$PostID]
        )->fetchColumn();
        break;

    case 'staffpm':
        list($Body, $TargetUserID, $Level, $AssignedToUser) = $master->db->rawQuery(
            "SELECT m.Message,
                    c.UserID,
                    c.Level,
                    c.AssignedToUser
               FROM staff_pm_conversations AS c
               JOIN staff_pm_messages AS m ON m.ConvID=c.ID
              WHERE m.ID = ?",
            [$PostID]
        )->fetch(\PDO::FETCH_NUM);

        $IsStaff = check_perms('site_staff_inbox');

        if ($TargetUserID != $activeUser['ID'] && $AssignedToUser != $activeUser['ID'] && ($Level > $activeUser['Class'] || !$IsStaff)) {
            // User is trying to view someone else's conversation
            error(403, true);
        }

        break;
    default:
        error(0, true);
        break;
}


$Text = new Luminance\Legacy\Text;

$Body = $Text->clean_bbcode($Body, get_permissions_advtags($activeUser['ID']));

// This gets sent to the browser, which echoes it wherever

if (isset($_REQUEST['body']) && $_REQUEST['body']==1) {
    echo trim($Body);
} else {
    $Text->display_bbcode_assistant("editbox$PostID", get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']));
    ?>
        <textarea id="editbox<?=$PostID?>" class="long" onkeyup="resize('editbox<?=$PostID?>');" name="body" rows="10"><?=display_str($Body)?></textarea>
    <?php
}
