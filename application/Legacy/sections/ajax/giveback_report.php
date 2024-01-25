<?php
if (!check_perms('admin_reports')) {
    error(403);
}

$IDs = explode(',', $_GET['id']);
foreach ($IDs as $ID) {
    if (!is_integer_string($ID)) {
        error(0);
    }
}

$inQuery = implode(',', array_fill(0, count($IDs), '?'));

$master->db->rawQuery(
    "UPDATE reportsv2
        SET Status = 'New',
            ResolverID = 0
      WHERE ID IN ({$inQuery})",
    $IDs
);
