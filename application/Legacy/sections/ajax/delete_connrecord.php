<?php
if (isset($_REQUEST['ip']) && isset($_REQUEST['userid'])) {

    if (!is_integer_string($_REQUEST['userid'])) {
        echo json_encode([false, 'UserID is not a number']);
        die();
    }
    if (!check_perms('users_mod') && $_REQUEST['userid'] != $activeUser['ID']) {
        echo json_encode([false, 'You do not have permission to access this page!']);
        die();
    }

    $connectionRecords = $master->db->rawQuery(
        "DELETE FROM users_connectable_status
               WHERE UserID = ?
                 AND IP = ?",
        [$_REQUEST['userid'], $_REQUEST['ip']]);

    $result = $connectionRecords->rowCount();

    if ($result > 0) {
        $master->cache->deleteValue('connectable_'.$_REQUEST['userid']);
        echo json_encode([true, "removed $result record for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    } elseif ($result == 0) {
        echo json_encode([false, "no record to remove for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    } else {
        echo json_encode([false, "error: failed to remove record for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    }

} else {
    // didnt get ip and port info
    echo json_encode([false, 'Parameters not specified']);
}
