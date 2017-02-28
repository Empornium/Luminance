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
if (!$_GET['post'] || !is_number($_GET['post'])) {
    error(0);
}

// Variables for database input
$PostID = (int) $_GET['post'];

// Mainly
switch ($_REQUEST['section']) {
    case 'forums' :
        $DB->query("SELECT
            p.Body, t.ForumID
            FROM forums_posts as p JOIN forums_topics as t on p.TopicID = t.ID
            WHERE p.ID='$PostID'");
        list($Body, $ForumID) = $DB->next_record(MYSQLI_NUM);

        // Is the user allowed to view the post?
        include_once(SERVER_ROOT.'/sections/forums/functions.php'); // Forum functions
        if (!check_forumperm($ForumID)) {
            error(0);
        }
        break;

    case 'collages' :
        $DB->query("SELECT
            Body
            FROM collages_comments
            WHERE ID='$PostID'");
        list($Body) = $DB->next_record(MYSQLI_NUM);
        break;

    case 'requests' :
        $DB->query("SELECT
            Body
            FROM requests_comments
            WHERE ID='$PostID'");
        list($Body) = $DB->next_record(MYSQLI_NUM);
        break;

    case 'comments' :
        $DB->query("SELECT
            Body
            FROM torrents_comments
            WHERE ID='$PostID'");
        list($Body) = $DB->next_record(MYSQLI_NUM);
        break;

    case 'pm' :
        // Message is selected providing the user quoting is one of the two people in the thread
        $DB->query("SELECT
            m.Body
            FROM pm_messages as m
            JOIN pm_conversations_users AS u ON m.ConvID=u.ConvID
            WHERE m.ID='$PostID'
            AND u.UserID=".$LoggedUser['ID']);
        list($Body) = $DB->next_record(MYSQLI_NUM);
        break;

    case 'staffpm' :
        // Maybe we should add some safety checks?
        $DB->query("SELECT
            Message
            FROM staff_pm_messages
            WHERE ID='$PostID'");
        list($Body) = $DB->next_record(MYSQLI_NUM);
        break;
    default :
        error($_REQUEST['section']);
        break;
}


include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
$Body = $Text->clean_bbcode($Body, get_permissions_advtags($LoggedUser['ID']));

// This gets sent to the browser, which echoes it wherever

if (isset($_REQUEST['body']) && $_REQUEST['body']==1) {
    echo trim($Body);
} else {
    $Text->display_bbcode_assistant("editbox$PostID", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']));
?>
    <textarea id="editbox<?=$PostID?>" class="long" onkeyup="resize('editbox<?=$PostID?>');" name="body" rows="10"><?=display_str($Body)?></textarea>
<?php
}

