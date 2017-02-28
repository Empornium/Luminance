<?php
include(SERVER_ROOT.'/sections/staffpm/functions.php');

if ($ID = (int) ($_GET['id'])) {
    // Check if conversation belongs to user
    $DB->query("SELECT UserID, Level, AssignedToUser FROM staff_pm_conversations WHERE ID=$ID");
    list($UserID, $Level, $AssignedToUser) = $DB->next_record();

    if ($UserID == $LoggedUser['ID'] || ($IsFLS && $Level == 0) ||
        $AssignedToUser == $LoggedUser['ID'] || ($IsStaff && $Level <= $LoggedUser['Class'])) {

        // Conversation belongs to user or user is staff, unresolve it // changed from Unanswered to Open on unresolve
        $DB->query("UPDATE staff_pm_conversations SET Status='Open' WHERE ID=$ID");
        // Clear cache for user
        $Cache->delete_value('num_staff_pms_'.$LoggedUser['ID']);

        // Add a log message to the StaffPM
        $Message = sqltime()." - Unresolved by ".$LoggedUser['Username'];
        make_staffpm_note($Message, $ID);

        if (isset($_GET['return']))
            header("Location: staffpm.php?action=viewconv&id=$ID");
        else
            header('Location: staffpm.php?view=open');

    } else {
        // Conversation does not belong to user
        error(403);
    }
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
