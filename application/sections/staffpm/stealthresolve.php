<?php
include(SERVER_ROOT.'/sections/staffpm/functions.php');

if ($ID = (int) ($_GET['id'])) {
    // Check if conversation belongs to user
    $DB->query("SELECT UserID, AssignedToUser FROM staff_pm_conversations WHERE ID=$ID");
    list($UserID, $AssignedToUser) = $DB->next_record();

    if (check_perms('admin_stealth_resolve')) {
        // Conversation belongs to user or user is staff, resolve it
        $DB->query("UPDATE staff_pm_conversations SET StealthResolved=1 WHERE ID=$ID");
        $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);

        // Add a log message to the StaffPM
        $Message = sqltime()." - Stealth Resolved by ".$LoggedUser['Username'];
        make_staffpm_note($Message, $ID);

        header('Location: staffpm.php?view=open');
    } else {
        // Conversation does not belong to user
        error(403);
    }
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
