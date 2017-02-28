<?php
if (!check_perms('admin_donor_log')) error(403,true);

include(SERVER_ROOT.'/sections/donate/functions.php');

$address = $_REQUEST['address'];
$numt = (int) $_REQUEST['numt'];

$result = check_bitcoin_balance($address, $numt);

echo $result;
