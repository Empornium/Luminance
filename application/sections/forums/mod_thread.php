<?php
/*********************************************************************\
//--------------Mod thread-------------------------------------------//

This page gets called if we're editing a thread.

Known issues:
If multiple threads are moved before forum activity occurs then
threads will linger with the 'Moved' flag until they're knocked off
the front page.

\*********************************************************************/
//
// Quick SQL injection check
if (!is_number($_POST['threadid'])) { error(404); }

// End injection check
// Make sure they are moderators
if (!check_perms('site_moderate_forums')) { error(403); }
authorize();

// Variables for database input

$TopicID = (int) $_POST['threadid'];
$Sticky = (isset($_POST['sticky'])) ? 1 : 0;
$Locked = (isset($_POST['locked'])) ? 1 : 0;
$Title =  trim($_POST['title']);
$ForumID = (int) $_POST['forumid'];
$Page = (int) $_POST['page'];

// Why!? This breaks subscriptions and is in general stupid.
//if ($Locked == 1) {
//    $DB->query("DELETE FROM forums_last_read_topics WHERE TopicID='$TopicID'");
//}

$DB->query("SELECT
    t.ForumID,
    t.Title,
    t.IsLocked,
    t.IsSticky,
    f.MinClassWrite,
    COUNT(p.ID) AS Posts,
    t.LastPostID,
    t.LastPostTime,
    t.LastPostAuthorID,
    t.StickyPostID,
    f.Name
    FROM forums_topics AS t
    LEFT JOIN forums_posts AS p ON p.TopicID=t.ID
    LEFT JOIN forums AS f ON f.ID=.t.ForumID
    WHERE t.ID='$TopicID'
    GROUP BY p.TopicID");
if ($DB->record_count()==0) error("Error: Could not find thread with id=$TopicID");
list($OldForumID, $OldTitle, $OldIsLocked, $OldIsSticky, $MinClassWrite, $Posts, $OldLastPostID, $OldLastPostTime, $OldLastPostAuthorID, $OldStickyPostID, $OldForumName) = $DB->next_record();

if ( !check_forumperm($OldForumID, 'Write') ) { error(403); }

// If we're moving
$Cache->delete_value('forums_'.$ForumID);
$Cache->delete_value('forums_'.$OldForumID);

$sqltime = sqltime();

if (isset($_POST['split'])) {
    if(!check_perms('site_moderate_forums')) error(403);

    $PostIDs = $_POST['splitids'];
    $NumSplitPosts =  count($PostIDs);
    if (!is_array($PostIDs) || $NumSplitPosts==0) error("No posts selected to split");
    if ( $NumSplitPosts>=$Posts) error("You cannot split ALL the posts from a thread");
    sort($PostIDs);
    foreach ($PostIDs as $pID) {
        if( !is_number($pID)) error(0);
        // while we are looping these may as well reset the current stickyID (prevents a nasty looking bug - null stickypost!)
        if ($OldStickyPostID == $pID) $OldStickyPostID=0;
    }
    $firstpostID = $PostIDs[0];
    $lastpostID = end($PostIDs);

    $DB->query("SELECT AuthorID, AddedTime FROM forums_posts WHERE ID='$firstpostID'");
    list($FirstAuthorID, $FirstAddedTime) = $DB->next_record();

    $DB->query("SELECT AuthorID, AddedTime FROM forums_posts WHERE ID='$lastpostID'");
    list($LastAuthorID, $LastAddedTime) = $DB->next_record();

    if ($_POST['splitoption'] == 'mergesplit') {
        // merge into an exisiting thread
        if(!is_number($_POST['splitintothreadid'])) error("split into thread id is not a number!");
        $SplitTopicID = (int) $_POST['splitintothreadid'];
        if($SplitTopicID == $TopicID) error("Split failed: split into thread id cannot be the same as source thread!");

        $DB->query("SELECT
              t.ForumID,
              t.Title,
              f.MinClassWrite,
              COUNT(p.ID) AS Posts,
              Max(p.ID) AS LastPostID
              FROM forums_topics AS t
              LEFT JOIN forums_posts AS p ON p.TopicID=t.ID
              LEFT JOIN forums AS f ON f.ID= t.ForumID
              WHERE t.ID='$SplitTopicID'
              GROUP BY p.TopicID");
        if ($DB->record_count()==0) error("Split failed: Could not find thread with id=$SplitTopicID");
        list($ForumID, $MergeTitle, $NFMinClassWrite, $NFPosts, $NFLastPostID) = $DB->next_record();

        if ( !check_forumperm($ForumID, 'Write') ) { error(403); }

		// - remove ever expanding mergetitles; now if mod has set a new title use that else use the merge into thread title - mifune 1/2017
        // $Title = "$MergeTitle (merged with posts from $OldTitle)";
		if ($Title == '') $Title = $MergeTitle;

        $NewLastPostID = ($lastpostID>$NFLastPostID)? $lastpostID : $NFLastPostID;
        $NFPosts += ($NumSplitPosts+1); // 1 extra for system post
        $DB->query("UPDATE forums_topics SET Title='".db_string($Title)."',
                                        LastPostID='$NewLastPostID',
                                  LastPostAuthorID='$LastAuthorID',
                                      LastPostTime='$sqltime',
                                          NumPosts='$NFPosts' WHERE ID='$SplitTopicID'");
        $extra = "merged into";
        $numtopics = 0;

        //$DB->query("DELETE FROM forums_last_read_topics WHERE TopicID='$SplitTopicID'");

    } elseif ($_POST['splitoption'] == 'trashsplit') {

        $ForumID = TRASH_FORUM_ID;

        $Title = "Trashed Posts - from \"$OldTitle\"";

        $ExtraSystemPost = "[br][b]$LoggedUser[Username] trashed $NumSplitPosts posts ($sqltime) because:[/b][br][br]{$_POST[comment]}";

        $DB->query("INSERT INTO forums_topics
              (Title, AuthorID, ForumID, LastPostID, LastPostTime, LastPostAuthorID, NumPosts)
              Values
              ('".db_string($Title)."', '$FirstAuthorID', '$ForumID', '$lastpostID', '$sqltime', '$LastAuthorID','".($NumSplitPosts+1)."')");
        $SplitTopicID = $DB->inserted_id();
        $extra = "trashed to";
        $numtopics = '+1';


    } elseif ($_POST['splitoption'] == 'deletesplit') {

        if(!check_perms('site_admin_forums')) error(403);


    } else {  // split into a new thread

		if ($Title == '') $Title = "Split thread - from \"$OldTitle\"";

        $DB->query("INSERT INTO forums_topics
              (Title, AuthorID, ForumID, LastPostID, LastPostTime, LastPostAuthorID, NumPosts)
              Values
              ('".db_string($Title)."', '$FirstAuthorID', '$ForumID', '$lastpostID', '$sqltime', '$LastAuthorID','".($NumSplitPosts+1)."')");
        $SplitTopicID = $DB->inserted_id();
        $extra = "moved to";
        $numtopics = '+1';
    }

    if ($_POST['splitoption'] == 'deletesplit') {

        // post in original thread
        $SystemPostOld = "[quote=the system]$NumSplitPosts posts were deleted from this thread[/quote]";
    } else {
        $SystemPost = "[quote=the system]$NumSplitPosts posts $extra this thread from [url=/forums.php?action=viewthread&threadid=$TopicID]\"$OldTitle\"[/url][/quote]";
        if ($ExtraSystemPost) $SystemPost .= $ExtraSystemPost;

        $DB->query("INSERT INTO forums_posts (TopicID, AuthorID, AddedTime, Body)
                        VALUES ('$SplitTopicID', '$LoggedUser[ID]', '".sqltime(strtotime($FirstAddedTime)-10)."', '".db_string($SystemPost)."')");
        $PrePostID = $DB->inserted_id();

        // post in original thread
        if ($_POST['splitoption'] == 'trashsplit') {
            $SystemPostOld = "[quote=the system]$NumSplitPosts posts were removed from this thread[/quote]";
        } else {
            $SystemPostOld = "[quote=the system]$NumSplitPosts posts $extra thread [url=/forums.php?action=viewthread&threadid=$SplitTopicID]\"$Title\"[/url][/quote]";
        }
    }

    $DB->query("INSERT INTO forums_posts (TopicID, AuthorID, AddedTime, Body)
                    VALUES ('$TopicID', '$LoggedUser[ID]', '$sqltime', '".db_string($SystemPostOld)."')");
    $PostPostID = $DB->inserted_id();

    $DB->query("UPDATE forums_topics SET LastPostID='$PostPostID',
                                         LastPostAuthorID  = '$LoggedUser[ID]',
                                         LastPostTime	= '$sqltime',
                                         StickyPostID = '$OldStickyPostID',
                                         NumPosts=((NumPosts+1)-$NumSplitPosts) WHERE ID='$TopicID'");

    //$DB->query("DELETE FROM forums_last_read_topics WHERE TopicID='$TopicID'");

    // move the selected posts
    $PostIDs = implode(',', $PostIDs);


    if ($_POST['splitoption'] == 'deletesplit') {

        $DB->query("DELETE FROM forums_posts WHERE ID IN ($PostIDs)");

    } else {

        $DB->query("UPDATE forums_posts SET TopicID='$SplitTopicID', Body=CONCAT_WS( '\n\n', Body, '[align=right][size=0][i]split from thread[/i][br]\'$OldTitle\'[/size][/align]') WHERE TopicID='$TopicID' AND ID IN ($PostIDs)");

    }

    $Cache->begin_transaction('forums_list');

    update_forum_info($ForumID, $numtopics,false);

    if ($OldForumID!=$ForumID) {    // If we're moving posts into a new forum, change the new forum stats

        update_forum_info($OldForumID, 0,false);
    }

    $Cache->commit_transaction(0);

    $Cache->delete_value('forums_'.$ForumID);
    $Cache->delete_value('forums_'.$OldForumID);

    $Cache->delete_value('thread_'.$TopicID.'_info');
    $Cache->delete_value('thread_'.$SplitTopicID.'_info');

    $CatalogueID = floor($Posts/THREAD_CATALOGUE);
    for ($i=0;$i<=$CatalogueID;$i++) {
        $Cache->delete_value('thread_'.$TopicID.'_catalogue_'.$i);
    }

    if ($SplitTopicID>0) {
        $CatalogueID = floor( ($NumSplitPosts+1) /THREAD_CATALOGUE);
        for ($i=0;$i<=$CatalogueID;$i++) {
            $Cache->delete_value('thread_'.$SplitTopicID.'_catalogue_'.$i);
        }
    }

    if ($SplitTopicID>0 && $_POST['splitoption'] != 'trashsplit') {
        header("Location: forums.php?action=viewthread&threadid=$SplitTopicID&postid=$PrePostID#post$PrePostID");
    } else {

        header("Location: forums.php?action=viewthread&threadid=$TopicID&postid=$PostPostID#post$PostPostID");
    }

    $Cache->delete_value('latest_topics_forum_'.$ForumID);

// If we're merging a thread
} elseif (isset($_POST['merge'])) {
    if(!check_perms('site_moderate_forums')) error(403);

    if(!is_number($_POST['mergethreadid'])) error("merge thread id is not a number!");
    $MergeTopicID = (int) $_POST['mergethreadid'];
    if($MergeTopicID == $TopicID) error("Merge failed: merge thread id cannot be the same as source thread!");

    $DB->query("SELECT
          t.ForumID,
          t.Title,
          f.MinClassWrite,
          COUNT(p.ID) AS Posts,
          t.LastPostID,
          t.LastPostTime,
          t.LastPostAuthorID
          FROM forums_topics AS t
          LEFT JOIN forums_posts AS p ON p.TopicID=t.ID
          LEFT JOIN forums AS f ON f.ID= t.ForumID
          WHERE t.ID='$MergeTopicID'
          GROUP BY p.TopicID");
    if ($DB->record_count()==0) error("Merge failed: Could not find thread with id=$MergeTopicID");
    list($NewForumID, $MergeTitle, $NFMinClassWrite, $NFPosts, $NFLastPostID, $NFLastPostTime, $NFLastPostAuthorID) = $DB->next_record();

    if ( !check_forumperm($NewForumID, 'Write') ) { error(403); }

	// reducing title spam - 1/2017
    // $MergeTitle = "$MergeTitle (merged with $OldTitle)";
	if ($Title == '') $Title = $MergeTitle;

    if($OldLastPostID>$NFLastPostID){
        $NFLastPostID       = $OldLastPostID;
        $NFLastPostTime     = $OldLastPostTime;
        $NFLastPostAuthorID = $OldLastPostAuthorID;
    }
    $Posts += $NFPosts;

    $DB->query("UPDATE forums_polls SET TopicID='$MergeTopicID' WHERE TopicID='$TopicID'");
    $DB->query("UPDATE forums_polls_votes SET TopicID='$MergeTopicID' WHERE TopicID='$TopicID'");

    $DB->query("UPDATE forums_posts SET TopicID='$MergeTopicID', Body=CONCAT_WS( '\n\n', Body, '[align=right][size=0][i]merged from thread[/i][br]\'$OldTitle\'[/size][/align]') WHERE TopicID='$TopicID'");
    $DB->query("UPDATE forums_topics SET Title='$Title',LastPostID='$NFLastPostID',LastPostTime='$NFLastPostTime',LastPostAuthorID='$NFLastPostAuthorID',NumPosts='$Posts' WHERE ID='$MergeTopicID'");

    $DB->query("DELETE FROM forums_topics WHERE ID='$TopicID'");

    $Cache->begin_transaction('forums_list');

    update_forum_info($OldForumID, '-1',false);
    if ($NewForumID!=$OldForumID) {    // If we're moving posts into a new forum, change the new forum stats

        update_forum_info($NewForumID, 0,false);
        $Cache->delete_value('forums_'.$NewForumID);
    }

    $Cache->commit_transaction(0);
    $Cache->delete_value('thread_'.$TopicID.'_info');
    $Cache->delete_value('thread_'.$MergeTopicID.'_info');

    $CatalogueID = floor($Posts/THREAD_CATALOGUE);
    for ($i=0;$i<=$CatalogueID;$i++) {
        $Cache->delete_value('thread_'.$TopicID.'_catalogue_'.$i);
        $Cache->delete_value('thread_'.$MergeTopicID.'_catalogue_'.$i);
    }

    $Cache->delete_value('latest_topics_forum_'.$OldForumID);
    $Cache->delete_value('latest_topics_forum_'.$NewForumID);
    header("Location: forums.php?action=viewthread&threadid=$MergeTopicID");

// If we're deleting a thread
} elseif (isset($_POST['delete'])) {
    if (check_perms('site_admin_forums')) {
        $DB->query("DELETE FROM forums_posts WHERE TopicID='$TopicID'");
        $DB->query("DELETE FROM forums_topics WHERE ID='$TopicID'");
            $DB->query("DELETE FROM forums_polls WHERE TopicID='$TopicID'");
            $DB->query("DELETE FROM forums_polls_votes WHERE TopicID='$TopicID'");

        update_forum_info($ForumID, '-1');

        $Cache->delete_value('thread_'.$TopicID.'_info');

        $Cache->delete_value('latest_topics_forum_'.$ForumID);
        header('Location: forums.php?action=viewforum&forumid='.$ForumID);
    } else {
        error(403);
    }

// If we're just editing it/moving it/trashing it
} else {
    //if ($_POST['title'] == '') { error(0); }
    if ($Title == '') $Title = $OldTitle;

    $DB->query("SELECT Count(ID), Min(AddedTime) FROM forums_posts WHERE TopicID='$TopicID'");
    list($NumPosts, $FirstAddedTime) = $DB->next_record();

    if (isset($_POST['trash']) || ($ForumID!=$OldForumID)) {
        if (isset($_POST['trash'])) {
            $ForumID = TRASH_FORUM_ID;
        }
        $DB->query("SELECT Name FROM forums WHERE ID='$ForumID'");
        list($ForumName) = $DB->next_record();
        $SystemPost = "[quote=the system]This thread moved from the [b][url=/forums.php?action=viewforum&forumid=$OldForumID]{$OldForumName}[/url][/b] forum to the [b][url=/forums.php?action=viewforum&forumid=$ForumID]{$ForumName}[/url][/b] forum.[/quote]";
        if (isset($_POST['trash'])) {
            $ForumID = TRASH_FORUM_ID;
            $Title = "Trashed Thread - from \"$OldTitle\"";
            $SystemPost .= "[b]$LoggedUser[Username] trashed this thread ($sqltime) because:[/b][br][br]{$_POST[comment]}";
        } else {
            $SystemPost .= "[b]$LoggedUser[Username] moved this thread ($sqltime)[/b]";
        }

        $DB->query("INSERT INTO forums_posts (TopicID, AuthorID, AddedTime, Body)
                        VALUES ('$TopicID', '$LoggedUser[ID]', '".sqltime(strtotime($FirstAddedTime)-10)."', '".db_string($SystemPost)."')");
        $PrePostID = $DB->inserted_id();
        $NumPosts=$NumPosts+1;

        $Cache->delete_value('forums_'.$ForumID);
        $CatalogueID = floor(($NumPosts+1)/THREAD_CATALOGUE);
        for ($i=0;$i<=$CatalogueID;$i++) {
            $Cache->delete_value('thread_'.$TopicID.'_catalogue_'.$i);
        }

    }

    $Cache->begin_transaction('thread_'.$TopicID.'_info');
    $UpdateArray = array(
        'IsSticky'=>$Sticky,
        'IsLocked'=>$Locked,
        'Title'=>cut_string($Title, 150, 1, 0),
        'ForumID'=>$ForumID,
        'Posts'=>$NumPosts
        );
    $Cache->update_row(false, $UpdateArray);
    $Cache->commit_transaction(0);

    if($Sticky != $OldIsSticky){
        if($Sticky == '1'){
            make_thread_note("Stickied", $TopicID);
        }else{
            make_thread_note("Unstickied", $TopicID);
        }
    }

    if($Locked != $OldIsLocked){
        if($Locked == '1'){
            make_thread_note("Locked", $TopicID);
        }else{
            make_thread_note("Unlocked", $TopicID);
        }
    }

    if($Title != $OldTitle){
        make_thread_note("Title changed from $OldTitle to $Title", $TopicID);
    }

    if(!empty($_POST['note'])){
        make_thread_note(db_string($_POST['note']), $TopicID);
    }

    $DB->query("UPDATE forums_topics SET
        IsSticky = '$Sticky',
        IsLocked = '$Locked',
        Title = '".db_string($Title)."',
        ForumID ='$ForumID',
        NumPosts='$NumPosts'
        WHERE ID='$TopicID'");

    if ($ForumID!=$OldForumID) { // If we're moving a thread, change the forum stats

            if ( !check_forumperm($ForumID, 'Write') ) { error(403); }

        $DB->query("SELECT MinClassRead, MinClassWrite, Name FROM forums WHERE ID='$ForumID'");
        list($MinClassRead, $MinClassWrite, $ForumName) = $DB->next_record();
        $Cache->begin_transaction('thread_'.$TopicID.'_info');
        $UpdateArray = array(
            'ForumName'=>$ForumName,
            'MinClassRead'=>$MinClassRead,
            'MinClassWrite'=>$MinClassWrite
            );
        $Cache->update_row(false, $UpdateArray);
        $Cache->commit_transaction(3600*24*5);

        $Cache->begin_transaction('forums_list');
        // Forum we're moving from
        update_forum_info($OldForumID, '-1', false);
        // Forum we're moving to
        update_forum_info($ForumID, '+1', false);
        $Cache->commit_transaction(0);

    } else { // Editing
        $DB->query("SELECT LastPostTopicID FROM forums WHERE ID='$ForumID'");
        list($LastTopicID) = $DB->next_record();
        if ($LastTopicID == $TopicID) {
            $UpdateArray = array(
                'Title'=>$Title,
                'IsLocked'=>$Locked,
                'IsSticky'=>$Sticky
            );
            $Cache->begin_transaction('forums_list');
            $Cache->update_row($ForumID, $UpdateArray);
            $Cache->commit_transaction(0);
        }
    }
    if ($Locked) {
        $CatalogueID = floor($NumPosts/THREAD_CATALOGUE);
        for ($i=0;$i<=$CatalogueID;$i++) {
            $Cache->expire_value('thread_'.$TopicID.'_catalogue_'.$i,3600*24*7);
        }
        $Cache->expire_value('thread_'.$TopicID.'_info',3600*24*7);

        $DB->query('UPDATE forums_polls SET Closed=\'0\' WHERE TopicID=\''.$TopicID.'\'');
        $Cache->delete_value('polls_'.$TopicID);
    }
    $Cache->delete_value('latest_topics_forum_'.$ForumID);
    if ($ForumID != $OldForumID)
        $Cache->delete_value('latest_topics_forum_'.$OldForumID);
    header('Location: forums.php?action=viewthread&threadid='.$TopicID.'&page='.$Page);
}
