<?php
authorize();
include(SERVER_ROOT . '/Legacy/sections/user/groupfunctions.php');

if (!check_perms('users_groups')) {
    error(403);
}

$userID = (int)$_REQUEST['userid'];
if (!$userID || !is_integer_string($userID)) error(0);

switch ($_REQUEST['groupaction']) {
    case 'remove':
        remove_user($userID, $_REQUEST['removegid']);
        break;

    case 'add':
        add_user($userID, $_REQUEST['addgid']);
        break;

    case 'update':
        add_comment($_REQUEST['groupComment'], $userID, $_REQUEST['gid']);
        break;

    default:
        error(403);
}

header('Location: /user.php?id='.$userID);
