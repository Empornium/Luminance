<?php
include(SERVER_ROOT.'/classes/class_text.php');
global $DB, $Cache, $Classes;

enforce_login();
authorize();
if($Classes[$LoggedUser['PermissionID']]['Level'] < LEVEL_ADMIN) {
    error(405);
}
$Body = db_string($_POST['body']);

$DB->query("UPDATE systempm_templates SET Body='" . $Body . "' WHERE ID=1");

$Cache->delete_value('systempm_template_1');

header("Location: bonus.php?action=gift");
