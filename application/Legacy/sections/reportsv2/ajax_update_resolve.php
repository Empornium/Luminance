<?php
// perform the back end of updating a resolve type

if (!check_perms('admin_reports')) {
    error(403, true);
}

if (empty($_GET['reportid']) || !is_number($_GET['reportid'])) {
    error(0, true);
}

if (empty($_GET['newresolve'])) {
    error(0, true);
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
    error("No resolve from that category", true);
}

$DB->query("UPDATE reportsv2 SET Type = '".$NewType."' WHERE ID=".$ReportID);
