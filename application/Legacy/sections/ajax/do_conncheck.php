<?php
if (isset($_REQUEST['ip']) && isset($_REQUEST['port']) && isset($_REQUEST['userid'])) {

    if (!is_integer_string($_REQUEST['userid'])) {
        echo json_encode([false, 'UserID is not a number']);
        die();
    }

    if (!check_perms('users_mod') && $_REQUEST['userid']!=$activeUser['ID']) {
        echo json_encode([false, 'You do not have permission to access this page!']);
        die();
    }

    if (empty($_REQUEST['ip']) || !validate_ip($_REQUEST['ip'])) {
        die(json_encode([false, 'Invalid IP']));
    }

    if (empty($_REQUEST['port']) || !is_integer_string($_REQUEST['port']) || $_REQUEST['port']<1 || $_REQUEST['port']>65535) {
        die(json_encode([false, 'Invalid Port']));
    }

    $connresult = @fsockopen($_REQUEST['ip'], $_REQUEST['port'], $Errno, $Errstr, 20) ? 'yes' : 'no';

    $now = time();
    $master->db->rawQuery(
        "INSERT INTO users_connectable_status (UserID, IP, Status, Time)
              VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY
              UPDATE Status = VALUES(Status),
                     Time = VALUES(Time)",
        [$_REQUEST['userid'], $_REQUEST['ip'], $connresult, $now]
    );

    $master->cache->cacheValue('connectable_'.$_REQUEST['userid'], [$connresult, $_REQUEST['ip'], $_REQUEST['port'], $now], 0);
    //$master->cache->deleteValue('connectable_'.$_REQUEST['userid']);

    if ($connresult == 'yes') {
        echo json_encode([true, "Port $_REQUEST[port] on $_REQUEST[ip] connected successfully"]);
    } else {
        echo json_encode([false, "Port $_REQUEST[port] on $_REQUEST[ip] failed to connect"]);
    }

} else {
    // didnt get ip and port info
    echo json_encode([false, 'Parameters not specified']);
}
