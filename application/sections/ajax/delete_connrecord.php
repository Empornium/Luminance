<?php
if (isset($_REQUEST['ip']) && isset($_REQUEST['userid']) ) {

    if (!is_number($_REQUEST['userid'])) {
        echo json_encode(array(false, 'UserID is not a number'));
        die();
    }
    if (!check_perms('users_mod') && $_REQUEST['userid']!==$LoggedUser['ID'] ) {
        echo json_encode(array(false, 'You do not have permission to access this page!'));
        die();
    }

    $DB->query("DELETE FROM users_connectable_status
                  WHERE UserID='" . db_string($_REQUEST['userid']) . "' AND IP='" . db_string($_REQUEST['ip']) . "' ");

    $result = $DB->affected_rows();

    if ($result > 0) {
        $Cache->delete_value('connectable_'.$_REQUEST['userid']);
        echo json_encode(array(true, "removed $result record for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "));
    } elseif ($result == 0) {
        echo json_encode(array(false, "no record to remove for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "));
    } else {
        echo json_encode(array(false, "error: failed to remove record for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "));
    }

} else {
    // didnt get ip and port info
    echo json_encode(array(false, 'Parameters not specified'));
}
