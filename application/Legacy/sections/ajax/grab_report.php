<?php
if (!check_perms('admin_reports')) {
    error(403);
}

if (!is_integer_string($_GET['id'])) {
    error(0);
}

$master->db->rawQuery(
            "UPDATE reportsv2
            SET Status = 'New'
            WHERE ID = ?
            AND Status <> 'Resolved'",
            [$_GET['id']]
);
if ($master->db->foundRows() > 0) {
        //Win
} else {
        echo 'You just tried to grab a resolved or non existent report!';
}
