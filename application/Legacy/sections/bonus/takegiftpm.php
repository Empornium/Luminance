<?php
global $master, $classes;

enforce_login();
authorize();
if ($classes[$activeUser['PermissionID']]['Level'] < LEVEL_ADMIN) {
    error(405);
}
$Body = $_POST['body'];

$master->db->rawQuery(
    "UPDATE systempm_templates
        SET Body = ?
      WHERE ID = 1",
    [$Body]
);

$master->cache->deleteValue('systempm_template_1');

header("Location: bonus.php?action=gift");
