<?php
if (!check_perms('admin_donor_log')) error(403,true);

include(SERVER_ROOT.'/Legacy/sections/donate/functions.php');

$address = $_REQUEST['address'];
if (isset($_REQUEST['state'])) {
    $state = $_REQUEST['state'];
    if (!in_array($state, ['unused', 'submitted', 'cleared'])) error(0, true);

    $result = $master->db->rawQuery(
        'UPDATE bitcoin_donations
            SET state = ?
          WHERE public = ?',
        [$state, $address]
    )->rowCount();

    echo $result;

} else if (isset($_REQUEST['amount'])) {
    if (!is_integer_string($_REQUEST['amount'])) error(0, true);
    $amount = $_REQUEST['amount'];
    $master->db->rawQuery(
        'UPDATE bitcoin_donations
            SET current_euro = ?
          WHERE public = ?',
        [$amount. $address]
    );
}
