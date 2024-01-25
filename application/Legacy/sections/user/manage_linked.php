<?php
authorize();
include(SERVER_ROOT . '/Legacy/sections/user/linkedfunctions.php');

if (!check_perms('users_mod')) {
    error(403);
}

if (!$userID || !is_integer_string($userID)) error(0);

$userID = (int)$_REQUEST['userid'];

switch ($_REQUEST['dupeaction']) {
    case 'remove':
        unlink_user($_REQUEST['removeid']);
        break;

    case 'link':
        if ($_REQUEST['targetid']) {
            $TargetID = $_REQUEST['targetid'];
            link_users($userID, $TargetID);
        }
        break;

    case 'update':
        if (isset($_REQUEST['submitlink'])) {

            if ($_REQUEST['target']) {
                $Target = $_REQUEST['target'];
                $targetID = $master->db->rawQuery(
                    "SELECT ID
                       FROM users
                      WHERE Username LIKE ?",
                    [$Target]
                )->fetchColumn();
                if (!($targetID === false)) {
                    link_users($userID, $targetID);
                } else {
                    error("User '".display_str($Target)."' not found.");
                }
            }
        }

        if (isset($_REQUEST['submitcomment'])) {

            $groupID = $master->db->rawQuery(
                "SELECT GroupID
                   FROM users_dupes
                  WHERE UserID = ?",
                [$userID]
            )->fetchColumn();

            if ($_REQUEST['dupecomments'] && $groupID) {
                dupe_comments($groupID, $_REQUEST['dupecomments']);
            }
        }
        break;
    default:
        error(403);
}

header('Location: /user.php?id='.$userID);
