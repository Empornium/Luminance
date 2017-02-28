<?php
authorize();

/*********************************************************************\
//--------------Take Post--------------------------------------------//

The page that handles the backend of the 'edit post' function.

$_GET['action'] must be "takeedit" for this page to work.

It will be accompanied with:
    $_POST['post'] - the ID of the post
    $_POST['body']

\*********************************************************************/

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

$Text = new TEXT;

// Quick SQL injection check
if (!$_POST['post'] || !is_number($_POST['post']) || !is_number($_POST['key'])) {
    error(0,true);
}
// End injection check

// Variables for database input
$UserID = $LoggedUser['ID'];
$Body = $_POST['body']; //Don't URL Decode
$PostID = $_POST['post'];
$Key = $_POST['key'];

// Mainly
$DB->query("SELECT
        p.Body,
        p.AuthorID,
        p.TopicID,
        p.AddedTime,
        t.IsLocked,
        t.ForumID,
        t.StickyPostID,
        f.MinClassWrite,
        p.EditedTime,
        p.EditedUserID,
        p.TimeLock,
        CEIL((SELECT COUNT(ID)
            FROM forums_posts
            WHERE forums_posts.TopicID = p.TopicID
            AND forums_posts.ID <= '$PostID')/".POSTS_PER_PAGE.")
            AS Page
        FROM forums_posts as p
        JOIN forums_topics as t on p.TopicID = t.ID
        JOIN forums as f ON t.ForumID=f.ID
        WHERE p.ID='$PostID'");
list($OldBody, $AuthorID, $TopicID, $AddedTime, $IsLocked, $ForumID, $StickyPostID, $MinClassWrite, $EditedTime, $EditedUserID, $TimeLock, $Page) = $DB->next_record();

// Make sure they aren't trying to edit posts they shouldn't
// We use die() here instead of error() because whatever we spit out is displayed to the user in the box where his forum post is
if (!check_forumperm($ForumID, 'Write') || ($IsLocked && !check_perms('site_moderate_forums'))) {
    error('Either the thread is locked, or you lack the permission to edit this post.',true);
}

validate_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $TimeLock);

if ($LoggedUser['DisablePosting']) {
    error('Your posting rights have been removed.',true);
}
if ($DB->record_count()==0) {
    error(404,true);
}

$preview = $Text->full_format($_POST['body'],  get_permissions_advtags($AuthorID), true);
if ($Text->has_errors()) {
    $bbErrors = implode('<br/>', $Text->get_errors());
    $preview = ("<strong>NOTE: Changes were not saved.</strong><br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>$bbErrors<br/><div class=\"box\"><div class=\"post_content\">$preview</div></div>");
}

if (!$bbErrors) {
    // Perform the update
    $DB->query("UPDATE forums_posts SET
          Body = '".db_string($Body)."',
          EditedUserID = '$UserID',
          EditedTime = '".sqltime()."'
          WHERE ID='$PostID'");



    if ($PostID == $StickyPostID) {
        $Cache->delete('thread_'.$TopicID.'_info');
/*        $Cache->begin_transaction('thread_info_'.$TopicID);
        $Key = 'StickyPost';
        $Cache->update_row($Key, array(
                      'ID'=>$Cache->MemcacheDBArray[$Key]['ID'],
                      'AuthorID'=>$Cache->MemcacheDBArray[$Key]['AuthorID'],
                      'AddedTime'=>$Cache->MemcacheDBArray[$Key]['AddedTime'],
                      'Body'=>$_POST['body'], //Don't url decode.
                      'EditedUserID'=>$LoggedUser['ID'],
                      'EditedTime'=>sqltime(),
                      'Username'=>$LoggedUser['Username']
        ));
        $Cache->commit_transaction(3600*24*5);
*/
    } else {
        $CatalogueID = floor((POSTS_PER_PAGE*$Page-POSTS_PER_PAGE)/THREAD_CATALOGUE);
        $Cache->delete('thread_'.$TopicID.'_catalogue_'.$CatalogueID);

/*        $Cache->begin_transaction('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
        if ($Cache->MemcacheDBArray[$Key]['ID'] != $PostID) {
              $Cache->cancel_transaction();
              $Cache->delete('thread_'.$TopicID.'_catalogue_'.$CatalogueID); //just clear the cache for would be cache-screwer-uppers
        } else {
              $Cache->update_row($Key, array(
                          'ID'=>$Cache->MemcacheDBArray[$Key]['ID'],
                          'AuthorID'=>$Cache->MemcacheDBArray[$Key]['AuthorID'],
                          'AddedTime'=>$Cache->MemcacheDBArray[$Key]['AddedTime'],
                          'Body'=>$_POST['body'], //Don't url decode.
                          'EditedUserID'=>$LoggedUser['ID'],
                          'EditedTime'=>sqltime(),
                          'Username'=>$LoggedUser['Username']
                          ));
              $Cache->commit_transaction(3600*24*5);
        }
*/
    }

    $DB->query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                                    VALUES ('forums', ".$PostID.", ".$UserID.", '".sqltime()."', '".db_string($OldBody)."')");
    $Cache->delete_value("forums_edits_$PostID");
}

?>

<div class="post_content">
    <?=$preview; ?>
</div>
<div class="post_footer">
    <span class="editedby">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> just now</span>
</div>
