<?php
authorize();

//TODO: Remove all the stupid queries that could get their information just as easily from the cache
/*********************************************************************\
//--------------Take Post--------------------------------------------//

This page takes a forum post submission, validates it (TODO), and
enters it into the database. The user is then redirected to their
post.

$_POST['action'] is what the user is trying to do. It can be:

'reply' if the user is replying to a thread
    It will be accompanied with:
    $_POST['thread']
    $_POST['body']

\*********************************************************************/

// Quick SQL injection checks

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

if (isset($_POST['thread']) && !is_number($_POST['thread'])) {
    error(0);
}
if (isset($_POST['forum']) && !is_number($_POST['forum'])) {
    error(0);
}

//If you're not sending anything, go back
if (empty($_POST['body'])) {
    error('You cannot post a reply with no content.');
}

$master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::POST);

$Body = $_POST['body'];

$Text = new Luminance\Legacy\Text;
$Text->validate_bbcode($_POST['body'], get_permissions_advtags($LoggedUser['ID']));

$TopicID = $_POST['thread'];
$ThreadInfo = get_thread_info($TopicID);
if ($ThreadInfo === false) {
    error(404);
}
$ForumID = $ThreadInfo['ForumID'];
$sqltime = sqltime();

if (!check_forumperm($ForumID)) {
    error(403);
}
if (!check_forumperm($ForumID, 'Write') || $master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::POST) || $ThreadInfo['IsLocked'] == "1" && !check_perms('site_moderate_forums')) {
    error(403);
}

if (isset($_POST['subscribe'])) {
    $DB->query("INSERT IGNORE INTO users_subscriptions VALUES ($LoggedUser[ID], '".db_string($TopicID)."')");
    $Cache->delete_value('subscriptions_user_'.$LoggedUser['ID']);
}

//Get the id for this post in the database to append
$DB->query("SELECT ID, Body, AuthorID, AddedTime, EditedTime, TimeLock FROM forums_posts WHERE TopicID='$TopicID' AND AuthorID='".$LoggedUser['ID']."' ORDER BY AddedTime DESC LIMIT 1");
list($PostID, $OldBody, $AuthorID, $AddedTime, $EditedTime, $TimeLock) = $DB->next_record();

$DB->query("SELECT EditedUserID from forums_posts WHERE ID='$PostID'");
list($EditedUserID) = $DB->next_record();

