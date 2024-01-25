<?php
/*
 * This page simply assings a report to the person clicking on
 * the Grab / Grab All button.
 */
if (!check_perms('admin_reports')) {
        echo '403';
        die();
}

if (!is_integer_string($_GET['id'])) {
        die();
}
$affectedRows = $master->db->rawQuery(
    "UPDATE reportsv2
        SET Status = 'InProgress',
            ResolverID = ?
      WHERE ID = ?",
    [$activeUser['ID'], $_GET['id']]
)->rowCount();
if ($affectedRows == 0) {
    echo '0';
} else {
    echo '1';
}
