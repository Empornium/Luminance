<?php
header('Content-Type: application/json; charset=utf-8');

// Make sure they are moderators
if (!check_perms('site_moderate_forums')) { error(403,true); }
authorize();

// Quick SQL injection check
if (!is_number($_POST['threadid'])) { error(404,true); }
// End injection check

// Variables for database input

$TopicID = (int) $_POST['threadid'];

$DB->query("SELECT
    t.ForumID,
          t.Title,
    f.MinClassWrite,
    COUNT(p.ID) AS Posts,
          Max(p.ID) AS LastPostID,
            t.StickyPostID
    FROM forums_topics AS t
    LEFT JOIN forums_posts AS p ON p.TopicID=t.ID
    LEFT JOIN forums AS f ON f.ID=.t.ForumID
    WHERE t.ID='$TopicID'
    GROUP BY p.TopicID");
if ($DB->record_count()==0) error("Error: Could not find thread with id=$TopicID",true);
list($OldForumID, $OldTitle, $MinClassWrite, $Posts, $OldLastPostID, $OldStickyPostID) = $DB->next_record();

if ( !check_forumperm($OldForumID, 'Write') ) { error(403,true); }

// If we're moving
$Cache->delete_value('forums_'.$OldForumID);

$sqltime = sqltime();

$ForumID = TRASH_FORUM_ID;

$PostID = $_POST['postid'];

if (!is_number($PostID)) error("No post selected to trash",true);

$NumSplitPosts=1;
if ( $NumSplitPosts>=$Posts) error("You cannot split ALL the posts from a thread",true);

if ($OldStickyPostID == $PostID) $OldStickyPostID=0;


$DB->query("SELECT AuthorID, AddedTime FROM forums_posts WHERE ID='$PostID'");
list($FirstAuthorID, $FirstAddedTime) = $DB->next_record();

$Title = "Trashed Post - from \"$OldTitle\"";

$DB->query("INSERT INTO forums_topics
              (Title, AuthorID, ForumID, LastPostID, LastPostTime, LastPostAuthorID, NumPosts)
              Values
              ('".db_string($Title)."', '$FirstAuthorID', '$ForumID', '$PostID', '$sqltime', '$FirstAuthorID','".($NumSplitPosts+1)."')");
$SplitTopicID = $DB->inserted_id();
$extra = "moved to";
$numtopics = '+1';

$SystemPost = "[quote=the system]$NumSplitPosts post $extra this thread from [url=/forums.php?action=viewthread&threadid=$TopicID]\"$OldTitle\"[/url][/quote]";
$SystemPost .= "[b]$LoggedUser[Username] trashed this post ($sqltime) because:[/b][br][br]{$_POST[comment]}";

$DB->query("INSERT INTO forums_posts (TopicID, AuthorID, AddedTime, Body)
                    VALUES ('$SplitTopicID', '$LoggedUser[ID]', '".sqltime(strtotime($FirstAddedTime)-10)."', '".db_string($SystemPost)."')");
$PrePostID = $DB->inserted_id();

if ($OldLastPostID == $PostID) {

    $DB->query("SELECT MAX(ID) FROM forums_posts WHERE ID!='$PostID' AND TopicID='$TopicID'");
    list($LastID) = $DB->next_record();

    $DB->query("SELECT p.AuthorID, u.Username, p.AddedTime FROM forums_posts AS p LEFT JOIN users_main AS u ON u.ID = p.AuthorID WHERE p.ID='$LastID'");
    list($LastAuthorID, $LastAuthorName, $LastTime) = $DB->next_record();

    $SET_LASTPOST_INFO = "LastPostID='$LastID',
                          LastPostAuthorID='$LastAuthorID',
                          LastPostTime='$LastTime', ";
}

$DB->query("UPDATE forums_topics SET $SET_LASTPOST_INFO
                                         StickyPostID = '$OldStickyPostID',
                                         NumPosts=(NumPosts-$NumSplitPosts) WHERE ID='$TopicID'");

$DB->query("DELETE FROM forums_last_read_topics WHERE TopicID='$TopicID'");

// move the selected posts
$DB->query("UPDATE forums_posts SET TopicID='$SplitTopicID', Body=CONCAT_WS( '\n\n', Body, '[align=right][size=0][i]split from thread[/i][br]\'$OldTitle\'[/size][/align]') WHERE TopicID='$TopicID' AND ID='$PostID'");

$Cache->begin_transaction('forums_list');

update_forum_info($ForumID, $numtopics, false);

if ($OldForumID!=$ForumID) {    // If we're moving posts into a new forum, change the new forum stats

    update_forum_info($OldForumID, 0,false);
    $Cache->delete_value('forums_'.$OldForumID);
}

$Cache->commit_transaction(0);
$Cache->delete_value('thread_'.$TopicID.'_info');
$Cache->delete_value('thread_'.$SplitTopicID.'_info');

$CatalogueID = floor($Posts/THREAD_CATALOGUE);
for ($i=0;$i<=$CatalogueID;$i++) {
    $Cache->delete_value('thread_'.$TopicID.'_catalogue_'.$i);
    $Cache->delete_value('thread_'.$SplitTopicID.'_catalogue_'.$i);
}

echo json_encode(array(true));
