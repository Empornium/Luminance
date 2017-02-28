<?php
if (!check_perms('admin_reports')) {
    error(403);
}

$IDs = explode(',', $_GET['id']);
foreach ($IDs as $ID) {
    if (!is_number($ID)) {
        error(0);
    }
}
$DB->query("UPDATE reportsv2 SET Status='New', ResolverID = 0 WHERE ID IN (".  db_string($_GET['id']).")");
