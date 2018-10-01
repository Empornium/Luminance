<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($ConvID = (int) ($_GET['id'])) {
    // Is the user allowed to access this StaffPM
    check_access($ConvID);

    if (check_perms('admin_stealth_resolve')) {
        // Conversation belongs to user or user is staff, resolve it
        $DB->query("UPDATE staff_pm_conversations SET StealthResolved=0 WHERE ID=$ConvID");
        $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);

        // Add a log message to the StaffPM
        $Message = sqltime()." - Stealth Unresolved by ".$LoggedUser['Username'];
        make_staffpm_note($Message, $ConvID);

        header('Location: staffpm.php?view=open');
    } else {
        // User cannot stealth unresolve
        error(403);
    }
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
