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

if(!check_perms('site_admin_forums')) {
    error('You lack the permission to revert an edit.',true);
}


$Text = new Luminance\Legacy\Text;

// Quick SQL injection check
if (!is_number($_POST['post'])) {
    error(0,true);
}
// End injection check

// Variables for database input
$postID = $_POST['post'];


$forumpost = $master->db->raw_query("SELECT p.AuthorID, u.Username, p.TopicID, CEIL((SELECT COUNT(ID) FROM forums_posts
                                                                                        WHERE forums_posts.TopicID = p.TopicID
                                                                                        AND forums_posts.ID <= :postid )/ :postsperpage) AS Page
                                  FROM forums_posts as p
                                  JOIN users_main AS u ON p.AuthorID = u.ID
                                 WHERE p.ID=:postid2",
                                       [':postid'       => $postID,
                                        ':postsperpage' => POSTS_PER_PAGE,
                                        ':postid2'      => $postID])->fetch(\PDO::FETCH_ASSOC);

if (!$forumpost) error(404, true);

$edits = $master->cache->get_value('forums_edits_'.$postID);
if (!is_array($edits)) {
    $edits = $master->db->raw_query("SELECT ce.EditUser, um.Username, ce.EditTime, ce.Body
                                       FROM comments_edits AS ce
                                       JOIN users_main AS um ON um.ID=ce.EditUser
                                      WHERE PostID = :postid
                                        AND Page = 'forums'
                                   ORDER BY ce.EditTime DESC",
                                            [':postid' => $postID])->fetchAll(\PDO::FETCH_ASSOC);

    $master->cache->cache_value('forums_edits_'.$postID, $edits, 0);
}


if (count($edits)==0) {
    // nothing to revert to
    error(404, true);
} else if (count($edits)==1) {
    // removing the only edit so revert to original post
    $editUserID   = 0;
    $editTime     = '0000-00-00 00:00:00';
} else {
    // get info for (what will be) the new last edit
    $editUserID   = $edits[1]['EditUser'];
    $editTime     = $edits[1]['EditTime'];
}

$preview = $Text->full_format($edits[0]['Body'],  get_permissions_advtags($edits[0]['EditUser']), true);


// delete the last added edit
$master->db->raw_query("DELETE FROM comments_edits
                         WHERE PostID = :postid AND Page = 'forums'
                      ORDER BY EditTime DESC
                         LIMIT 1",
                      [':postid' => $postID]);

$master->db->raw_query("UPDATE forums_posts
                           SET Body         = :body,
                               EditedUserID = :authorid,
                               EditedTime   = :sqltime
                         WHERE ID           = :postid",
                               [':body'     => $edits[0]['Body'],
                                ':authorid' => $editUserID,
                                ':sqltime'  => $editTime,
                                ':postid'   => $postID]);


$CatalogueID = floor((POSTS_PER_PAGE*$forumpost['Page']-POSTS_PER_PAGE)/THREAD_CATALOGUE);
$master->cache->delete_value('thread_'.$forumpost['TopicID'].'_catalogue_'.$CatalogueID);
$master->cache->delete_value('thread_'.$forumpost['TopicID'].'_info');

$master->cache->delete_value("forums_edits_$postID");

?>
<div class="post_content">
    <?=$preview; ?>
</div>
<div class="post_footer">
<?php if (count($edits)>1) { ?>
    <a href="#content<?=$postID?>" onclick="LoadEdit('forums', <?=$postID?>, 1); return false;">&laquo;</a>
    <span class="editedby"><?=((count($edits)>2) ? 'Last edited by' : 'Edited by')?> <?=format_username($editUserID, $edits[1]['Username']) ?> <?=time_diff($editTime,2,true,true)?></span>
    &nbsp;&nbsp;<a href="#content<?=$postID?>" onclick="RevertEdit(<?=$postID?>); return false;" title="remove last edit">&reg;</a>
<?php } else { ?>
    <em>Original Post</em>
<?php }        ?>
</div>
