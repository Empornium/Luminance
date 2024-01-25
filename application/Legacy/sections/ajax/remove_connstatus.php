<?php
if (isset($_REQUEST['ip']) && isset($_REQUEST['userid'])) {

    if (!is_integer_string($_REQUEST['userid'])) {
        echo json_encode([false, 'UserID is not a number']);
        die();
    }
    if (!check_perms('users_mod') && $_REQUEST['userid']!=$activeUser['ID']) {
        echo json_encode([false, 'You do not have permission to access this page!']);
        die();
    }

    $now = time();
    $result = $master->db->rawQuery(
        "INSERT INTO users_connectable_status (UserID, IP, Status, Time)
         VALUES (?, ?, 'unset', ?)
             ON DUPLICATE KEY
         UPDATE Status = 'unset'",
        [$_REQUEST['userid'], $_REQUEST['ip'], $now]
    )->rowCount();

    if ($result > 0) {
        $master->cache->deleteValue('connectable_'.$_REQUEST['userid']);
        echo json_encode([true, "unset status in $result record for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    } elseif ($result == 0) {
        echo json_encode([false, "could not unset status for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    } else {
        echo json_encode([false, "error: failed to unset status for UserID: $_REQUEST[userid]  IP: $_REQUEST[ip] "]);
    }

} else {
    // didnt get ip and port info
    echo json_encode([false, 'Parameters not specified']);
}
