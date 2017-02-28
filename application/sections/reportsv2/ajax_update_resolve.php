<?php
// perform the back end of updating a resolve type

if (!check_perms('admin_reports')) {
    error(403);
}

if (empty($_GET['reportid']) || !is_number($_GET['reportid'])) {
    echo 'HAX ATTEMPT!'.$_GET['reportid'];
    die();
}

if (empty($_GET['newresolve'])) {
    echo "No new resolve";
    die();
}

$ReportID = $_GET['reportid'];
$NewType = $_GET['newresolve'];

$TypeList = $Types;
$Priorities = array();
foreach ($TypeList as $Key => $Value) {
        $Priorities[$Key] = $Value['priority'];
}
array_multisort($Priorities, SORT_ASC, $TypeList);

if (!array_key_exists($NewType, $TypeList)) {
    echo "No resolve from that category";
    die();
}

$DB->query("UPDATE reportsv2 SET Type = '".$NewType."' WHERE ID=".$ReportID);
