<?php
authorize();

/*********************************************************************\
//--------------mod unlock-------------------------------------------//

The page that handles the backend of the 'unlock post' function.

$_GET['action'] must be "unlock" for this page to work.

It will be accompanied with:
    $_POST['post'] - the ID of the post


'mod unlock' works by creating a new duplicate of the last edit and setting the author
of the new edit to be the post's original author
\*********************************************************************/

if(!check_perms('site_moderate_forums')) {
    error('You lack the permission to unlock this post.',true);
}


$Text = new Luminance\Legacy\Text;

// Quick SQL injection check
if (!is_number($_POST['post'])) {
    error(0,true);
}
// End injection check

$postID = $_POST['post'];

$forumpost = $master->db->raw_query("SELECT p.Body, p.AuthorID, u.UserName, p.TopicID, CEIL((SELECT COUNT(ID) FROM forums_posts
                                                                                        WHERE forums_posts.TopicID = p.TopicID
                                                                                        AND forums_posts.ID <= :postid )/ :postsperpage) AS Page
                                  FROM forums_posts as p
                                  JOIN users_main AS u ON p.AuthorID = u.ID
                                 WHERE p.ID=:postid2",
                                       [':postid'       => $postID,
                                        ':postsperpage' => POSTS_PER_PAGE,
                                        ':postid2'      => $postID])->fetch(\PDO::FETCH_ASSOC);

if (!$forumpost) error(404, true);


$preview = $Text->full_format($forumpost['Body'],  get_permissions_advtags($forumpost['AuthorID']), true);


    // Perform the update
$master->db->raw_query("UPDATE forums_posts
                           SET EditedUserID = :authorid,
                               EditedTime   = :sqltime
                         WHERE ID           = :postid",
                               [':authorid' => $forumpost['AuthorID'],
                                ':sqltime'  => sqltime(),
                                ':postid'   => $postID]);

$CatalogueID = floor((POSTS_PER_PAGE*$forumpost['Page']-POSTS_PER_PAGE)/THREAD_CATALOGUE);
$master->cache->delete('thread_'.$forumpost['TopicID'].'_catalogue_'.$CatalogueID);
$master->cache->delete('thread_'.$forumpost['TopicID'].'_info');

$master->db->raw_query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                             VALUES ('forums', :postid, :authorid, :sqltime, :body)",
                             [':postid'   => $postID,
                              ':authorid' => $forumpost['AuthorID'],
                              ':sqltime'  => sqltime(),
                              ':body'     => $forumpost['Body']]);

$master->cache->delete_value("forums_edits_$postID");

?>
<div class="post_content">
    <?=$preview; ?>
</div>
<div class="post_footer">
    <a href="#content<?=$postID?>" onclick="LoadEdit('forums', <?=$postID?>, 1); return false;">&laquo;</a>
    <span class="editedby">Last edited by <a href="/user.php?id=<?=$forumpost['AuthorID']?>"><?=$forumpost['UserName']?></a> just now</span>
<?php       if (check_perms('site_admin_forums')) { ?>
    &nbsp;&nbsp;<a href="#content<?=$postID?>" onclick="RevertEdit(<?=$postID?>); return false;" title="remove last edit">&reg;</a>
<?php       } ?>
</div>
