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

// Quick SQL injection check
if (!is_number($_POST['post'])) {
    error(0,true);
}
// End injection check

// Variables for database input
$PostID = $_POST['post'];

// Mainly
// Perform the update
$DB->query("UPDATE forums_posts SET
      TimeLock = NOT(TimeLock)
      WHERE ID='$PostID'");

$DB->query("SELECT
        p.TimeLock,
        p.TopicID,
        CEIL((SELECT COUNT(ID)
            FROM forums_posts
            WHERE forums_posts.TopicID = p.TopicID
            AND forums_posts.ID <= '$PostID')/".POSTS_PER_PAGE.")
            AS Page
        FROM forums_posts as p
        WHERE p.ID='$PostID'");
list($TimeLock, $TopicID, $Page) = $DB->next_record();

$CatalogueID = floor((POSTS_PER_PAGE*$Page-POSTS_PER_PAGE)/THREAD_CATALOGUE);
$Cache->delete('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
$Cache->delete('thread_'.$TopicID.'_info');

//$UnlockSymbol = $TimeLock ? "&#x1f550;" : "<strike>&#x1f550;</strike>";
$UnlockSymbol = $TimeLock ? "T" : "<strike>T</strike>";
echo "[$UnlockSymbol]";
