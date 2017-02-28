<?php
/*****************************************************************
User history switch center

This page acts as a switch that includes the real user history pages (to keep
the root less cluttered).

enforce_login() is run here - the entire user history pages are off limits for
non members.
*****************************************************************/

//Include all the basic stuff...
enforce_login();

if ($_GET['action']) {
    switch ($_GET['action']) {
        case 'tag_history':
            include(SERVER_ROOT.'/sections/userhistory/tag_history.php');
            break;
        case 'ips':
            //Load IP history page
            include(SERVER_ROOT.'/sections/userhistory/ip_history.php');
            break;
        case 'tracker_ips':
            include(SERVER_ROOT.'/sections/userhistory/ip_tracker_history.php');
            break;
        case 'passwords':
            //Load Password history page
            include(SERVER_ROOT.'/sections/userhistory/password_history.php');
            break;
        case 'email':
            //Load email history page
            include(SERVER_ROOT.'/sections/userhistory/email_history.php');
            break;
        case 'email2':
            //Load email history page
            include(SERVER_ROOT.'/sections/userhistory/email_history2.php');
            break;
        case 'passkeys':
            //Load passkey history page
            include(SERVER_ROOT.'/sections/userhistory/passkey_history.php');
            break;
        case 'posts':
            //Load post history page
            include(SERVER_ROOT.'/sections/userhistory/post_history.php');
            break;
        case 'comments':
            //Load comment history page
            include(SERVER_ROOT.'/sections/userhistory/comment_history.php');
            break;
        case 'subscriptions':
            // View subscriptions
            include(SERVER_ROOT.'/sections/userhistory/subscriptions.php');
            break;
        case 'thread_subscribe':
            include(SERVER_ROOT.'/sections/userhistory/thread_subscribe.php');
            break;
        case 'catchup':
            include(SERVER_ROOT.'/sections/userhistory/catchup.php');
            break;
        case 'collage_subscribe':
            include(SERVER_ROOT.'/sections/userhistory/collage_subscribe.php');
            break;
        case 'subscribed_collages':
            include(SERVER_ROOT.'/sections/userhistory/subscribed_collages.php');
            break;
        case 'catchup_collages':
            include(SERVER_ROOT.'/sections/userhistory/catchup_collages.php');
            break;
        case 'token_history':
            include(SERVER_ROOT.'/sections/userhistory/token_history.php');
            break;
        case 'ajax_get_edit':
            // Page that switches edits for mods
            require(SERVER_ROOT.'/common/ajax_get_edit.php');
            break;
        case 'takeedit':
            // Edit posts
            require(SERVER_ROOT.'/sections/forums/takeedit.php');
            break;
        case 'get_post':
            // Get posts
            require(SERVER_ROOT.'/common/get_post.php');
            break;
        default:
            //You trying to mess with me query string? To the home page with you!
            header('Location: index.php');
    }
}

/* Database Information Regarding This Page

users_history_ips:
    id (auto_increment, index)
    userid (index)
    ip (stored using ip2long())
    timestamp

users_history_passwd:
    id (auto_increment, index)
    userid (index)
    changed_by (index)
    old_pass
    new_pass
    timestamp

users_history_email:
    id (auto_increment, index)
    userid (index)
    changed_by (index)
    old_email
    new_email
    timestamp

users_history_passkey:
    id (auto_increment, index)
    userid (index)
    changed_by (index)
    old_passkey
    new_passkey
    timestamp

users_history_stats:
    id (auto_increment, index)
    userid (index)
    uploaded
    downloaded
    ratio
    timestamp

*/
