<?php
//TODO
/*****************************************************************
Finish removing the take[action] pages and utilize the index correctly
Should the advanced search really only show if they match 3 perms?
Make sure all constants are defined in config.php and not in random files
*****************************************************************/
enforce_login();
$Val = new \Luminance\Legacy\Validate;

if (empty($_REQUEST['action'])) { $_REQUEST['action']=''; }

switch ($_REQUEST['action']) {
    case 'dupes':
        include 'manage_linked.php';
        break;
    case 'groups':
        include 'manage_groups.php';
        break;
    case 'notify':
        include 'notify_edit.php';
        break;
    case 'notify_handle':
        include 'notify_handle.php';
        break;
    case 'notify_delete':
        authorize();
        if ($_GET['id'] && is_integer_string($_GET['id'])) {
            $master->db->rawQuery(
                "DELETE
                   FROM users_notify_filters
                  WHERE ID = ?
                    AND UserID = ?",
                [$_GET['id'], $activeUser['ID']]
            );
        }
        $master->cache->deleteValue('notify_filters_'.$activeUser['ID']);
        header('Location: user.php?action=notify');
        break;
    case 'search':// User search
        if (check_perms('admin_advanced_user_search')) {
            include 'advancedsearch.php';
        } else {
            include 'search.php';
        }
        break;
    case 'edit':
        include 'edit.php';
        break;
    case 'takeedit':
        include 'takeedit.php';
        break;
    case 'sessions':
        include 'sessions.php';
        break;
    case 'connchecker':
        include 'connchecker.php';
        break;
    case 'permissions':
        include 'permissions.php';
        break;
    case 'similar':
        include 'similar.php';
        break;
    case 'moderate':
        include 'takemoderate.php';
        break;
    default:
        if ($_REQUEST['action']=='reset_login_watch' && is_integer_string($_POST['loginid'])) {
            authorize();
            if (!check_perms('admin_login_watch')) error(403);

            if ($flood = $master->repos->requestfloods->load($_POST['loginid'])) {
                $master->repos->requestfloods->delete($flood);
            }

            if ($IP = $master->repos->ips->load($flood->IPID)) {
                $master->repos->ips->unban($IP);
            }
        }
        if (isset($_REQUEST['id'])) {
            include 'user.php';
        } else {
            header('Location: index.php');
        }
}
