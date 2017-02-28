<?php
if (isset($_REQUEST['ip']) && isset($_REQUEST['port']) && isset($_REQUEST['userid']) ) {

    if (!is_number($_REQUEST['userid'])) {
        echo json_encode(array(false, 'UserID is not a number'));
        die();
    }

    if (!check_perms('users_mod') && $_REQUEST['userid']!==$LoggedUser['ID'] ) {
        echo json_encode(array(false, 'You do not have permission to access this page!'));
        die();
    }

    $Octets = explode(".", $_REQUEST['ip']);
    if(
        empty($_REQUEST['ip']) ||
        !preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $_REQUEST['ip']) ||
        $Octets[0] < 0 ||
        $Octets[0] > 255 ||
        $Octets[1] < 0 ||
        $Octets[1] > 255 ||
        $Octets[2] < 0 ||
        $Octets[2] > 255 ||
        $Octets[3] < 0 ||
        $Octets[3] > 255 ||
        $Octets[0] == 127 ||
        $Octets[0] == 192 && $Octets[1] == 168
    ) {
        //echo '-3'; //'Invalid IP');
        echo json_encode(array(false, 'Invalid IP'));
        die();
    }

    if (empty($_REQUEST['port']) || !is_number($_REQUEST['port']) || $_REQUEST['port']<1 || $_REQUEST['port']>65535) {
        //echo '-2';    //'Invalid Port');
        echo json_encode(array(false, 'Invalid Port'));
        die();
    }

    $connresult = @fsockopen($_REQUEST['ip'], $_REQUEST['port'], $Errno, $Errstr, 20) ? 'yes' : 'no';

    $now = time();
    $DB->query("INSERT INTO users_connectable_status (UserID, IP, Status, Time)
                    VALUES ( '" . db_string($_REQUEST['userid']) . "','" . db_string($_REQUEST['ip']) . "', '$connresult','$now' )
                    ON DUPLICATE KEY UPDATE Status='$connresult', Time='$now'");

    $Cache->cache_value('connectable_'.$_REQUEST['userid'], array($connresult, $_REQUEST['ip'], $_REQUEST['port'], $now), 0);
    //$Cache->delete_value('connectable_'.$_REQUEST['userid']);

    if ($connresult == 'yes') {
        echo json_encode(array(true, "Port $_REQUEST[port] on $_REQUEST[ip] connected successfully"));
    } else {
        echo json_encode(array(false, "Port $_REQUEST[port] on $_REQUEST[ip] failed to connect"));
    }

} else {
    // didnt get ip and port info
    echo json_encode(array(false, 'Parameters not specified'));
}
