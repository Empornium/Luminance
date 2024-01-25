<?php
enforce_login();

// Number of users per page
define('BOOKMARKS_PER_PAGE', '20');

if (empty($_REQUEST['action'])) { $_REQUEST['action'] = 'view'; }
switch ($_REQUEST['action']) {
    case 'add':
        require(SERVER_ROOT.'/Legacy/sections/bookmarks/add.php');
        break;

    case 'remove':
        require(SERVER_ROOT.'/Legacy/sections/bookmarks/remove.php');
        break;

    case 'download':
        require(SERVER_ROOT.'/Legacy/sections/bookmarks/download.php');
        break;

    case 'remove_snatched':
        authorize();
        if (!isset($_REQUEST['userid']) || !is_integer_string($_REQUEST['userid'])) {
            error(0);
        }
        // Only owner of the bookmarks or staff can download them
        if ($_REQUEST['userid'] != $activeUser['ID'] && !check_perms('users_override_paranoia')) {
            error(403);
        }

        $userID = $_REQUEST['userid'];
        $master->db->rawQuery(
            "DELETE b FROM bookmarks_torrents AS b
              WHERE b.UserID = ?
                AND b.GroupID IN(
                 SELECT DISTINCT t.GroupID
                   FROM torrents AS t
             INNER JOIN xbt_snatched AS s ON s.fid = t.ID AND s.uid = ?)",
           [$activeUser['ID'], $activeUser['ID']]
        );
        $master->cache->deleteValue("bookmarks_info_{$activeUser['ID']}");
        header('Location: bookmarks.php');
        return;
        break;

    case 'remove_grabbed':
        authorize();
        if (!isset($_REQUEST['userid']) || !is_integer_string($_REQUEST['userid'])) {
            error(0);
        }
        // Only owner of the bookmarks or staff can download them
        if ($_REQUEST['userid'] != $activeUser['ID'] && !check_perms('users_override_paranoia')) {
            error(403);
        }

        $userID = $_REQUEST['userid'];
        $master->db->rawQuery(
            "DELETE b FROM bookmarks_torrents AS b
              WHERE b.UserID = ?
                AND b.GroupID IN(
                 SELECT DISTINCT t.GroupID
                   FROM torrents AS t
             INNER JOIN users_downloads AS ud ON ud.TorrentID = t.ID AND ud.UserID = ?)",
           [$activeUser['ID'], $activeUser['ID']]
        );
        $master->cache->deleteValue("bookmarks_info_{$activeUser['ID']}");
        header('Location: bookmarks.php');
        return;
        break;

    case 'remove_all':
        authorize();
        if (!isset($_REQUEST['userid']) || !is_integer_string($_REQUEST['userid'])) {
            error(0);
        }
        // Only owner of the bookmarks or staff can download them
        if ($_REQUEST['userid'] != $activeUser['ID'] && !check_perms('users_override_paranoia')) {
            error(403);
        }

        $userID = $_REQUEST['userid'];

        $master->db->rawQuery(
            "DELETE b FROM bookmarks_torrents AS b
              WHERE b.UserID = ?",
            [$userID]
        );
        header('Location: bookmarks.php');
        return;
        break;

    case 'view':
        if (empty($_REQUEST['type'])) { $_REQUEST['type'] = 'torrents'; }
        switch ($_REQUEST['type']) {
            case 'torrents':
                require(SERVER_ROOT.'/Legacy/sections/bookmarks/torrents.php');
                break;
            case 'collages':
                $_GET['bookmarks'] = 1;
                header('Location: /collage/bookmarks');
                die();
                break;
            case 'requests':
                include(SERVER_ROOT.'/Legacy/sections/requests/functions.php');
                $_GET['type'] = 'bookmarks';
                require(SERVER_ROOT.'/Legacy/sections/requests/requests.php');
                break;
            default:
                error(404);
        }
        break;
    default:
        error(404);
}
