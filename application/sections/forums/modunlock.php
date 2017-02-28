<?php
authorize();

/*********************************************************************\
//--------------Take Post--------------------------------------------//

The page that handles the backend of the 'unlock post' function.

$_GET['action'] must be "unlock" for this page to work.

It will be accompanied with:
    $_POST['post'] - the ID of the post

\*********************************************************************/

if(!check_perms('site_moderate_forums')) {
    error('You lack the permission to unlock this post.',true);
}

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

$Text = new TEXT;

// Quick SQL injection check
if (!is_number($_POST['post'])) {
    error(0,true);
}
// End injection check

// Variables for database input
$PostID = $_POST['post'];

// Mainly
$DB->query("SELECT
        p.Body,
        p.AuthorID,
        u.UserName,
        p.TopicID,
        CEIL((SELECT COUNT(ID)
            FROM forums_posts
            WHERE forums_posts.TopicID = p.TopicID
            AND forums_posts.ID <= '$PostID')/".POSTS_PER_PAGE.")
            AS Page
        FROM forums_posts as p
        JOIN users_main AS u ON p.AuthorID = u.ID
        WHERE p.ID='$PostID'");
list($Body, $AuthorID, $AuthorName, $TopicID, $Page) = $DB->next_record();

if ($DB->record_count()==0) {
    error(404,true);
}

$preview = $Text->full_format($Body,  get_permissions_advtags($AuthorID), true);

    // Perform the update
    $DB->query("UPDATE forums_posts SET
          EditedUserID = '$AuthorID',
          EditedTime = '".sqltime()."'
          WHERE ID='$PostID'");

    $CatalogueID = floor((POSTS_PER_PAGE*$Page-POSTS_PER_PAGE)/THREAD_CATALOGUE);
    $Cache->delete('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
    $Cache->delete('thread_'.$TopicID.'_info');

    $DB->query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                                    VALUES ('forums', ".$PostID.", ".$AuthorID.", '".sqltime()."', '".db_string($Body)."')");
    $Cache->delete_value("forums_edits_$PostID");

?>

<div class="post_content">
    <?=$preview; ?>
</div>
<div class="post_footer">
    <span class="editedby">Last edited by <a href="user.php?id=<?=$AuthorID?>"><?=$AuthorName?></a> just now</span>
</div>
