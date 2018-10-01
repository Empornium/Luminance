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

header('Content-Type: application/json; charset=utf-8');

$Text = new Luminance\Legacy\Text;

// Quick SQL injection check
if (!$_POST['post'] || !is_number($_POST['post'])) {
    error(0, true);
}
// End injection check


$master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::POST);

// Variables for database input
$UserID = $LoggedUser['ID'];
$Body   = $_POST['body'];
$PostID = $_POST['post'];

// get current post info
$postinfo = $master->db->raw_query("SELECT
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
                                        AND forums_posts.ID <= :postid )/".POSTS_PER_PAGE.")
                                        AS Page
                                    FROM forums_posts as p
                                    JOIN forums_topics as t on p.TopicID = t.ID
                                    JOIN forums as f ON t.ForumID=f.ID
                                    WHERE p.ID=:postid2",
                                    [':postid' => $PostID,
                                     ':postid2'=> $PostID])->fetch(\PDO::FETCH_NUM);

if (!isset($postinfo[0])) error(404, true);

list($OldBody, $AuthorID, $TopicID, $AddedTime, $IsLocked, $ForumID, $StickyPostID, $MinClassWrite, $EditedTime, $EditedUserID, $TimeLock, $Page) = $postinfo;

// Make sure they aren't trying to edit posts they shouldn't
if (!check_forumperm($ForumID, 'Write') || ($IsLocked && !check_perms('site_moderate_forums'))) {
    error('Either the thread is locked, or you lack the permission to edit this post.',true);
}

validate_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $TimeLock);


$preview = $Text->full_format($_POST['body'],  get_permissions_advtags($AuthorID), true);
if ($Text->has_errors()) {
    $result = 'error';
    $bbErrors = implode('<br/>', $Text->get_errors());
    $preview = ("<strong>NOTE: Changes were not saved.</strong><br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>$bbErrors<br/><div class=\"box\"><div class=\"post_content\">$preview</div></div>");
}

if (!$bbErrors) {
    // Perform the update
    $master->db->raw_query("UPDATE forums_posts
                               SET Body         = :body,
                                   EditedUserID = :userid,
                                   EditedTime   = :sqltime
                             WHERE ID = :postid",
                                   [':body'    => $Body,
                                    ':userid'  => $UserID,
                                    ':sqltime' => sqltime(),
                                    ':postid'  => $PostID]);

    if ($PostID == $StickyPostID) {
        $master->cache->delete('thread_'.$TopicID.'_info');

    } else {
        $CatalogueID = floor((POSTS_PER_PAGE*$Page-POSTS_PER_PAGE)/THREAD_CATALOGUE);
        $master->cache->delete('thread_'.$TopicID.'_catalogue_'.$CatalogueID);

    }

    $master->db->raw_query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                VALUES ('forums', :postid, :userid, :sqltime, :body)",
                                      [':postid'  => $PostID,
                                       ':userid'  => $UserID,
                                       ':sqltime' => sqltime(),
                                       ':body'    => $OldBody]);
    $master->cache->delete_value("forums_edits_$PostID");
    $result = 'saved';
}
ob_start();
?>
<div class="post_content">
    <?=$preview; ?>
</div>
<?php
    if ($result=='saved') {
?>
<div class="post_footer">
<?php
        if (check_perms('site_moderate_forums')) { ?>
    <a href="#content<?=$PostID?>" onclick="LoadEdit('forums', <?=$PostID?>, 1); return false;">&laquo;</a>
<?php
        }
?>
    <span class="editedby">Last edited by <a href="/user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> just now</span>
<?php
        if (check_perms('site_admin_forums')) { ?>
    &nbsp;&nbsp;<a href="#content<?=$PostID?>" onclick="RevertEdit(<?=$PostID?>); return false;" title="remove last edit">&reg;</a>
<?php
        }
?>
</div>
<?php
    }

$html = ob_get_contents();
ob_end_clean();

echo json_encode(array($result, $html));
