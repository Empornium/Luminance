<?php
enforce_login();

require(SERVER_ROOT.'/Legacy/sections/blog/functions.php');

// we also use this code for the contests section
if (!in_array($blogSection,['Blog', 'Contests'])) $blogSection = 'Blog';

$thispage = lcfirst($blogSection).'.php';

if (!empty($_REQUEST['action'])) {
    if (!check_perms('admin_manage_blog')) error(403);

    switch ($_REQUEST['action']) {
        case 'removelink' :
            authorize();
            if (is_number($_GET['id'])) {
                $master->db->raw_query("UPDATE blog SET ThreadID=NULL WHERE ID=:blogid", [':blogid' => $_GET['id']]);
                $master->cache->delete_value(strtolower($blogSection));
                $master->cache->delete_value('feed_blog');
            }
            header('Location: '.$thispage);
            break;

        case 'editpost':
        case 'newpost':
            require(SERVER_ROOT.'/Legacy/sections/blog/editnew.php');
            break;

        case 'deletepost':
            authorize();
            if (is_number($_GET['id'])) {
                $master->db->raw_query("DELETE FROM blog WHERE ID=:blogid", [':blogid' => $_GET['id']]);
                $master->cache->delete_value(strtolower($blogSection));
                $master->cache->delete_value(strtolower($blogSection.'_latest_id'));
                $master->cache->delete_value('feed_blog');
            }
            header('Location: '.$thispage);
            break;

        case 'takeeditpost':
            authorize();
            require(SERVER_ROOT.'/Legacy/sections/blog/takeedit.php');
            break;

        case 'takenewpost':
            authorize();
            require(SERVER_ROOT.'/Legacy/sections/blog/takenew.php');
            break;

        default:
            require(SERVER_ROOT.'/Legacy/sections/blog/blog.php');
            break;
    }
} else {
    // view the section
    require(SERVER_ROOT.'/Legacy/sections/blog/blog.php');
}
