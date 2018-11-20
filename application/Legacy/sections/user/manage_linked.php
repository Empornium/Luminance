<?php
authorize();
include(SERVER_ROOT . '/Legacy/sections/user/linkedfunctions.php');

if (!check_perms('users_mod')) {
    error(403);
}

if (!$UserID || !is_number($UserID)) {
    error(0);
}

$UserID = (int)$_REQUEST['userid'];

switch ($_REQUEST['dupeaction']) {
    case 'remove':
        unlink_user($_REQUEST['removeid']);
        break;

    case 'link':
        if ($_REQUEST['targetid']) {
            $TargetID = $_REQUEST['targetid'];
            link_users($UserID, $TargetID);
        }
        break;

    case 'update':
        if (isset($_REQUEST['submitlink'])) {
            if ($_REQUEST['target']) {
                $Target = $_REQUEST['target'];
                $DB->query("SELECT ID FROM users_main WHERE Username LIKE '" . db_string($Target) . "'");
                if (list($TargetID) = $DB->next_record()) {
                    link_users($UserID, $TargetID);
                } else {
                    error("User '".display_str($Target)."' not found.");
                }
            }
        }

        if (isset($_REQUEST['submitcomment'])) {
            $DB->query("SELECT GroupID FROM users_dupes WHERE UserID = '$UserID'");
            list($GroupID) = $DB->next_record();

            if ($_REQUEST['dupecomments'] && $GroupID) {
                dupe_comments($GroupID, $_REQUEST['dupecomments']);
            }
        }
        break;
    default:
        error(403);
}
echo '\o/';
header("Location: user.php?id=$UserID");