//Now lets handle the special case of merging posts, we can skip bumping the thread and all that fun
if ($ThreadInfo['LastPostAuthorID'] == $LoggedUser['ID'] // User has written the last post in the thread
    && ((!check_perms('site_forums_double_post') && !in_array($ForumID, $ForumsDoublePost)) || isset($_POST['merge'])) // user chose to merge or is forced to merge
    && can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $TimeLock)) { // post wasn't edited by someone else and is within the edit time

    //Edit the post
    $DB->query("UPDATE forums_posts SET Body = CONCAT(Body,'"."\n\n".db_string($Body)."'),
                                            EditedUserID = '".$LoggedUser['ID']."',
                                            EditedTime = '$sqltime'
                                            WHERE ID='$PostID'");

    //Get the catalogue it is in
    $CatalogueID = floor((POSTS_PER_PAGE*ceil($ThreadInfo['Posts']/POSTS_PER_PAGE)-POSTS_PER_PAGE)/THREAD_CATALOGUE);

    //Get the catalogue value for the post we're appending to
    if ($ThreadInfo['Posts']%THREAD_CATALOGUE == 0) {
        $Key = THREAD_CATALOGUE-1;
    } else {
        $Key = ($ThreadInfo['Posts']%THREAD_CATALOGUE)-1;
    }

    //Edit the post in the cache
    $Cache->begin_transaction('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
    $Cache->update_row($Key, array(
            'Body'=>$OldBody."\n\n".$Body,
            'EditedUserID'=>$LoggedUser['ID'],
            'EditedTime'=>$sqltime,
             'Username'=>$LoggedUser['Username']
            ));
    $Cache->commit_transaction(0);
//      $Cache->delete_value('thread_'.$TopicID.'_catalogue_'.$CatalogueID);

    $DB->query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                         VALUES ('forums', $PostID, $LoggedUser[ID], '$sqltime', '".db_string($OldBody)."')");

    $Cache->delete_value("forums_edits_$PostID");

//Now we're dealing with a normal post
} else {
      flood_check();

    //Insert the post into the posts database
    $DB->query("INSERT INTO forums_posts (TopicID, AuthorID, AddedTime, Body)
            VALUES ('$TopicID', '$LoggedUser[ID]', '$sqltime', '".db_string($Body)."')");

    $PostID = $DB->inserted_id();

    //This updates the root index
    $DB->query("UPDATE forums SET
            NumPosts		  = NumPosts+1,
            LastPostID		= '$PostID',
            LastPostAuthorID  = '$LoggedUser[ID]',
            LastPostTopicID   = '$TopicID',
            LastPostTime	  = '$sqltime'
            WHERE ID = '$ForumID'");

    //Update the topic
    $DB->query("UPDATE forums_topics SET
            NumPosts		  = NumPosts+1,
            LastPostID		= '$PostID',
            LastPostAuthorID  = '$LoggedUser[ID]',
            LastPostTime	  = '$sqltime'
            WHERE ID = '$TopicID'");

    //if cache exists modify it, if not, then it will be correct when selected next, and we can skip this block
    if ($Forum = $Cache->get_value('forums_'.$ForumID)) {
        list($Forum,,,$Stickies) = $Forum;

        //if the topic is already on this page
        if (array_key_exists($TopicID, $Forum)) {
            $Thread = $Forum[$TopicID];
            unset($Forum[$TopicID]);
            $Thread['NumPosts'] = $Thread['NumPosts']+1; //Increment post count
            $Thread['LastPostID'] = $PostID; //Set postid for read/unread
            $Thread['LastPostTime'] = $sqltime; //Time of last post
            $Thread['LastPostAuthorID'] = $LoggedUser['ID']; //Last poster id
            $Thread['LastPostUsername'] = $LoggedUser['Username']; //Last poster username
            $Part2 = array($TopicID=>$Thread); //Bumped thread

        //if we're bumping from an older page
        } else {
            //Remove the last thread from the index
            if (count($Forum) == TOPICS_PER_PAGE && $Stickies < TOPICS_PER_PAGE) {
                array_pop($Forum);
            }
            //Never know if we get a page full of stickies...
            if ($Stickies < TOPICS_PER_PAGE || $ThreadInfo['IsSticky'] == 1) {
                //Pull the data for the thread we're bumping
                $DB->query("SELECT f.AuthorID, f.IsLocked, f.IsSticky, f.NumPosts, u.Username, ISNULL(p.TopicID) AS NoPoll FROM forums_topics AS f INNER JOIN users_main AS u ON u.ID=f.AuthorID LEFT JOIN forums_polls AS p ON p.TopicID=f.ID WHERE f.ID ='$TopicID'");
                list($AuthorID,$IsLocked,$IsSticky,$NumPosts,$AuthorName,$NoPoll) = $DB->next_record();
                $Part2 = array($TopicID => array(
                    'ID' => $TopicID,
                    'Title' => $ThreadInfo['Title'],
                    'AuthorID' => $AuthorID,
                    'AuthorUsername' => $AuthorName,
                    'IsLocked' => $IsLocked,
                    'IsSticky' => $IsSticky,
                    'NumPosts' => $NumPosts,
                    'LastPostID' => $PostID,
                    'LastPostTime' => $sqltime,
                    'LastPostAuthorID' => $LoggedUser['ID'],
                    'LastPostUsername' => $LoggedUser['Username'],
                    'NoPoll' => $NoPoll
                )); //Bumped
            } else {
                $Part2 = array();
            }
        }
        if ($Stickies > 0) {
            $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickies
            $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE-$Stickies-1, true); //Rest of page
        } else {
            $Part1 = array();
            $Part3 = $Forum;
        }
        if (is_null($Part1)) {
            $Part1 = array();
        }
        if (is_null($Part3)) {
            $Part3 = array();
        }
        if ($ThreadInfo['IsSticky'] == 1) {
            $Forum = $Part2 + $Part1 + $Part3; //Merge it
        } else {
            $Forum = $Part1 + $Part2 + $Part3; //Merge it
        }
        $Cache->cache_value('forums_'.$ForumID, array($Forum,'',0,$Stickies), 0);

        //Update the forum root
        $Cache->begin_transaction('forums_list');
        $Cache->update_row($ForumID, array(
            'NumPosts'=>'+1',
            'LastPostID'=>$PostID,
            'LastPostAuthorID'=>$LoggedUser['ID'],
            'Username'=>$LoggedUser['Username'],
            'LastPostTopicID'=>$TopicID,
            'LastPostTime'=>$sqltime,
            'Title'=>$ThreadInfo['Title'],
            'IsLocked'=>$ThreadInfo['IsLocked'],
            'IsSticky'=>$ThreadInfo['IsSticky']
            ));
        $Cache->commit_transaction(0);
    } else {
        //If there's no cache, we have no data, and if there's no data
        $Cache->delete_value('forums_list');
    }

    //This calculates the block of 500 posts that this one will fall under
    $CatalogueID = floor((POSTS_PER_PAGE*ceil($ThreadInfo['Posts']/POSTS_PER_PAGE)-POSTS_PER_PAGE)/THREAD_CATALOGUE);

      $DB->query("SELECT Signature FROM users_main WHERE ID='{$LoggedUser['ID']}'");
    if ($DB->record_count()>0) {
        list($Sig)= $DB->next_record();
    }

    //Insert the post into the thread catalogue (block of 500 posts)
    $Cache->begin_transaction('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
    $Cache->insert('', array(
        'ID'=>$PostID,
        'AuthorID'=>$LoggedUser['ID'],
        'AddedTime'=>$sqltime,
        'Body'=>$Body,
        'EditedUserID'=>0,
        'EditedTime'=>'0000-00-00 00:00:00',
        'Username'=>$LoggedUser['Username'],
        'Signature'=>$Sig
        ));
    $Cache->commit_transaction(0);

    //Update the thread info
    $Cache->delete_value('thread_'.$TopicID.'_info');
    $Cache->delete_value('latest_topics_forum_'.$ForumID);

    //Increment this now to make sure we redirect to the correct page
    $ThreadInfo['Posts']++;
}

$DB->query("SELECT UserID FROM users_subscriptions WHERE TopicID = ".$TopicID);
if ($DB->record_count() > 0) {
    $Subscribers = $DB->collect('UserID');
    foreach ($Subscribers as $Subscriber) {
        $Cache->delete_value('subscriptions_user_new_'.$Subscriber);
    }
}

header('Location: forums.php?action=viewthread&threadid='.$TopicID.'&page='.ceil($ThreadInfo['Posts']/$PerPage)."#post$PostID");
die();
