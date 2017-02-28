<?php
enforce_login();

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

include(SERVER_ROOT.'/sections/forums/functions.php');

// Replace the old hard-coded forum categories
unset($ForumCats);
$ForumCats = get_forum_cats();
//This variable contains all our lovely forum data
$Forums = get_forums_info();

if (!empty($_POST['action'])) {
    switch ($_POST['action']) {
        case 'goto_forum':
            // using the forum jump
            $ForumID = (int) $_POST['forumid'];
            header('Location: forums.php?action=viewforum&forumid='.$ForumID);
            break;
        case 'reply':
            require(SERVER_ROOT.'/sections/forums/take_reply.php');
            break;
        case 'new':
            require(SERVER_ROOT.'/sections/forums/take_new_thread.php');
            break;
        case 'mod_thread':
            require(SERVER_ROOT.'/sections/forums/mod_thread.php');
            break;
        case 'trash_post':
            require(SERVER_ROOT.'/sections/forums/trash.php');
            break;
        case 'poll_vote':
            require(SERVER_ROOT.'/sections/forums/poll_vote.php');
            break;
        case 'poll_mod':
            require(SERVER_ROOT.'/sections/forums/poll_mod.php');
            break;
        case 'add_poll_option':
            require(SERVER_ROOT.'/sections/forums/add_poll_option.php');
            break;
        default:
            error(0);
    }
} elseif (!empty($_GET['action'])) {
    switch ($_GET['action']) {
        case 'unread':
            require(SERVER_ROOT.'/sections/forums/unread_posts.php');
            break;
        case 'allposts':
            require(SERVER_ROOT.'/sections/forums/all_posts.php');
            break;
        case 'viewforum':
            // Page that lists all the topics in a forum
            require(SERVER_ROOT.'/sections/forums/forum.php');
            break;
        case 'viewthread':
        case 'viewtopic':
            // Page that displays threads
            require(SERVER_ROOT.'/sections/forums/thread.php');
            break;
        case 'ajax_get_edit':
            // Page that switches edits for mods
            require(SERVER_ROOT.'/common/ajax_get_edit.php');
            break;
        case 'new':
            // Create a new thread
            require(SERVER_ROOT.'/sections/forums/newthread.php');
            break;
        case 'takeedit':
            // Edit posts
            require(SERVER_ROOT.'/sections/forums/takeedit.php');
            break;
        case 'get_post':
            // Get posts
            require(SERVER_ROOT.'/common/get_post.php');
            break;
        case 'delete':
            // Delete posts
            require(SERVER_ROOT.'/sections/forums/delete.php');
            break;
        case 'modunlock':
            // Unlock posts
            require(SERVER_ROOT.'/sections/forums/modunlock.php');
            break;
        case 'timeunlock':
            // Unlock posts
            require(SERVER_ROOT.'/sections/forums/timeunlock.php');
            break;
        case 'catchup':
            // Catchup
            require(SERVER_ROOT.'/sections/forums/catchup.php');
            break;
        case 'search':
            // Search posts
            require(SERVER_ROOT.'/sections/forums/search.php');
            break;
        case 'change_vote':
            // Change poll vote
            require(SERVER_ROOT.'/sections/forums/change_vote.php');
            break;
        case 'delete_poll_option':
            require(SERVER_ROOT.'/sections/forums/delete_poll_option.php');
            break;
        case 'sticky_post':
            require(SERVER_ROOT.'/sections/forums/sticky_post.php');
            break;
        case 'edit_rules':
            require(SERVER_ROOT.'/sections/forums/edit_rules.php');
            break;
        case 'thread_subscribe':
            break;
        default:
            error(404);
    }
} else {
    require(SERVER_ROOT.'/sections/forums/main.php');
}

// Function to get basic information on a forum
// Uses class CACHE
function get_forum_info($ForumID)
{
    global $DB, $Cache;
    $Forum = $Cache->get_value('ForumInfo_'.$ForumID);
    if (!$Forum) {
        $DB->query("SELECT
            Name,
            MinClassRead,
            MinClassWrite,
            MinClassCreate,
            COUNT(forums_topics.ID) AS Topics
            FROM forums
            LEFT JOIN forums_topics ON forums_topics.ForumID=forums.ID
            WHERE forums.ID='$ForumID'
            GROUP BY ForumID");
        if ($DB->record_count() == 0) {
            return false;
        }
        // Makes an array, with $Forum['Name'], etc.
        $Forum = $DB->next_record(MYSQLI_ASSOC);

        $Cache->cache_value('ForumInfo_'.$ForumID, $Forum, 86400); // Cache for a day
    }

    return $Forum;
}
